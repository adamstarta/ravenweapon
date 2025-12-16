<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=shopware', 'root', 'root');

// Find Ausrüstung/Snigel category
$stmt = $pdo->query("SELECT LOWER(HEX(id)) as id, JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"de-CH\"')) as name FROM category WHERE JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"de-CH\"')) = 'Ausrüstung' OR JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"de-CH\"')) = 'Snigel'");
$ausruestung = $stmt->fetch(PDO::FETCH_ASSOC);

if ($ausruestung) {
    echo "Found: " . $ausruestung['name'] . " (ID: " . $ausruestung['id'] . ")\n\n";

    // Get subcategories
    $stmt = $pdo->prepare("SELECT LOWER(HEX(id)) as id, JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"de-CH\"')) as name FROM category WHERE LOWER(HEX(parent_id)) = ? ORDER BY auto_increment");
    $stmt->execute([$ausruestung['id']]);
    $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($subs) . " subcategories:\n";
    foreach ($subs as $i => $s) {
        echo ($i+1) . ". " . $s['name'] . "\n";
    }
} else {
    // List all level 2 categories
    echo "Ausrüstung not found, listing all level 2 categories:\n";
    $stmt = $pdo->query("SELECT LOWER(HEX(id)) as id, JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"de-CH\"')) as name, level FROM category WHERE level = 2 ORDER BY auto_increment");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['name'] . " (ID: " . $row['id'] . ")\n";
    }
}
