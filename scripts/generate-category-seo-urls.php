<?php
/**
 * Generate SEO URLs for all categories
 */

$host = 'localhost';
$dbname = 'shopware';
$user = 'root';
$password = 'root';

$envFile = '/var/www/html/.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    if (preg_match('/DATABASE_URL=mysql:\/\/([^:]+):([^@]+)@([^:\/]+)(?::(\d+))?\/(\w+)/', $envContent, $matches)) {
        $user = $matches[1];
        $password = $matches[2];
        $host = $matches[3];
        $dbname = $matches[5];
    }
}

$pdo = null;
$connectionMethods = [
    "mysql:host=127.0.0.1;port=3306;dbname=$dbname;charset=utf8mb4",
    "mysql:host=$host;port=3306;dbname=$dbname;charset=utf8mb4",
];

foreach ($connectionMethods as $dsn) {
    try {
        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        break;
    } catch (PDOException $e) {
        continue;
    }
}

if ($pdo === null) {
    die("Database connection failed.\n");
}

$salesChannelId = '0191c12dd4b970949e9aeec40433be3e';

// Get language ID
$query = "SELECT LOWER(HEX(language_id)) as language_id FROM sales_channel WHERE LOWER(HEX(id)) = '$salesChannelId'";
$stmt = $pdo->query($query);
$languageId = $stmt->fetchColumn();
echo "Language ID: $languageId\n";

function slugify($text) {
    $replacements = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue',
        ' ' => '-', '/' => '-', '&' => '-', ',' => '', '.' => '', ':' => '',
        '(' => '', ')' => '', '"' => '', "'" => '', '®' => '', '™' => '',
    ];
    $slug = strtr($text, $replacements);
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

echo "=== Generating SEO URLs for all categories ===\n\n";

// Get all categories with breadcrumb (filter by language)
$query = "
SELECT
    LOWER(HEX(c.id)) as category_id,
    ct.name as category_name,
    ct.breadcrumb,
    c.level
FROM category c
JOIN category_translation ct ON c.id = ct.category_id
    AND LOWER(HEX(ct.language_id)) = '$languageId'
WHERE c.active = 1
AND c.level > 1
ORDER BY c.level, ct.name
";

$stmt = $pdo->query($query);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($categories) . " active categories\n\n";

// Delete existing category SEO URLs
echo "Deleting old category SEO URLs...\n";
$pdo->exec("
DELETE FROM seo_url
WHERE route_name = 'frontend.navigation.page'
AND LOWER(HEX(sales_channel_id)) = '$salesChannelId'
");

$created = 0;
$errors = 0;

foreach ($categories as $cat) {
    $categoryId = $cat['category_id'];
    $categoryName = $cat['category_name'];
    $breadcrumb = json_decode($cat['breadcrumb'], true);

    if (!$breadcrumb) {
        echo "  SKIP: {$categoryName} - no breadcrumb\n";
        $errors++;
        continue;
    }

    // Build SEO path from breadcrumb (skip root)
    $pathParts = [];
    $keys = array_keys($breadcrumb);
    foreach ($keys as $i => $key) {
        if ($i === 0) continue; // Skip root
        $pathParts[] = slugify($breadcrumb[$key]);
    }

    if (empty($pathParts)) {
        echo "  SKIP: {$categoryName} - empty path\n";
        continue;
    }

    $seoPath = implode('/', $pathParts) . '/';

    try {
        $insertQuery = "
        INSERT INTO seo_url (id, language_id, sales_channel_id, foreign_key, route_name, path_info, seo_path_info, is_canonical, is_modified, is_deleted, created_at)
        VALUES (
            UNHEX(REPLACE(UUID(), '-', '')),
            UNHEX(?),
            UNHEX(?),
            UNHEX(?),
            'frontend.navigation.page',
            CONCAT('/navigation/', ?),
            ?,
            1,
            0,
            0,
            NOW()
        )
        ";
        $stmt = $pdo->prepare($insertQuery);
        $stmt->execute([
            strtoupper($languageId),
            strtoupper($salesChannelId),
            strtoupper($categoryId),
            $categoryId,
            $seoPath
        ]);
        $created++;
        echo "  OK: {$categoryName} -> /{$seoPath}\n";
    } catch (PDOException $e) {
        echo "  ERROR: {$categoryName} - " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== Summary ===\n";
echo "Created: $created\n";
echo "Errors: $errors\n";

// Verify
$query = "
SELECT COUNT(*) as count
FROM seo_url
WHERE route_name = 'frontend.navigation.page'
AND is_deleted = 0
AND is_canonical = 1
AND LOWER(HEX(sales_channel_id)) = '$salesChannelId'
";
$stmt = $pdo->query($query);
$count = $stmt->fetchColumn();
echo "Categories with canonical SEO URLs: $count\n";

echo "\nDone!\n";
