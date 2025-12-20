<?php
/**
 * Generate SEO URLs for Raven Weapons products
 * Based on their main_category (Raven Weapons under Waffen)
 */

// Database connection
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

// Sales Channel ID
$salesChannelId = '0191c12dd4b970949e9aeec40433be3e';
$languageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b'; // German

echo "=== Generate SEO URLs for Raven Products ===\n\n";

// Get Raven products with their main category path
$query = "
SELECT DISTINCT
    LOWER(HEX(p.id)) as product_id,
    pt.name as product_name,
    LOWER(HEX(mc.category_id)) as main_cat_id
FROM product p
JOIN product_translation pt ON p.id = pt.product_id
JOIN main_category mc ON p.id = mc.product_id
WHERE pt.name LIKE '%RAVEN%'
  AND p.parent_id IS NULL
";

$stmt = $pdo->query($query);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($products) . " Raven products\n\n";

// Get the SEO path for Raven Weapons category
$ravenWeaponsCatId = '85482f0ec50ecc1a2db23ac833846a49';

// Check existing category SEO URL
$catSeoQuery = "
SELECT seo_path_info FROM seo_url
WHERE LOWER(HEX(foreign_key)) = ?
  AND route_name = 'frontend.navigation.page'
  AND is_canonical = 1
LIMIT 1
";
$stmt = $pdo->prepare($catSeoQuery);
$stmt->execute([$ravenWeaponsCatId]);
$catSeo = $stmt->fetch(PDO::FETCH_ASSOC);

// Always use the full path including parent category
$categoryPath = 'waffen/raven-weapons';
echo "Category path: $categoryPath\n\n";

// Create category SEO URL first
echo "Creating category SEO URL...\n";
$insertCatQuery = "
INSERT INTO seo_url (id, language_id, sales_channel_id, foreign_key, route_name, path_info, seo_path_info, is_canonical, is_modified, is_deleted, custom_fields, created_at, updated_at)
VALUES (
    UNHEX(REPLACE(UUID(), '-', '')),
    UNHEX(?),
    UNHEX(?),
    UNHEX(?),
    'frontend.navigation.page',
    CONCAT('/navigation/', ?),
    ?,
    1,
    1,
    0,
    NULL,
    NOW(),
    NOW()
)
";
$stmt = $pdo->prepare($insertCatQuery);
$stmt->execute([$languageId, $salesChannelId, $ravenWeaponsCatId, $ravenWeaponsCatId, $categoryPath . '/']);
echo "  ✓ Created: /$categoryPath/\n\n";

// Function to create URL-friendly slug
function slugify($text) {
    // Convert to lowercase
    $text = mb_strtolower($text, 'UTF-8');
    // Replace spaces and special chars
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    // Remove leading/trailing dashes
    $text = trim($text, '-');
    return $text;
}

foreach ($products as $product) {
    $productId = $product['product_id'];
    $productName = $product['product_name'];
    $productSlug = slugify($productName);

    // Build SEO path: category-path/product-slug
    $seoPath = $categoryPath . '/' . $productSlug;

    echo "Product: $productName\n";
    echo "  SEO Path: $seoPath\n";

    // Delete existing SEO URLs for this product
    $deleteQuery = "DELETE FROM seo_url WHERE LOWER(HEX(foreign_key)) = ? AND route_name = 'frontend.detail.page'";
    $stmt = $pdo->prepare($deleteQuery);
    $stmt->execute([$productId]);

    // Insert new SEO URL
    $insertQuery = "
    INSERT INTO seo_url (id, language_id, sales_channel_id, foreign_key, route_name, path_info, seo_path_info, is_canonical, is_modified, is_deleted, custom_fields, created_at, updated_at)
    VALUES (
        UNHEX(REPLACE(UUID(), '-', '')),
        UNHEX(?),
        UNHEX(?),
        UNHEX(?),
        'frontend.detail.page',
        CONCAT('/detail/', ?),
        ?,
        1,
        1,
        0,
        NULL,
        NOW(),
        NOW()
    )
    ";
    $stmt = $pdo->prepare($insertQuery);
    $stmt->execute([$languageId, $salesChannelId, $productId, $productId, $seoPath]);

    echo "  ✓ Created SEO URL\n\n";
}

echo "=== COMPLETE ===\n";
echo "New URLs:\n";
foreach ($products as $product) {
    $slug = slugify($product['product_name']);
    echo "  https://ortak.ch/$categoryPath/$slug/\n";
}
