<?php
/**
 * Diagnose category CMS layout and product_category_tree
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

echo "=== CHECKING CMS LAYOUT AND PRODUCT CATEGORY TREE ===\n\n";

// 1. Check CMS layout assignment
echo "1. CMS LAYOUT ASSIGNMENT\n";
echo str_repeat("-", 50) . "\n";

$stmt = $pdo->query("
    SELECT
        ct.name as category_name,
        LOWER(HEX(c.cms_page_id)) as cms_page_id,
        cpt.name as cms_page_name
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    LEFT JOIN cms_page cp ON c.cms_page_id = cp.id
    LEFT JOIN cms_page_translation cpt ON cp.id = cpt.cms_page_id
    WHERE ct.name IN ('Dienstleistungen', 'Schiesskurse', 'Basic-Kurse', 'Privatunterricht')
");
$layouts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($layouts as $l) {
    echo "Category: {$l['category_name']}\n";
    echo "  CMS Page ID: " . ($l['cms_page_id'] ?: 'NULL - NO CMS PAGE!') . "\n";
    echo "  CMS Page Name: " . ($l['cms_page_name'] ?: 'N/A') . "\n";
    echo "\n";
}

// 2. Check product_category_tree
echo "2. PRODUCT_CATEGORY_TREE TABLE\n";
echo str_repeat("-", 50) . "\n";

$stmt = $pdo->query("
    SELECT
        p.product_number,
        ct.name as category_name
    FROM product_category_tree pct
    JOIN product p ON pct.product_id = p.id
    JOIN category c ON pct.category_id = c.id
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE p.product_number IN ('Basic-Kurs', 'Basic-Kurs-II', 'Basic-Kurs-III', 'Basic-Kurs-IV', 'Instruktor-2-H')
    ORDER BY p.product_number, ct.name
");
$tree = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tree)) {
    echo "  NO ENTRIES IN product_category_tree!\n";
    echo "  This is likely the issue - Shopware uses this table for listing queries\n";
} else {
    foreach ($tree as $t) {
        echo "{$t['product_number']} → {$t['category_name']}\n";
    }
}

// 3. Compare with a working category
echo "\n3. COMPARING WITH WORKING CATEGORY (Ausrüstung)\n";
echo str_repeat("-", 50) . "\n";

$stmt = $pdo->query("
    SELECT
        ct.name as category_name,
        LOWER(HEX(c.cms_page_id)) as cms_page_id,
        cpt.name as cms_page_name
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    LEFT JOIN cms_page cp ON c.cms_page_id = cp.id
    LEFT JOIN cms_page_translation cpt ON cp.id = cpt.cms_page_id
    WHERE ct.name = 'Ausrüstung'
    LIMIT 1
");
$working = $stmt->fetch(PDO::FETCH_ASSOC);

if ($working) {
    echo "Category: {$working['category_name']}\n";
    echo "  CMS Page ID: " . ($working['cms_page_id'] ?: 'NULL') . "\n";
    echo "  CMS Page Name: " . ($working['cms_page_name'] ?: 'N/A') . "\n";
}

// 4. Get the default listing CMS page ID
echo "\n4. FINDING DEFAULT PRODUCT LISTING CMS PAGE\n";
echo str_repeat("-", 50) . "\n";

$stmt = $pdo->query("
    SELECT
        LOWER(HEX(cp.id)) as id,
        cpt.name,
        cp.type
    FROM cms_page cp
    JOIN cms_page_translation cpt ON cp.id = cpt.cms_page_id
    WHERE cp.type = 'product_list'
    LIMIT 5
");
$cmsPages = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cmsPages)) {
    echo "No product_list CMS pages found.\n";
} else {
    foreach ($cmsPages as $page) {
        echo "ID: {$page['id']} - Name: {$page['name']} - Type: {$page['type']}\n";
    }
}

// 5. Check sales channel category configuration
echo "\n5. SALES CHANNEL NAVIGATION CATEGORY\n";
echo str_repeat("-", 50) . "\n";

$stmt = $pdo->query("
    SELECT
        sct.name as sales_channel_name,
        LOWER(HEX(sc.navigation_category_id)) as nav_category_id,
        ct.name as nav_category_name
    FROM sales_channel sc
    JOIN sales_channel_translation sct ON sc.id = sct.sales_channel_id
    JOIN category c ON sc.navigation_category_id = c.id
    JOIN category_translation ct ON c.id = ct.category_id
    LIMIT 1
");
$scNav = $stmt->fetch(PDO::FETCH_ASSOC);

if ($scNav) {
    echo "Sales Channel: {$scNav['sales_channel_name']}\n";
    echo "Navigation Root ID: {$scNav['nav_category_id']}\n";
    echo "Navigation Root Name: {$scNav['nav_category_name']}\n";
}

// 6. Check if Dienstleistungen is child of navigation root
echo "\n6. CHECKING DIENSTLEISTUNGEN PARENT CHAIN\n";
echo str_repeat("-", 50) . "\n";

$stmt = $pdo->query("
    SELECT
        ct.name,
        LOWER(HEX(c.id)) as id,
        LOWER(HEX(c.parent_id)) as parent_id,
        c.path,
        c.level
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE ct.name = 'Dienstleistungen'
    LIMIT 1
");
$dienst = $stmt->fetch(PDO::FETCH_ASSOC);

if ($dienst) {
    echo "Category: {$dienst['name']}\n";
    echo "ID: {$dienst['id']}\n";
    echo "Parent ID: {$dienst['parent_id']}\n";
    echo "Path: {$dienst['path']}\n";
    echo "Level: {$dienst['level']}\n";
}

echo "\n=== DIAGNOSIS COMPLETE ===\n";
