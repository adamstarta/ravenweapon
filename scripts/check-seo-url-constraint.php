<?php
/**
 * Check seo_url unique constraints
 */

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=shopware', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Show indexes on seo_url
echo "=== seo_url indexes ===\n";
$stmt = $pdo->query("SHOW INDEX FROM seo_url");
$indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($indexes as $idx) {
    echo "{$idx['Key_name']} - Column: {$idx['Column_name']} - Unique: " . ($idx['Non_unique'] ? 'No' : 'Yes') . "\n";
}

echo "\n=== Existing SEO URLs for the product (all columns) ===\n";
$productNumber = 'SN-25-30l-specialist-backpack-14';
$stmt = $pdo->prepare("
    SELECT
        HEX(su.language_id) as language_id,
        HEX(su.sales_channel_id) as sales_channel_id,
        su.path_info,
        su.seo_path_info,
        su.is_canonical
    FROM seo_url su
    JOIN product p ON su.foreign_key = p.id
    WHERE p.product_number = ?
    AND su.route_name = 'frontend.detail.page'
");
$stmt->execute([$productNumber]);
$urls = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($urls as $url) {
    echo "lang: {$url['language_id']}, channel: {$url['sales_channel_id']}\n";
    echo "  path_info: {$url['path_info']}\n";
    echo "  seo_path: {$url['seo_path_info']}\n";
    echo "  canonical: {$url['is_canonical']}\n\n";
}
