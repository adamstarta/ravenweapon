<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=shopware', 'root', 'root');

echo "=== SEO URL Table Structure ===\n";
$stmt = $pdo->query('DESCRIBE seo_url');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\n=== Sample Product SEO URLs ===\n";
$stmt = $pdo->query("
    SELECT su.seo_path_info, su.path_info, pt.name
    FROM seo_url su
    JOIN product_translation pt ON UNHEX(SUBSTRING(su.path_info, LOCATE('/', su.path_info)+1)) = pt.product_id
    WHERE su.route_name = 'frontend.detail.page'
    AND su.is_canonical = 1
    LIMIT 10
");

while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['seo_path_info'] . " => " . $row['name'] . "\n";
}

echo "\n=== Current Category URLs ===\n";
$stmt = $pdo->query("
    SELECT seo_path_info, ct.name
    FROM seo_url su
    JOIN category c ON UNHEX(SUBSTRING(su.path_info, LENGTH('/navigation/')+1)) = c.id
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE su.route_name = 'frontend.navigation.page'
    AND su.is_canonical = 1
    LIMIT 20
");

while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['seo_path_info'] . " => " . $row['name'] . "\n";
}
