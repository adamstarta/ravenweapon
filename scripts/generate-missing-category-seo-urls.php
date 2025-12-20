<?php
/**
 * Generate Missing Category SEO URLs
 * Creates SEO URLs for all categories that don't have them
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

echo "=== Generate Missing Category SEO URLs ===\n\n";

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
        ' ' => '-', '/' => '-', '&' => '-', ',' => '', "'" => '', '"' => ''
    ];
    $text = strtr($text, $replacements);

    // Remove any remaining special characters
    $text = preg_replace('/[^a-z0-9\-]/', '', $text);

    // Remove multiple dashes
    $text = preg_replace('/-+/', '-', $text);

    // Trim dashes
    return trim($text, '-');
}

// Get all categories without SEO URLs
$sql = "
    SELECT
        c.id as category_id,
        ct.name,
        c.level,
        ct.breadcrumb as seo_breadcrumb
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id AND ct.language_id = ?
    WHERE c.level >= 2
    AND ct.name IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM seo_url su
        WHERE su.foreign_key = c.id
        AND su.route_name = 'frontend.navigation.page'
        AND su.is_deleted = 0
        AND su.language_id = ?
    )
    ORDER BY c.level, ct.name
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$languageId, $languageId]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($categories) . " categories without SEO URLs\n\n";

$created = 0;
$errors = 0;

foreach ($categories as $cat) {
    $categoryId = $cat['category_id'];
    $name = $cat['name'];
    $level = $cat['level'];
    $breadcrumbJson = $cat['seo_breadcrumb'];

    // Parse breadcrumb to build URL
    $breadcrumb = json_decode($breadcrumbJson, true);
    if (!is_array($breadcrumb) || count($breadcrumb) < 2) {
        echo "SKIP: $name - Invalid breadcrumb\n";
        continue;
    }

    // Remove first item (root category "Katalog #1")
    array_shift($breadcrumb);

    // Build SEO path from breadcrumb
    $seoPath = '';
    foreach ($breadcrumb as $part) {
        $seoPath .= slugify($part) . '/';
    }

    echo "Creating: $name (Level $level)\n";
    echo "  Path: $seoPath\n";

    // Generate unique ID
    $seoUrlId = random_bytes(16);

    // Check if this path already exists
    $checkSql = "SELECT COUNT(*) FROM seo_url WHERE seo_path_info = ? AND language_id = ? AND sales_channel_id = ? AND is_deleted = 0";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$seoPath, $languageId, $salesChannelId]);
    if ($checkStmt->fetchColumn() > 0) {
        echo "  SKIP: Path already exists\n";
        continue;
    }

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
            $categoryId,
            $seoPath,
            $categoryId
        ]);

        echo "  CREATED!\n";
        $created++;
    } catch (Exception $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== Summary ===\n";
echo "Created: $created SEO URLs\n";
echo "Errors: $errors\n";

// Clear cache reminder
echo "\n⚠️  Remember to clear the cache: bin/console cache:clear\n";
