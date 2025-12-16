<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=shopware;charset=utf8mb4", "root", "root");
$stmt = $pdo->query("
    SELECT pt.name,
           (SELECT COUNT(*) FROM product_media WHERE product_id = p.id) as media_count
    FROM product p
    JOIN product_translation pt ON p.id = pt.product_id
    WHERE pt.name LIKE '%RAPAX%' OR pt.name LIKE '%CARACAL%' OR pt.name LIKE '%Lynx%'
    ORDER BY pt.name
");

echo "=== RAPAX/CARACAL PRODUCT IMAGES ===\n\n";

$withImages = 0;
$withoutImages = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $status = $row["media_count"] > 0 ? "✓" : "✗";
    echo "$status {$row['name']} ({$row['media_count']} images)\n";

    if ($row["media_count"] > 0) {
        $withImages++;
    } else {
        $withoutImages++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "With images: $withImages\n";
echo "Without images: $withoutImages\n";
