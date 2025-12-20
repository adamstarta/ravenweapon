<?php
/**
 * Fix Category SEO URLs v2
 * Uses actual category path hierarchy instead of breadcrumb field
 */

$host = 'localhost';
$dbname = 'shopware';
$user = 'root';
$pass = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

echo "=== Fix Category SEO URLs v2 ===\n\n";

// Get sales channel ID
$sql = "SELECT id FROM sales_channel WHERE type_id = UNHEX('8A243080F92E4C719546314B577CF82B') LIMIT 1";
$stmt = $pdo->query($sql);
$salesChannelId = $stmt->fetchColumn();
echo "Sales Channel ID: " . bin2hex($salesChannelId) . "\n\n";

// Get language ID (German)
$sql = "SELECT id FROM language WHERE name = 'Deutsch' LIMIT 1";
$stmt = $pdo->query($sql);
$languageId = $stmt->fetchColumn();
echo "Language ID: " . bin2hex($languageId) . "\n\n";

// Function to slugify text (German-aware)
function slugify($text) {
    $text = mb_strtolower($text, 'UTF-8');

    // Replace German umlauts
    $replacements = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue',
        ' ' => '-', '/' => '-', '&' => '', ',' => '', "'" => '', '"' => ''
    ];
    $text = strtr($text, $replacements);

    // Remove any remaining special characters
    $text = preg_replace('/[^a-z0-9\-]/', '', $text);

    // Remove multiple dashes
    $text = preg_replace('/-+/', '-', $text);

    // Trim dashes
    return trim($text, '-');
}

// Step 1: Delete all incorrectly generated SEO URLs from previous run
echo "Step 1: Deleting all category SEO URLs to start fresh...\n";
$deleteSql = "DELETE FROM seo_url WHERE route_name = 'frontend.navigation.page' AND language_id = ? AND is_modified = 1";
$deleteStmt = $pdo->prepare($deleteSql);
$deleteStmt->execute([$languageId]);
echo "Deleted: " . $deleteStmt->rowCount() . " URLs\n\n";

// Step 2: Build category hierarchy map
echo "Step 2: Building category hierarchy...\n";

// Get all categories with their names and paths
$sql = "
    SELECT
        LOWER(HEX(c.id)) as category_id,
        c.id as category_id_bin,
        ct.name,
        c.level,
        c.path
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id AND ct.language_id = ?
    WHERE c.level >= 1
    AND ct.name IS NOT NULL
    ORDER BY c.level
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$languageId]);
$allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build a map of category ID to name
$categoryNames = [];
foreach ($allCategories as $cat) {
    $categoryNames[$cat['category_id']] = $cat['name'];
}

echo "Found " . count($allCategories) . " categories\n\n";

// Step 3: Generate correct SEO URLs based on path hierarchy
echo "Step 3: Generating SEO URLs from path hierarchy...\n\n";

$created = 0;
$errors = 0;

foreach ($allCategories as $cat) {
    if ($cat['level'] < 2) continue; // Skip root category

    $categoryId = $cat['category_id'];
    $categoryIdBin = $cat['category_id_bin'];
    $name = $cat['name'];
    $level = $cat['level'];
    $path = $cat['path'];

    // Parse path to get parent IDs
    // Path format: |id1|id2|id3|
    $pathIds = array_filter(explode('|', $path));

    // Skip first one (root catalog)
    if (count($pathIds) > 0) {
        array_shift($pathIds);
    }

    // Build SEO path from parent names + current name
    $seoPathParts = [];
    foreach ($pathIds as $parentIdHex) {
        $parentIdLower = strtolower($parentIdHex);
        if (isset($categoryNames[$parentIdLower])) {
            $seoPathParts[] = slugify($categoryNames[$parentIdLower]);
        }
    }
    // Add current category
    $seoPathParts[] = slugify($name);

    $seoPath = implode('/', $seoPathParts) . '/';

    echo "Level $level: $name\n";
    echo "  Path: $seoPath\n";

    // Check if this exact path already exists
    $checkSql = "SELECT id FROM seo_url WHERE seo_path_info = ? AND language_id = ? AND sales_channel_id = ? AND is_deleted = 0";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$seoPath, $languageId, $salesChannelId]);
    $existingId = $checkStmt->fetchColumn();

    if ($existingId) {
        // Update existing to be canonical and modified
        $updateSql = "UPDATE seo_url SET is_canonical = 1, is_modified = 1 WHERE id = ?";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([$existingId]);
        echo "  UPDATED existing\n";
        $created++;
    } else {
        // Create new SEO URL
        $seoUrlId = random_bytes(16);
        try {
            $insertSql = "
                INSERT INTO seo_url (
                    id, language_id, sales_channel_id, foreign_key, route_name,
                    seo_path_info, path_info, is_canonical, is_modified, is_deleted,
                    created_at
                ) VALUES (
                    ?, ?, ?, ?, 'frontend.navigation.page',
                    ?, CONCAT('/navigation/', LOWER(HEX(?))), 1, 1, 0,
                    NOW()
                )
            ";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                $seoUrlId,
                $languageId,
                $salesChannelId,
                $categoryIdBin,
                $seoPath,
                $categoryIdBin
            ]);
            echo "  CREATED!\n";
            $created++;
        } catch (Exception $e) {
            echo "  ERROR: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
}

// Step 4: Reset non-modified URLs to non-canonical
echo "\nStep 4: Setting correct canonical flags...\n";
$resetSql = "
    UPDATE seo_url su
    SET su.is_canonical = 0
    WHERE su.route_name = 'frontend.navigation.page'
    AND su.language_id = ?
    AND su.is_modified = 0
";
$resetStmt = $pdo->prepare($resetSql);
$resetStmt->execute([$languageId]);
echo "Reset " . $resetStmt->rowCount() . " non-modified URLs to non-canonical\n";

echo "\n=== Summary ===\n";
echo "Created/Updated: $created SEO URLs\n";
echo "Errors: $errors\n";
