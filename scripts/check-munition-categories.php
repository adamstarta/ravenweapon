<?php
/**
 * Check Munition category structure
 */

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=shopware', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Munition Category Structure ===\n\n";

// Get Munition category ID
$query = "
SELECT
    LOWER(HEX(c.id)) as id,
    ct.name,
    c.level,
    c.child_count
FROM category c
JOIN category_translation ct ON c.id = ct.category_id
WHERE ct.name = 'Munition'
LIMIT 1
";

$stmt = $pdo->query($query);
$munition = $stmt->fetch(PDO::FETCH_ASSOC);

if ($munition) {
    echo "Munition Category: {$munition['name']} (ID: {$munition['id']}, Level: {$munition['level']}, Children: {$munition['child_count']})\n\n";

    // Get subcategories under Munition
    $subQuery = "
    SELECT
        LOWER(HEX(c.id)) as id,
        ct.name,
        c.level,
        c.child_count
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE c.parent_id = UNHEX('{$munition['id']}')
    ORDER BY ct.name
    ";

    $subStmt = $pdo->query($subQuery);
    $subs = $subStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Subcategories under Munition:\n";
    foreach ($subs as $sub) {
        echo "  - {$sub['name']} (ID: {$sub['id']}, Children: {$sub['child_count']})\n";
    }

    if (empty($subs)) {
        echo "  (none)\n";
    }
}

echo "\n=== Products with problematic breadcrumbs ===\n\n";

// Check which products are assigned to which categories
$productQuery = "
SELECT
    LOWER(HEX(p.id)) as product_id,
    pt.name as product_name,
    LOWER(HEX(pc.category_id)) as category_id,
    ct.name as category_name
FROM product p
JOIN product_translation pt ON p.id = pt.product_id
JOIN product_category pc ON p.id = pc.product_id
JOIN category_translation ct ON pc.category_id = ct.category_id
WHERE pt.name LIKE '%223 Remington%' OR pt.name LIKE '%300 AAC%'
ORDER BY pt.name, ct.name
";

$prodStmt = $pdo->query($productQuery);
$products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

$currentProduct = '';
foreach ($products as $prod) {
    if ($currentProduct !== $prod['product_name']) {
        echo "\nProduct: {$prod['product_name']}\n";
        echo "  Categories:\n";
        $currentProduct = $prod['product_name'];
    }
    echo "    - {$prod['category_name']} ({$prod['category_id']})\n";
}

echo "\n=== SEO Main Category for these products ===\n\n";

// Check what's the main SEO category
$seoQuery = "
SELECT
    LOWER(HEX(psc.product_id)) as product_id,
    pt.name as product_name,
    LOWER(HEX(psc.category_id)) as seo_category_id,
    ct.name as seo_category_name
FROM product_category_tree psc
JOIN product_translation pt ON psc.product_id = pt.product_id
JOIN category_translation ct ON psc.category_id = ct.category_id
WHERE pt.name LIKE '%223 Remington%' OR pt.name LIKE '%300 AAC%'
ORDER BY pt.name
";

try {
    $seoStmt = $pdo->query($seoQuery);
    $seoCats = $seoStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($seoCats as $seo) {
        echo "Product: {$seo['product_name']}\n";
        echo "  SEO Category: {$seo['seo_category_name']} ({$seo['seo_category_id']})\n\n";
    }
} catch (Exception $e) {
    echo "Could not query product_category_tree: " . $e->getMessage() . "\n";
}
