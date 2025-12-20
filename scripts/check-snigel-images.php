<?php
/**
 * Check Snigel product images in Shopware to see if filenames contain color info
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

// Check Oyster pouch 1.0 (has Black and Grey colors)
$sku = 'SN-oyster-pouch-1-0';

$stmt = $pdo->prepare("
    SELECT
        p.product_number,
        pt.name as product_name,
        m.file_name,
        m.path
    FROM product p
    JOIN product_translation pt ON p.id = pt.product_id
    LEFT JOIN product_media pm ON p.id = pm.product_id
    LEFT JOIN media m ON pm.media_id = m.id
    WHERE p.product_number = ?
    ORDER BY pm.position
");
$stmt->execute([$sku]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Images for $sku ===\n\n";
foreach ($results as $row) {
    echo "Product: {$row['product_name']}\n";
    echo "File: {$row['file_name']}\n";
    echo "Path: {$row['path']}\n";
    echo "---\n";
}

// Also check Rigid trouser belt (has Black, Olive)
echo "\n\n=== Images for SN-rigid-trouser-belt-05 ===\n\n";
$sku2 = 'SN-rigid-trouser-belt-05';
$stmt->execute([$sku2]);
$results2 = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results2 as $row) {
    echo "Product: {$row['product_name']}\n";
    echo "File: {$row['file_name']}\n";
    echo "Path: {$row['path']}\n";
    echo "---\n";
}

// Check A5 Field binder (has Black and Grey)
echo "\n\n=== Images for SN-a5-field-binder-07 ===\n\n";
$sku3 = 'SN-a5-field-binder-07';
$stmt->execute([$sku3]);
$results3 = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results3 as $row) {
    echo "Product: {$row['product_name']}\n";
    echo "File: {$row['file_name']}\n";
    echo "Path: {$row['path']}\n";
    echo "---\n";
}
