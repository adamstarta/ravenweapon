<?php
/**
 * Analyze ALL category SEO URL issues
 * Find mismatches between category names and SEO URL paths
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

echo "=== Analyze All Category SEO URL Issues ===\n\n";

// Function to slugify text (German-aware)
function slugify($text) {
    $text = mb_strtolower($text, 'UTF-8');
    $replacements = [
        ' & ' => '-', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue',
        '&' => '-', ' ' => '-', ',' => '', '.' => '', '/' => '-', '--' => '-'
    ];
    $text = strtr($text, $replacements);
    $text = preg_replace('/[^a-z0-9\-]/', '', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

// Get English language ID (sales channel uses this)
$sql = "SELECT id FROM language WHERE name = 'English' LIMIT 1";
$stmt = $pdo->query($sql);
$englishLangId = $stmt->fetchColumn();
echo "English Language ID: " . bin2hex($englishLangId) . "\n";

// Get sales channel ID
$sql = "SELECT id FROM sales_channel WHERE type_id = UNHEX('8A243080F92E4C719546314B577CF82B') LIMIT 1";
$stmt = $pdo->query($sql);
$salesChannelId = $stmt->fetchColumn();
echo "Sales Channel ID: " . bin2hex($salesChannelId) . "\n\n";

// Get all categories with their German and English names
$sql = "
    SELECT
        LOWER(HEX(c.id)) as category_id,
        c.id as category_id_bin,
        c.level,
        c.path,
        ct_en.name as english_name,
        ct_de.name as german_name
    FROM category c
    LEFT JOIN category_translation ct_en ON c.id = ct_en.category_id AND ct_en.language_id = ?
    LEFT JOIN category_translation ct_de ON c.id = ct_de.category_id AND ct_de.language_id != ?
    WHERE c.level >= 2
    AND (ct_en.name IS NOT NULL OR ct_de.name IS NOT NULL)
    ORDER BY c.level, ct_en.name
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$englishLangId, $englishLangId]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($categories) . " categories\n\n";

// Build category name map
$categoryNames = [];
foreach ($categories as $cat) {
    $name = $cat['english_name'] ?: $cat['german_name'];
    $categoryNames[$cat['category_id']] = $name;
}

$issues = [];
$needsFix = [];

foreach ($categories as $cat) {
    $categoryId = $cat['category_id'];
    $categoryIdBin = $cat['category_id_bin'];
    $level = $cat['level'];
    $englishName = $cat['english_name'];
    $germanName = $cat['german_name'];
    $path = $cat['path'];

    // Build expected German SEO path from hierarchy
    $pathIds = array_filter(explode('|', $path));
    if (count($pathIds) > 0) {
        array_shift($pathIds); // Skip root
    }

    $expectedPathParts = [];
    foreach ($pathIds as $parentIdHex) {
        $parentIdLower = strtolower($parentIdHex);
        if (isset($categoryNames[$parentIdLower])) {
            $expectedPathParts[] = slugify($categoryNames[$parentIdLower]);
        }
    }
    // Add current category (prefer German name for display, but check both)
    $displayName = $germanName ?: $englishName;
    $expectedPathParts[] = slugify($displayName);
    $expectedPath = implode('/', $expectedPathParts) . '/';

    // Get current SEO URL for this category (English language for sales channel)
    $sql = "SELECT seo_path_info FROM seo_url
            WHERE foreign_key = ? AND language_id = ? AND is_canonical = 1
            AND route_name = 'frontend.navigation.page' AND is_deleted = 0 LIMIT 1";
    $checkStmt = $pdo->prepare($sql);
    $checkStmt->execute([$categoryIdBin, $englishLangId]);
    $currentPath = $checkStmt->fetchColumn();

    // Check if there's a mismatch
    if ($currentPath && $currentPath !== $expectedPath) {
        $issues[] = [
            'id' => $categoryId,
            'level' => $level,
            'german_name' => $germanName,
            'english_name' => $englishName,
            'current_path' => $currentPath,
            'expected_path' => $expectedPath
        ];
        $needsFix[] = [
            'id' => $categoryIdBin,
            'expected_path' => $expectedPath
        ];
    } elseif (!$currentPath) {
        echo "MISSING: Level $level - " . ($germanName ?: $englishName) . "\n";
        echo "  Expected: $expectedPath\n";
    }
}

echo "\n=== Categories with WRONG SEO URLs ===\n\n";
foreach ($issues as $issue) {
    echo "Level {$issue['level']}: {$issue['german_name']} ({$issue['english_name']})\n";
    echo "  CURRENT:  {$issue['current_path']}\n";
    echo "  EXPECTED: {$issue['expected_path']}\n";
    echo "\n";
}

echo "Total issues found: " . count($issues) . "\n";
