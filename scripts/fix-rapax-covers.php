<?php
/**
 * Fix RAPAX product cover images
 * Sets the coverId for products that have media but no cover set
 */

$host = '127.0.0.1';
$dbname = 'shopware';
$user = 'root';
$pass = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage() . "\n");
}

echo "=== FIX RAPAX PRODUCT COVERS ===\n\n";

// Find all RAPAX/CARACAL products that have media but no cover
$stmt = $pdo->query("
    SELECT
        p.id as product_id,
        p.version_id,
        pt.name,
        pm.id as product_media_id,
        m.file_name,
        p.product_media_version_id,
        p.cover as cover_id
    FROM product p
    JOIN product_translation pt ON p.id = pt.product_id
    JOIN product_media pm ON p.id = pm.product_id AND p.version_id = pm.product_version_id
    JOIN media m ON pm.media_id = m.id
    WHERE (pt.name LIKE '%RAPAX%' OR pt.name LIKE '%CARACAL%')
    AND m.file_name NOT IN ('product', 'variant')
    ORDER BY pt.name
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($products) . " RAPAX/CARACAL products with real images\n\n";

$fixCount = 0;

foreach ($products as $row) {
    $productId = $row['product_id'];
    $versionId = $row['version_id'];
    $productMediaId = $row['product_media_id'];
    $productMediaVersionId = $row['product_media_version_id'];
    $currentCoverId = $row['cover_id'];
    $name = $row['name'];
    $filename = $row['file_name'];

    echo "Product: $name\n";
    echo "  File: $filename\n";
    echo "  Current cover_id: " . ($currentCoverId ? bin2hex($currentCoverId) : 'NULL') . "\n";
    echo "  Product media ID: " . bin2hex($productMediaId) . "\n";

    // Check if cover needs to be set
    if (!$currentCoverId || $currentCoverId !== $productMediaId) {
        // Update the cover to point to the product_media record
        $updateStmt = $pdo->prepare("
            UPDATE product
            SET cover = :product_media_id
            WHERE id = :product_id AND version_id = :version_id
        ");

        $updateStmt->execute([
            ':product_media_id' => $productMediaId,
            ':product_id' => $productId,
            ':version_id' => $versionId
        ]);

        echo "  FIXED: Set cover_id to " . bin2hex($productMediaId) . "\n";
        $fixCount++;
    } else {
        echo "  OK: Cover already set correctly\n";
    }
    echo "\n";
}

echo "=== SUMMARY ===\n";
echo "Fixed: $fixCount products\n";
echo "\nClear cache: bin/console cache:clear\n";
