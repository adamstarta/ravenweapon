<?php
/**
 * Verify Snigel color data in Shopware database
 * Check if colors are properly imported from JSON
 */

$host = '127.0.0.1';
$dbname = 'shopware';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

// Check a few Snigel products
$stmt = $pdo->query("
    SELECT
        p.product_number,
        pt.name,
        pt.custom_fields
    FROM product p
    LEFT JOIN product_translation pt ON p.id = pt.product_id
    WHERE p.product_number LIKE 'SN-%'
    AND pt.custom_fields LIKE '%snigel_color_options%'
    LIMIT 10
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Snigel Products with Color Options ===\n\n";

foreach ($products as $product) {
    $customFields = json_decode($product['custom_fields'], true);
    $colorOptions = $customFields['snigel_color_options'] ?? [];

    echo "Product: {$product['product_number']}\n";
    echo "Name: {$product['name']}\n";
    echo "Colors: ";

    if (!empty($colorOptions)) {
        $names = array_map(fn($c) => $c['name'], $colorOptions);
        echo implode(', ', $names);
    } else {
        echo "(none)";
    }
    echo "\n\n";
}

// Count stats
$stmt = $pdo->query("
    SELECT COUNT(*) as total FROM product WHERE product_number LIKE 'SN-%'
");
$total = $stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT COUNT(*) as withColors
    FROM product p
    LEFT JOIN product_translation pt ON p.id = pt.product_id
    WHERE p.product_number LIKE 'SN-%'
    AND pt.custom_fields LIKE '%snigel_color_options%'
    AND pt.custom_fields NOT LIKE '%snigel_color_options\":[]%'
");
$withColors = $stmt->fetch()['withColors'];

echo "=== Stats ===\n";
echo "Total Snigel products: $total\n";
echo "Products with color options: $withColors\n";
