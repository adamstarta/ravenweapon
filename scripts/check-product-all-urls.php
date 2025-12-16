<?php
/**
 * Check ALL SEO URLs for a specific product
 */

$host = '127.0.0.1';
$port = 3306;
$dbname = 'shopware';
$username = 'root';
$password = 'root';

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check 30L Specialist backpack (product that's in K9 category)
$productNumber = 'SN-25-30l-specialist-backpack-14';

echo "=== Product: $productNumber ===\n\n";

// Get product info and categories
$stmt = $pdo->prepare("
    SELECT
        HEX(p.id) as product_id,
        p.product_number,
        pt.name as product_name
    FROM product p
    JOIN product_translation pt ON p.id = pt.product_id
    WHERE p.product_number = ?
    LIMIT 1
");
$stmt->execute([$productNumber]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Product: {$product['product_name']}\n\n";

// Get product categories
echo "Categories:\n";
$stmt = $pdo->prepare("
    SELECT
        ct.name as category_name,
        su.seo_path_info as category_path
    FROM product_category pc
    JOIN category c ON pc.category_id = c.id
    JOIN category_translation ct ON c.id = ct.category_id
    LEFT JOIN seo_url su ON su.foreign_key = c.id AND su.route_name = 'frontend.navigation.page' AND su.is_canonical = 1
    WHERE pc.product_id = UNHEX(?)
    ORDER BY ct.name
");
$stmt->execute([$product['product_id']]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($categories as $cat) {
    echo "  - {$cat['category_name']}: {$cat['category_path']}\n";
}

// Get ALL SEO URLs (including deleted)
echo "\nALL SEO URLs:\n";
$stmt = $pdo->prepare("
    SELECT
        su.seo_path_info,
        su.is_canonical,
        su.is_deleted,
        su.is_modified
    FROM seo_url su
    WHERE su.foreign_key = UNHEX(?)
    AND su.route_name = 'frontend.detail.page'
    ORDER BY su.is_canonical DESC, su.seo_path_info
");
$stmt->execute([$product['product_id']]);
$urls = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($urls as $url) {
    $flags = [];
    if ($url['is_canonical']) $flags[] = 'CANONICAL';
    if ($url['is_deleted']) $flags[] = 'DELETED';
    if ($url['is_modified']) $flags[] = 'MODIFIED';
    $flagStr = empty($flags) ? '' : '[' . implode(', ', $flags) . '] ';
    echo "  {$flagStr}{$url['seo_path_info']}\n";
}

echo "\nTotal: " . count($urls) . " URLs\n";
