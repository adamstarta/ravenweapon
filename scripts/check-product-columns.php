<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=shopware', 'root', 'root');
$stmt = $pdo->query('DESCRIBE product');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . "\n";
}
