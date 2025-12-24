<?php
/**
 * Diagnose why products aren't showing in category listings
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = 'localhost';
$dbname = 'shopware';
$user = 'root';
$password = 'root';

$envFile = '/var/www/html/.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    if (preg_match('/DATABASE_URL=mysql:\/\/([^:]+):([^@]+)@([^\/:]+)(?::(\d+))?\/(\\w+)/', $envContent, $matches)) {
        $user = $matches[1];
        $password = $matches[2];
        $host = $matches[3];
        $dbname = $matches[5];
    }
}

$pdo = null;
try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to database\n\n";
} catch (PDOException $e) {
    die("Could not connect: " . $e->getMessage() . "\n");
}

echo "=== DIAGNOSING DIENSTLEISTUNGEN CATEGORY PRODUCTS ===\n\n";

// 1. Find the Basic-Kurse category
echo "1. CHECKING CATEGORIES\n";
echo str_repeat("-", 50) . "\n";

$stmt = $pdo->query("
    SELECT
        LOWER(HEX(c.id)) as id,
        ct.name,
        c.active,
        c.visible,
        LOWER(HEX(c.product_stream_id)) as product_stream_id,
        c.type,
        c.product_assignment_type,
        c.level
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE ct.name IN ('Dienstleistungen', 'Schiesskurse', 'Basic-Kurse', 'Privatunterricht')
    ORDER BY c.level
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($categories as $cat) {
    echo "Category: {$cat['name']}\n";
    echo "  ID: {$cat['id']}\n";
    echo "  Active: " . ($cat['active'] ? 'Yes' : 'No') . "\n";
    echo "  Visible: " . ($cat['visible'] ? 'Yes' : 'No') . "\n";
    echo "  Type: " . ($cat['type'] ?: 'null') . "\n";
    echo "  Product Assignment Type: " . ($cat['product_assignment_type'] ?: 'null') . "\n";
    echo "  Product Stream ID: " . ($cat['product_stream_id'] ?: 'null') . "\n";
    echo "\n";
}

// 2. Check product-category assignments
echo "\n2. CHECKING PRODUCT-CATEGORY RELATIONSHIPS\n";
echo str_repeat("-", 50) . "\n";

$stmt = $pdo->query("
    SELECT
        p.product_number,
        pt.name as product_name,
        ct.name as category_name,
        LOWER(HEX(pc.category_id)) as category_id,
        LOWER(HEX(pc.product_id)) as product_id
    FROM product_category pc
    JOIN product p ON pc.product_id = p.id
    JOIN product_translation pt ON p.id = pt.product_id
    JOIN category c ON pc.category_id = c.id
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE p.product_number IN ('Basic-Kurs', 'Basic-Kurs-II', 'Basic-Kurs-III', 'Basic-Kurs-IV', 'Instruktor-2-H')
    ORDER BY p.product_number, ct.name
");
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($assignments)) {
    echo "  NO PRODUCT-CATEGORY ASSIGNMENTS FOUND!\n";
    echo "  This is likely the issue - products need to be in product_category table\n";
} else {
    foreach ($assignments as $a) {
        echo "Product: {$a['product_number']} → Category: {$a['category_name']}\n";
    }
}

// 3. Check product status
echo "\n3. CHECKING PRODUCT STATUS\n";
echo str_repeat("-", 50) . "\n";

$stmt = $pdo->query("
    SELECT
        p.product_number,
        pt.name,
        p.active,
        LOWER(HEX(p.id)) as product_id
    FROM product p
    JOIN product_translation pt ON p.id = pt.product_id
    WHERE p.product_number IN ('Basic-Kurs', 'Basic-Kurs-II', 'Basic-Kurs-III', 'Basic-Kurs-IV', 'Instruktor-2-H')
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($products as $prod) {
    echo "Product: {$prod['product_number']}\n";
    echo "  Name: {$prod['name']}\n";
    echo "  Active: " . ($prod['active'] ? 'Yes' : 'No') . "\n";
    echo "  ID: {$prod['product_id']}\n";
    echo "\n";
}

// 4. Check product visibility
echo "4. CHECKING PRODUCT VISIBILITY\n";
echo str_repeat("-", 50) . "\n";

$stmt = $pdo->query("
    SELECT
        p.product_number,
        pv.visibility
    FROM product p
    JOIN product_visibility pv ON p.id = pv.product_id
    WHERE p.product_number IN ('Basic-Kurs', 'Basic-Kurs-II', 'Basic-Kurs-III', 'Basic-Kurs-IV', 'Instruktor-2-H')
");
$visibilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($visibilities as $v) {
    $vis = $v['visibility'];
    $visText = ($vis == 30) ? 'All (search + listing)' : (($vis == 20) ? 'Search only' : (($vis == 10) ? 'Listing link only' : 'Unknown'));
    echo "Product: {$v['product_number']} - Visibility: {$vis} ({$visText})\n";
}

// 5. Check if products are indexed (product_search_keyword)
echo "\n5. CHECKING PRODUCT INDEX\n";
echo str_repeat("-", 50) . "\n";

$stmt = $pdo->query("
    SELECT
        p.product_number,
        COUNT(psk.id) as keyword_count
    FROM product p
    LEFT JOIN product_search_keyword psk ON p.id = psk.product_id
    WHERE p.product_number IN ('Basic-Kurs', 'Basic-Kurs-II', 'Basic-Kurs-III', 'Basic-Kurs-IV', 'Instruktor-2-H')
    GROUP BY p.product_number
");
$indexed = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($indexed as $i) {
    echo "Product: {$i['product_number']} - Search keywords: {$i['keyword_count']}\n";
}

// 6. Get Basic-Kurse category ID and check for product_category entries
echo "\n6. CHECKING BASIC-KURSE CATEGORY PRODUCTS\n";
echo str_repeat("-", 50) . "\n";

$stmt = $pdo->query("
    SELECT LOWER(HEX(c.id)) as id
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE ct.name = 'Basic-Kurse'
    LIMIT 1
");
$basicKurse = $stmt->fetch(PDO::FETCH_ASSOC);

if ($basicKurse) {
    $categoryId = $basicKurse['id'];
    echo "Basic-Kurse category ID: $categoryId\n";

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM product_category
        WHERE LOWER(HEX(category_id)) = ?
    ");
    $stmt->execute([$categoryId]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Products directly in Basic-Kurse: {$count['count']}\n";
}

echo "\n=== DIAGNOSIS COMPLETE ===\n";
