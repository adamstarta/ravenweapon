<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=shopware', 'root', 'root');

echo "=== MAIN CATEGORIES (Level 2 - Navbar Items) ===\n";
$stmt = $pdo->query("
    SELECT HEX(c.id) as id, ct.name, c.visible, c.active,
           (SELECT COUNT(*) FROM category child WHERE child.parent_id = c.id) as children
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE c.level = 2 ORDER BY c.auto_increment
");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['name'] . " | visible:" . $r['visible'] . " | active:" . $r['active'] . " | subs:" . $r['children'] . "\n";
}

echo "\n=== ALL SUBCATEGORIES BY PARENT ===\n";
$parents = $pdo->query("SELECT HEX(c.id) as id, ct.name FROM category c JOIN category_translation ct ON c.id = ct.category_id WHERE c.level = 2 ORDER BY c.auto_increment");
foreach ($parents as $p) {
    $subs = $pdo->query("SELECT ct.name, c.visible, c.active FROM category c JOIN category_translation ct ON c.id = ct.category_id WHERE HEX(c.parent_id) = '" . $p['id'] . "' ORDER BY c.auto_increment");
    $subList = $subs->fetchAll(PDO::FETCH_ASSOC);
    if (count($subList) > 0) {
        echo "\n>> " . $p['name'] . ":\n";
        foreach ($subList as $s) {
            echo "   - " . $s['name'] . " (vis:" . $s['visible'] . ", act:" . $s['active'] . ")\n";
        }
    }
}
