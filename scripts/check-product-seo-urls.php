<?php
/**
 * Check SEO URLs for a specific product
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

$stmt = $pdo->prepare("
    SELECT
        p.product_number,
        pt.name as product_name,
        su.seo_path_info,
        su.is_canonical,
        su.is_deleted
    FROM product p
    JOIN product_translation pt ON p.id = pt.product_id
    JOIN seo_url su ON su.foreign_key = p.id AND su.route_name = 'frontend.detail.page'
    WHERE p.product_number = ?
    ORDER BY su.is_canonical DESC, su.seo_path_info
");
$stmt->execute([$productNumber]);
$urls = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "SEO URLs for $productNumber:\n\n";
foreach ($urls as $url) {
    $canonical = $url['is_canonical'] ? '[CANONICAL]' : '';
    $deleted = $url['is_deleted'] ? '[DELETED]' : '';
    echo "  {$canonical}{$deleted} {$url['seo_path_info']}\n";
}
echo "\nTotal: " . count($urls) . " URLs\n";
