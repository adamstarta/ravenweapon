<?php
/**
 * Generate SEO URLs for all products based on their main_category
 * Uses the same template pattern: category-path/product-name
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

// Sales channel and language IDs
$salesChannelId = '0191c12dd4b970949e9aeec40433be3e';

// Get language ID for the sales channel
$query = "
SELECT LOWER(HEX(language_id)) as language_id
FROM sales_channel
WHERE LOWER(HEX(id)) = '$salesChannelId'
";
$stmt = $pdo->query($query);
$languageId = $stmt->fetchColumn();
echo "Language ID: $languageId\n";

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

echo "=== Generating SEO URLs for all products ===\n\n";

// Get all products with their main category breadcrumb (filter by language to avoid duplicates)
$query = "
SELECT DISTINCT
    LOWER(HEX(p.id)) as product_id,
    pt.name as product_name,
    LOWER(HEX(mc.category_id)) as main_category_id,
    ct.breadcrumb as category_breadcrumb
FROM product p
JOIN product_translation pt ON p.id = pt.product_id
    AND pt.name IS NOT NULL
    AND LOWER(HEX(pt.language_id)) = '$languageId'
JOIN main_category mc ON p.id = mc.product_id AND LOWER(HEX(mc.sales_channel_id)) = '$salesChannelId'
JOIN category_translation ct ON mc.category_id = ct.category_id
    AND LOWER(HEX(ct.language_id)) = '$languageId'
WHERE p.parent_id IS NULL
ORDER BY pt.name
";

$stmt = $pdo->query($query);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($products) . " products with main_category\n\n";

// First, DELETE all existing product SEO URLs for this sales channel (clean slate)
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
    $breadcrumb = json_decode($product['category_breadcrumb'], true);

    if (!$breadcrumb) {
        echo "  SKIP: {$productName} - no breadcrumb\n";
        $errors++;
        continue;
    }

    // Build SEO path: skip first element (root category), slugify the rest
    $pathParts = [];
    $keys = array_keys($breadcrumb);
    foreach ($keys as $i => $key) {
        if ($i === 0) continue; // Skip root category
        $pathParts[] = slugify($breadcrumb[$key]);
    }

    // Add product name
    $productSlug = slugify($productName);

    // Build full path
    $seoPath = implode('/', $pathParts) . '/' . $productSlug;

    // Insert new SEO URL
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
