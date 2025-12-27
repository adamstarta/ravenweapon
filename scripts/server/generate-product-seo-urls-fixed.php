<?php
/**
 * Generate SEO URLs for all products based on their main_category
 * FIXED VERSION: Uses the actual path (parent UUIDs) instead of corrupted breadcrumb JSON
 */

$host = 'localhost';
$dbname = 'shopware';
$user = 'root';
$password = 'root';

$envFile = '/var/www/html/.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    if (preg_match('/DATABASE_URL=mysql:\/\/([^:]+):([^@]+)@([^\/:]+)(?::(\d+))?\/(\\w+)/', $envContent, $matches)) {
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
$languageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';

function slugify($text) {
    $replacements = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue',
        ' ' => '-', '/' => '-', '&' => '-', ',' => '', '.' => '', ':' => '',
        '(' => '', ')' => '', '"' => '', "'" => '', '®' => '', '™' => '',
        '+' => '-', '–' => '-', '—' => '-'
    ];

    $slug = strtr($text, $replacements);
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');

    return $slug;
}

echo "=== Generating SEO URLs for all products (FIXED VERSION) ===\n\n";
echo "Using path field (correct parent hierarchy) instead of corrupted breadcrumb JSON\n\n";

// First, load ALL categories with their names and paths
$query = "
SELECT
    LOWER(HEX(c.id)) as category_id,
    ct.name as category_name,
    c.path
FROM category c
JOIN category_translation ct ON c.id = ct.category_id
    AND LOWER(HEX(ct.language_id)) = '$languageId'
WHERE c.active = 1
";

$stmt = $pdo->query($query);
$allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build lookup maps
$categoryNames = [];
$categoryPaths = [];
foreach ($allCategories as $cat) {
    $categoryNames[$cat['category_id']] = $cat['category_name'];
    $categoryPaths[$cat['category_id']] = $cat['path'];
}

echo "Loaded " . count($allCategories) . " categories for lookup\n\n";

// Get all products with their main category
$query = "
SELECT DISTINCT
    LOWER(HEX(p.id)) as product_id,
    pt.name as product_name,
    LOWER(HEX(mc.category_id)) as main_category_id
FROM product p
JOIN product_translation pt ON p.id = pt.product_id
    AND pt.name IS NOT NULL
    AND LOWER(HEX(pt.language_id)) = '$languageId'
JOIN main_category mc ON p.id = mc.product_id AND LOWER(HEX(mc.sales_channel_id)) = '$salesChannelId'
WHERE p.parent_id IS NULL
ORDER BY pt.name
";

$stmt = $pdo->query($query);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($products) . " products with main_category\n\n";

// Delete existing product SEO URLs
echo "Deleting old product SEO URLs...\n";
$pdo->exec("
DELETE FROM seo_url
WHERE route_name = 'frontend.detail.page'
AND LOWER(HEX(sales_channel_id)) = '$salesChannelId'
");

$created = 0;
$errors = 0;

foreach ($products as $product) {
    $productId = $product['product_id'];
    $productName = $product['product_name'];
    $mainCategoryId = $product['main_category_id'];

    if (!isset($categoryPaths[$mainCategoryId])) {
        echo "  SKIP: {$productName} - category not found\n";
        $errors++;
        continue;
    }

    // Get the category's path
    $catPath = $categoryPaths[$mainCategoryId];
    $mainCatName = $categoryNames[$mainCategoryId] ?? '';

    // Parse the path to get parent category IDs
    $parentIds = [];
    if (!empty($catPath)) {
        $pathIds = array_filter(explode('|', $catPath), function($id) {
            return !empty($id) && strlen($id) >= 32;
        });
        $parentIds = array_values($pathIds);
    }

    // Build SEO path by looking up each parent's name in correct order
    $pathParts = [];
    foreach ($parentIds as $parentId) {
        $parentIdLower = strtolower($parentId);
        if (isset($categoryNames[$parentIdLower])) {
            $parentName = $categoryNames[$parentIdLower];
            // Skip root category (Catalogue #1)
            if ($parentName !== 'Catalogue #1') {
                $pathParts[] = slugify($parentName);
            }
        }
    }

    // Add main category itself
    if (!empty($mainCatName) && $mainCatName !== 'Catalogue #1') {
        $pathParts[] = slugify($mainCatName);
    }

    // Add product name
    $productSlug = slugify($productName);
    $seoPath = implode('/', $pathParts) . '/' . $productSlug;

    try {
        $insertQuery = "
        INSERT INTO seo_url (id, language_id, sales_channel_id, foreign_key, route_name, path_info, seo_path_info, is_canonical, is_modified, is_deleted, created_at)
        VALUES (
            UNHEX(REPLACE(UUID(), '-', '')),
            UNHEX(?),
            UNHEX(?),
            UNHEX(?),
            'frontend.detail.page',
            CONCAT('/detail/', ?),
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
            strtoupper($productId),
            $productId,
            $seoPath
        ]);
        $created++;
        echo "  OK: {$productName} -> /{$seoPath}\n";
    } catch (PDOException $e) {
        echo "  ERROR: {$productName} - " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== Summary ===\n";
echo "Created/Updated: $created\n";
echo "Errors: $errors\n";

// Verify
echo "\n=== Verification ===\n";
$query = "
SELECT COUNT(*) as count
FROM seo_url
WHERE route_name = 'frontend.detail.page'
AND is_deleted = 0
AND is_canonical = 1
AND LOWER(HEX(sales_channel_id)) = '$salesChannelId'
";
$stmt = $pdo->query($query);
$count = $stmt->fetchColumn();
echo "Products with canonical SEO URLs: $count\n";

echo "\nDone!\n";
