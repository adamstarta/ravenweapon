<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=shopware', 'root', 'root');
$stmt = $pdo->query('DESCRIBE seo_url');
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "{$col['Field']} - {$col['Type']} - {$col['Null']} - Default: {$col['Default']}\n";
}
