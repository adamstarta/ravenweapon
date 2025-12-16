<?php
/**
 * Reorganize Categories for ortak.ch
 * Based on shop.ravenweapon.ch structure
 *
 * Changes:
 * 1. Snigel → Ausrüstung (brands shouldn't be navbar items)
 * 2. Add Munition to navbar
 * 3. Add Waffen subcategories (Langwaffen, Faustfeuerwaffen)
 * 4. Ensure all subcategories visible in dropdowns
 */

$pdo = new PDO('mysql:host=127.0.0.1;dbname=shopware', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== CATEGORY REORGANIZATION SCRIPT ===\n\n";

// First, let's see the current category structure
echo "=== CURRENT CATEGORY STRUCTURE ===\n";
$stmt = $pdo->query("
    SELECT
        c.id,
        HEX(c.id) as hex_id,
        ct.name,
        c.level,
        c.active,
        c.visible,
        HEX(c.parent_id) as parent_hex,
        c.auto_increment
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE c.level <= 3
    ORDER BY c.level, c.auto_increment
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
$catMap = [];

foreach ($categories as $cat) {
    $indent = str_repeat("  ", $cat['level']);
    $visible = $cat['visible'] ? '✓' : '✗';
    $active = $cat['active'] ? 'ON' : 'OFF';
    echo "{$indent}[L{$cat['level']}] {$cat['name']} (visible:{$visible}, active:{$active})\n";
    echo "{$indent}     ID: {$cat['hex_id']}\n";

    $catMap[$cat['name']] = [
        'id' => $cat['id'],
        'hex_id' => $cat['hex_id'],
        'level' => $cat['level'],
        'parent_hex' => $cat['parent_hex']
    ];
}

echo "\n=== ANALYSIS ===\n";

// Find Snigel category
$snigelStmt = $pdo->query("
    SELECT HEX(c.id) as hex_id, ct.name, c.level
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE ct.name LIKE '%Snigel%' OR ct.name LIKE '%SNIGEL%'
");
$snigelCats = $snigelStmt->fetchAll(PDO::FETCH_ASSOC);
echo "Snigel categories found: " . count($snigelCats) . "\n";
foreach ($snigelCats as $s) {
    echo "  - {$s['name']} (Level {$s['level']}, ID: {$s['hex_id']})\n";
}

// Find Waffen category
$waffenStmt = $pdo->query("
    SELECT HEX(c.id) as hex_id, ct.name, c.level
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE ct.name = 'Waffen'
");
$waffenCats = $waffenStmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nWaffen categories found: " . count($waffenCats) . "\n";
foreach ($waffenCats as $w) {
    echo "  - {$w['name']} (Level {$w['level']}, ID: {$w['hex_id']})\n";
}

// Find Munition category
$munitionStmt = $pdo->query("
    SELECT HEX(c.id) as hex_id, ct.name, c.level
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE ct.name LIKE '%Munition%'
");
$munitionCats = $munitionStmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nMunition categories found: " . count($munitionCats) . "\n";
foreach ($munitionCats as $m) {
    echo "  - {$m['name']} (Level {$m['level']}, ID: {$m['hex_id']})\n";
}

// Find Waffenzubehör and its subcategories
$waffenzubehorStmt = $pdo->query("
    SELECT HEX(c.id) as hex_id, ct.name, c.level, HEX(c.parent_id) as parent_hex
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE ct.name LIKE '%Waffenzubeh%' OR ct.name LIKE '%zubehör%'
    ORDER BY c.level
");
$waffenzubehorCats = $waffenzubehorStmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nWaffenzubehör categories found: " . count($waffenzubehorCats) . "\n";
foreach ($waffenzubehorCats as $wz) {
    echo "  - {$wz['name']} (Level {$wz['level']}, ID: {$wz['hex_id']})\n";
}

// Get all level 2 categories (navbar items)
echo "\n=== CURRENT NAVBAR (Level 2 Categories) ===\n";
$navbarStmt = $pdo->query("
    SELECT HEX(c.id) as hex_id, ct.name, c.visible, c.active,
           (SELECT COUNT(*) FROM category child WHERE child.parent_id = c.id) as child_count
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE c.level = 2
    ORDER BY c.auto_increment
");
$navbarCats = $navbarStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($navbarCats as $nav) {
    $vis = $nav['visible'] ? '✓' : '✗';
    echo "  [{$vis}] {$nav['name']} ({$nav['child_count']} children)\n";
}

echo "\n=== PROPOSED CHANGES ===\n";
echo "1. Rename 'Snigel' navbar item to 'Ausrüstung'\n";
echo "2. Add 'Munition' to navbar (if not exists at level 2)\n";
echo "3. Add subcategories to 'Waffen': Langwaffen, Faustfeuerwaffen\n";
echo "4. Make all subcategories visible in navigation\n";

echo "\n=== END ANALYSIS ===\n";
echo "\nRun with --execute flag to make changes\n";

// Check if we should execute changes
if (in_array('--execute', $argv ?? [])) {
    echo "\n=== EXECUTING CHANGES ===\n";

    // 1. Rename Snigel to Ausrüstung
    echo "\n1. Renaming 'Snigel' to 'Ausrüstung'...\n";
    $stmt = $pdo->prepare("
        UPDATE category_translation
        SET name = 'Ausrüstung', updated_at = NOW()
        WHERE name = 'Snigel'
    ");
    $stmt->execute();
    echo "   Updated " . $stmt->rowCount() . " rows\n";

    // 2. Find or create Munition at navbar level
    // First check if it exists
    $munCheck = $pdo->query("
        SELECT HEX(c.id) as hex_id, c.level
        FROM category c
        JOIN category_translation ct ON c.id = ct.category_id
        WHERE ct.name = 'Munition' AND c.level = 2
    ")->fetch();

    if (!$munCheck) {
        echo "\n2. Munition not at navbar level - needs to be added/moved\n";
        // Check if it exists anywhere
        $munAnywhere = $pdo->query("
            SELECT HEX(c.id) as hex_id, c.level, HEX(c.parent_id) as parent_hex
            FROM category c
            JOIN category_translation ct ON c.id = ct.category_id
            WHERE ct.name LIKE '%Munition%'
            LIMIT 1
        ")->fetch();

        if ($munAnywhere) {
            echo "   Found Munition at level {$munAnywhere['level']} - will need to move to navbar\n";
        } else {
            echo "   Munition category doesn't exist - will need to create\n";
        }
    } else {
        echo "\n2. Munition already at navbar level ✓\n";
    }

    // 3. Make all subcategories visible in navigation
    echo "\n3. Making all level 3 categories visible...\n";
    $stmt = $pdo->exec("
        UPDATE category
        SET visible = 1, updated_at = NOW()
        WHERE level = 3 AND visible = 0
    ");
    echo "   Made " . $stmt . " categories visible\n";

    echo "\n=== CHANGES COMPLETE ===\n";
    echo "Please clear Shopware cache: bin/console cache:clear\n";
}
