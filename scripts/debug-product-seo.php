<?php
/**
 * Debug SEO URL creation for a specific product
 */

$host = '127.0.0.1';
$port = 3306;
$dbname = 'shopware';
$username = 'root';
$password = 'root';

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$productNumber = 'SN-25-30l-specialist-backpack-14';

// Get product
$stmt = $pdo->prepare("
    SELECT HEX(p.id) as product_id, p.product_number, su.seo_path_info as canonical_url
    FROM product p
    LEFT JOIN seo_url su ON su.foreign_key = p.id AND su.route_name = 'frontend.detail.page' AND su.is_canonical = 1
    WHERE p.product_number = ?
");
$stmt->execute([$productNumber]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Product: {$product['product_number']}\n";
echo "Canonical URL: {$product['canonical_url']}\n";

// Get product slug
$urlParts = explode('/', $product['canonical_url']);
$productSlug = end($urlParts);
echo "Product slug: $productSlug\n\n";

// Get ALL distinct category paths for this product
echo "=== Category paths for this product ===\n";
$stmt = $pdo->prepare("
    SELECT DISTINCT su.seo_path_info as category_path
    FROM product_category pc
    JOIN seo_url su ON su.foreign_key = pc.category_id
        AND su.route_name = 'frontend.navigation.page'
        AND su.is_canonical = 1
    WHERE pc.product_id = UNHEX(?)
    AND su.seo_path_info IS NOT NULL
    ORDER BY su.seo_path_info
");
$stmt->execute([$product['product_id']]);
$categoryPaths = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($categoryPaths as $path) {
    echo "  $path\n";
}

echo "\n=== What URLs SHOULD exist ===\n";
foreach ($categoryPaths as $catPath) {
    $catPath = rtrim($catPath, '/');
    if ($catPath === 'Ausruestung' || $catPath === 'Alle-Produkte') {
        continue;
    }
    $newPath = $catPath . '/' . $productSlug;
    echo "  $newPath\n";
}

echo "\n=== What URLs actually exist ===\n";
$stmt = $pdo->prepare("
    SELECT seo_path_info, is_canonical, is_deleted
    FROM seo_url
    WHERE foreign_key = UNHEX(?)
    AND route_name = 'frontend.detail.page'
    ORDER BY seo_path_info
");
$stmt->execute([$product['product_id']]);
$existingUrls = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($existingUrls as $url) {
    $flags = [];
    if ($url['is_canonical']) $flags[] = 'CANONICAL';
    if ($url['is_deleted']) $flags[] = 'DELETED';
    $flagStr = empty($flags) ? '' : ' [' . implode(', ', $flags) . ']';
    echo "  {$url['seo_path_info']}$flagStr\n";
}
