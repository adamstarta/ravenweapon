<?php
/**
 * Verify size options are properly stored in products
 */

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=shopware', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== VERIFYING SIZE OPTIONS ===\n\n";

// Get products with size options
$stmt = $pdo->query("
    SELECT
        p.product_number,
        pt.name,
        pt.custom_fields
    FROM product p
    JOIN product_translation pt ON p.id = pt.product_id
    WHERE pt.custom_fields LIKE '%snigel_has_sizes%'
    ORDER BY pt.name
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($products) . " products with size options\n\n";

foreach ($products as $product) {
    $customFields = json_decode($product['custom_fields'], true) ?: [];
    $hasSizes = $customFields['snigel_has_sizes'] ?? false;
    $sizeOptions = $customFields['snigel_size_options'] ?? '[]';

    // Decode size options if it's a string
    if (is_string($sizeOptions)) {
        $sizes = json_decode($sizeOptions, true) ?: [];
    } else {
        $sizes = $sizeOptions;
    }

    if ($hasSizes && !empty($sizes)) {
        echo "[OK] {$product['product_number']}\n";
        echo "    Name: {$product['name']}\n";
        $sizeNames = array_map(function($s) { return $s['name']; }, $sizes);
        echo "    Sizes: " . implode(', ', $sizeNames) . "\n\n";
    }
}

echo "=== VERIFICATION COMPLETE ===\n";
