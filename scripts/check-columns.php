<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=shopware', 'root', 'root');
$stmt = $pdo->query("SHOW COLUMNS FROM product_translation");
echo "=== product_translation columns ===\n";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . "\n";
}

echo "\n=== product columns ===\n";
$stmt = $pdo->query("SHOW COLUMNS FROM product");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . "\n";
}
