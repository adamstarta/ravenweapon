<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=shopware", "root", "root");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== NAVBAR (Level 2) ===\n";
$stmt = $pdo->query("
    SELECT ct.name, c.visible,
           (SELECT COUNT(*) FROM category child WHERE child.parent_id = c.id) as children
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE c.level = 2 ORDER BY c.auto_increment
");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r["name"] . " (vis:" . $r["visible"] . ", kids:" . $r["children"] . ")\n";
}

echo "\n=== SNIGEL SUBCATEGORIES ===\n";
$stmt = $pdo->query("
    SELECT ct.name, c.visible FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE c.parent_id IN (SELECT c2.id FROM category c2
        JOIN category_translation ct2 ON c2.id = ct2.category_id WHERE ct2.name LIKE \"%Snigel%\")
    ORDER BY ct.name
");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  " . $r["name"] . " (vis:" . $r["visible"] . ")\n";
}

echo "\n=== WAFFEN SUBCATEGORIES ===\n";
$stmt = $pdo->query("
    SELECT ct.name, c.visible FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE c.parent_id IN (SELECT c2.id FROM category c2
        JOIN category_translation ct2 ON c2.id = ct2.category_id WHERE ct2.name = \"Waffen\")
    ORDER BY ct.name
");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  " . $r["name"] . " (vis:" . $r["visible"] . ")\n";
}

echo "\n=== WAFFENZUBEHOR SUBCATEGORIES ===\n";
$stmt = $pdo->query("
    SELECT ct.name, c.visible FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE c.parent_id IN (SELECT c2.id FROM category c2
        JOIN category_translation ct2 ON c2.id = ct2.category_id WHERE ct2.name LIKE \"%Waffenzubeh%\")
    ORDER BY ct.name
");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  " . $r["name"] . " (vis:" . $r["visible"] . ")\n";
}

echo "\n=== ALLE PRODUKTE SUBCATEGORIES ===\n";
$stmt = $pdo->query("
    SELECT ct.name, c.visible FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE c.parent_id IN (SELECT c2.id FROM category c2
        JOIN category_translation ct2 ON c2.id = ct2.category_id WHERE ct2.name = \"Alle Produkte\")
    ORDER BY ct.name
");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  " . $r["name"] . " (vis:" . $r["visible"] . ")\n";
}

echo "\n=== MUNITION SEARCH ===\n";
$stmt = $pdo->query("
    SELECT ct.name, c.level, c.visible FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE ct.name LIKE \"%Munition%\"
");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  " . $r["name"] . " (level:" . $r["level"] . ", vis:" . $r["visible"] . ")\n";
}

