<?php
/**
 * List all products in Shopware to help identify Snigel products
 */

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=shopware', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== All Products in Shopware ===\n\n";

// Get language info
$stmt = $pdo->query("SELECT HEX(id) as id, name FROM language");
$langs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Available languages:\n";
foreach ($langs as $lang) {
    echo "  " . $lang['id'] . ": " . $lang['name'] . "\n";
}
echo "\n";

// Count products
$stmt = $pdo->query("SELECT COUNT(*) as c FROM product");
$count = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total products in product table: " . $count['c'] . "\n";

$stmt = $pdo->query("SELECT COUNT(*) as c FROM product WHERE parent_id IS NULL");
$count = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Products with parent_id IS NULL: " . $count['c'] . "\n";

$stmt = $pdo->query("SELECT COUNT(*) as c FROM product WHERE parent_id IS NOT NULL");
$count = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Products with parent_id (variants): " . $count['c'] . "\n\n";

// Get first language
$stmt = $pdo->query("SELECT id FROM language LIMIT 1");
$firstLang = $stmt->fetch(PDO::FETCH_ASSOC);
$langId = $firstLang['id'];
echo "Using first language ID for query\n\n";

// Get all products with their names (no parent filter)
$stmt = $pdo->query("
    SELECT
        LOWER(HEX(p.id)) as product_id,
        p.product_number,
        pt.name,
        pt.custom_fields,
        LOWER(HEX(p.parent_id)) as parent_id
    FROM product p
    LEFT JOIN product_translation pt ON p.id = pt.product_id
    ORDER BY pt.name
    LIMIT 200
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total products (parent only): " . count($products) . "\n\n";

// Look for Snigel-related products by various patterns
$snigelPatterns = ['snigel', 'backpack', 'pouch', 'vest', 'belt', 'combat', 'tactical', 'specialist', 'mission'];

echo "--- All Products ---\n";
foreach ($products as $p) {
    $name = $p['name'];
    $number = $p['product_number'];
    $hasCustomFields = !empty($p['custom_fields']);
    $cfInfo = '';
    if ($hasCustomFields) {
        $cf = json_decode($p['custom_fields'], true);
        if ($cf) {
            $colorFields = [];
            foreach ($cf as $k => $v) {
                if (stripos($k, 'color') !== false || stripos($k, 'colour') !== false || stripos($k, 'snigel') !== false) {
                    $colorFields[$k] = is_array($v) ? json_encode($v) : $v;
                }
            }
            if (!empty($colorFields)) {
                $cfInfo = ' | Custom fields: ' . json_encode($colorFields);
            }
        }
    }
    echo "  [$number] $name$cfInfo\n";
}

// Also check for products with custom fields containing color info
echo "\n\n--- Products with Color-related Custom Fields ---\n";
$stmt = $pdo->query("
    SELECT
        p.product_number,
        pt.name,
        pt.custom_fields
    FROM product p
    JOIN product_translation pt ON p.id = pt.product_id
    WHERE pt.language_id = (SELECT id FROM language WHERE locale_id = (SELECT id FROM locale WHERE code = 'de-DE'))
    AND p.parent_id IS NULL
    AND pt.custom_fields IS NOT NULL
    AND pt.custom_fields != '{}'
    AND pt.custom_fields LIKE '%color%'
");

$colorProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($colorProducts) . " products with color custom fields\n";
foreach ($colorProducts as $p) {
    echo "  [{$p['product_number']}] {$p['name']}\n";
    echo "    Custom fields: {$p['custom_fields']}\n\n";
}

// Check for snigel custom fields specifically
echo "\n--- Products with Snigel Custom Fields ---\n";
$stmt = $pdo->query("
    SELECT
        p.product_number,
        pt.name,
        pt.custom_fields
    FROM product p
    JOIN product_translation pt ON p.id = pt.product_id
    WHERE pt.language_id = (SELECT id FROM language WHERE locale_id = (SELECT id FROM locale WHERE code = 'de-DE'))
    AND p.parent_id IS NULL
    AND pt.custom_fields IS NOT NULL
    AND pt.custom_fields LIKE '%snigel%'
");

$snigelProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($snigelProducts) . " products with snigel in custom fields\n";
foreach ($snigelProducts as $p) {
    echo "  [{$p['product_number']}] {$p['name']}\n";
    echo "    Custom fields: " . substr($p['custom_fields'], 0, 200) . "...\n\n";
}
