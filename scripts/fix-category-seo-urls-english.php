<?php
/**
 * Fix Category SEO URLs - English Language
 * Uses English language ID to match sales channel domain configuration
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

echo "=== Fix Category SEO URLs - English Language ===\n\n";

// Get sales channel ID
$sql = "SELECT id FROM sales_channel WHERE type_id = UNHEX('8A243080F92E4C719546314B577CF82B') LIMIT 1";
$stmt = $pdo->query($sql);
$salesChannelId = $stmt->fetchColumn();
echo "Sales Channel ID: " . bin2hex($salesChannelId) . "\n";

// Get ENGLISH language ID (this is what the sales channel uses!)
$sql = "SELECT id FROM language WHERE name = 'English' LIMIT 1";
$stmt = $pdo->query($sql);
$englishLangId = $stmt->fetchColumn();
echo "English Language ID: " . bin2hex($englishLangId) . "\n\n";

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

// Step 1: Build category hierarchy map using ENGLISH names
echo "Step 1: Building category hierarchy from English translations...\n";

// Get all categories with their ENGLISH names and paths
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
$stmt->execute([$englishLangId]);
$allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build a map of category ID to name
$categoryNames = [];
foreach ($allCategories as $cat) {
    $categoryNames[$cat['category_id']] = $cat['name'];
}

echo "Found " . count($allCategories) . " categories with English translations\n\n";

// Step 2: Generate SEO URLs with English language ID
echo "Step 2: Generating SEO URLs with English language ID...\n\n";

$created = 0;
$updated = 0;
$errors = 0;

foreach ($allCategories as $cat) {
    if ($cat['level'] < 2) continue; // Skip root category

    $categoryId = $cat['category_id'];
    $categoryIdBin = $cat['category_id_bin'];
    $name = $cat['name'];
    $level = $cat['level'];
    $path = $cat['path'];

    // Parse path to get parent IDs
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

    // Check if SEO URL already exists for this category with English language
    $checkSql = "SELECT id, seo_path_info FROM seo_url
                 WHERE foreign_key = ?
                 AND language_id = ?
                 AND sales_channel_id = ?
                 AND route_name = 'frontend.navigation.page'
                 AND is_deleted = 0";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$categoryIdBin, $englishLangId, $salesChannelId]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($existing['seo_path_info'] !== $seoPath) {
            // Update existing entry
            $updateSql = "UPDATE seo_url SET seo_path_info = ?, is_canonical = 1, is_modified = 1 WHERE id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$seoPath, $existing['id']]);
            echo "  UPDATED from: {$existing['seo_path_info']}\n";
            $updated++;
        } else {
            echo "  Already correct\n";
        }
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
                $englishLangId,
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

echo "\n=== Summary ===\n";
echo "Created: $created SEO URLs\n";
echo "Updated: $updated SEO URLs\n";
echo "Errors: $errors\n";
