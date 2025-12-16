<?php
/**
 * Fix Navigation Structure for ortak.ch
 * Based on shop.ravenweapon.ch
 */

$pdo = new PDO('mysql:host=127.0.0.1;dbname=shopware', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== NAVIGATION FIX SCRIPT ===\n\n";

// 1. Rename Snigel to Ausruestung
echo "1. Renaming Snigel to Ausruestung...\n";
$stmt = $pdo->exec("UPDATE category_translation SET name = 'Ausrüstung', updated_at = NOW() WHERE name = 'Snigel'");
echo "   Updated $stmt rows\n";

// 2. Make all subcategories visible
echo "\n2. Making all subcategories visible...\n";
$count = $pdo->exec("UPDATE category SET visible = 1, updated_at = NOW() WHERE level = 3 AND visible = 0");
echo "   Made $count categories visible\n";

// 3. Show current navbar
echo "\n=== CURRENT NAVBAR ===\n";
$stmt = $pdo->query("SELECT ct.name, c.visible, (SELECT COUNT(*) FROM category child WHERE child.parent_id = c.id) as children FROM category c JOIN category_translation ct ON c.id = ct.category_id WHERE c.level = 2 AND ct.name != '' ORDER BY c.auto_increment");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  - " . $r['name'] . " (" . $r['children'] . " subs)\n";
}

echo "\n=== DONE ===\n";
