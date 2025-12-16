<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=shopware', 'root', 'root');

$stmt = $pdo->query("SHOW TABLES LIKE '%custom_field%'");
echo "=== Custom Field Tables ===\n";
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo $row[0] . "\n";
}

echo "\n=== custom_field_set columns ===\n";
$stmt = $pdo->query("SHOW COLUMNS FROM custom_field_set");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\n=== custom_field columns ===\n";
$stmt = $pdo->query("SHOW COLUMNS FROM custom_field");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
