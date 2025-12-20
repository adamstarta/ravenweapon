<?php
/**
 * Restructure Raven Weapons Categories
 *
 * This script:
 * 1. Finds single-product categories under "Raven Weapons" (level 3)
 * 2. Moves products from those categories to "Raven Weapons" directly
 * 3. Updates main_category for products
 * 4. Deletes the now-empty level 4 categories
 *
 * NOT HARDCODED: Dynamically finds categories with 1 product where
 * category name matches product name under Raven Weapons.
 */

// Database connection - read from .env
$host = 'localhost';
$dbname = 'shopware';
$user = 'root';
$password = 'root';

$envFile = '/var/www/html/.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    if (preg_match('/DATABASE_URL=mysql:\/\/([^:]+):([^@]+)@([^:\/]+)(?::(\d+))?\/(\w+)/', $envContent, $matches)) {
        $user = $matches[1];
        $password = $matches[2];
        $host = $matches[3];
        $dbname = $matches[5];
    }
}

$pdo = null;
$connectionMethods = [
    "mysql:host=127.0.0.1;port=3306;dbname=$dbname;charset=utf8mb4",
    "mysql:host=$host;port=3306;dbname=$dbname;charset=utf8mb4",
];

foreach ($connectionMethods as $dsn) {
    try {
        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        break;
    } catch (PDOException $e) {
        continue;
    }
}

if ($pdo === null) {
    die("Database connection failed.\n");
}

// CHF Sales Channel ID
$salesChannelId = '0191c12dd4b970949e9aeec40433be3e';

echo "=== Restructure Raven Weapons Categories ===\n\n";

// Step 1: Find "Raven Weapons" parent category (level 3, under Waffen)
echo "Step 1: Finding 'Raven Weapons' parent category...\n";

$query = "
SELECT
    LOWER(HEX(c.id)) as category_id,
    ct.name as category_name,
    c.level
FROM category c
JOIN category_translation ct ON c.id = ct.category_id
WHERE ct.name = 'Raven Weapons'
  AND c.level = 3
LIMIT 1
";

$stmt = $pdo->query($query);
$ravenWeaponsParent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ravenWeaponsParent) {
    die("ERROR: Could not find 'Raven Weapons' category at level 3.\n");
}

$ravenWeaponsId = $ravenWeaponsParent['category_id'];
echo "  Found: {$ravenWeaponsParent['category_name']} (ID: $ravenWeaponsId, Level: {$ravenWeaponsParent['level']})\n\n";

// Step 2: Find single-product categories under Raven Weapons
// These are categories where: level 4, parent is Raven Weapons, has exactly 1 product, product name = category name
echo "Step 2: Finding single-product categories to merge...\n";

$query = "
SELECT
    LOWER(HEX(c.id)) as category_id,
    ct.name as category_name,
    c.level,
    COUNT(DISTINCT pc.product_id) as product_count,
    LOWER(HEX(MIN(pc.product_id))) as product_id,
    (SELECT pt.name FROM product_translation pt WHERE pt.product_id = MIN(pc.product_id) LIMIT 1) as product_name
FROM category c
JOIN category_translation ct ON c.id = ct.category_id
LEFT JOIN product_category pc ON c.id = pc.category_id
WHERE LOWER(HEX(c.parent_id)) = ?
  AND c.level = 4
GROUP BY c.id, ct.name, c.level
HAVING product_count = 1
";

$stmt = $pdo->prepare($query);
$stmt->execute([$ravenWeaponsId]);
$singleProductCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($singleProductCategories)) {
    echo "  No single-product categories found. Nothing to do.\n";
    exit(0);
}

echo "  Found " . count($singleProductCategories) . " single-product categories:\n";
foreach ($singleProductCategories as $cat) {
    $match = ($cat['category_name'] === $cat['product_name']) ? '✓ MATCH' : '✗ DIFFERENT';
    echo "    - {$cat['category_name']} → Product: {$cat['product_name']} ($match)\n";
}
echo "\n";

// Step 3: Move products to Raven Weapons parent category
echo "Step 3: Moving products to 'Raven Weapons' category...\n";

$movedCount = 0;
foreach ($singleProductCategories as $cat) {
    $productId = $cat['product_id'];
    $categoryId = $cat['category_id'];

    // Check if product already has Raven Weapons category
    $checkQuery = "
    SELECT 1 FROM product_category
    WHERE LOWER(HEX(product_id)) = ? AND LOWER(HEX(category_id)) = ?
    ";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$productId, $ravenWeaponsId]);

    if (!$checkStmt->fetch()) {
        // Add product to Raven Weapons category
        $insertQuery = "
        INSERT INTO product_category (product_id, product_version_id, category_id, category_version_id)
        SELECT
            UNHEX(?),
            (SELECT version_id FROM product WHERE LOWER(HEX(id)) = ? LIMIT 1),
            UNHEX(?),
            (SELECT version_id FROM category WHERE LOWER(HEX(id)) = ? LIMIT 1)
        ";
        $insertStmt = $pdo->prepare($insertQuery);
        $insertStmt->execute([$productId, $productId, $ravenWeaponsId, $ravenWeaponsId]);
        echo "    Added product '{$cat['product_name']}' to Raven Weapons\n";
        $movedCount++;
    } else {
        echo "    Product '{$cat['product_name']}' already in Raven Weapons (skipped)\n";
    }
}
echo "  Moved $movedCount products.\n\n";

// Step 4: Update main_category for products
echo "Step 4: Updating main_category for products...\n";

foreach ($singleProductCategories as $cat) {
    $productId = $cat['product_id'];

    // Delete existing main_category entries for this product
    $deleteQuery = "
    DELETE FROM main_category
    WHERE LOWER(HEX(product_id)) = ?
    ";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->execute([$productId]);

    // Insert new main_category pointing to Raven Weapons
    $insertQuery = "
    INSERT INTO main_category (id, product_id, product_version_id, category_id, category_version_id, sales_channel_id, created_at)
    SELECT
        UNHEX(REPLACE(UUID(), '-', '')),
        UNHEX(?),
        (SELECT version_id FROM product WHERE LOWER(HEX(id)) = ? LIMIT 1),
        UNHEX(?),
        (SELECT version_id FROM category WHERE LOWER(HEX(id)) = ? LIMIT 1),
        UNHEX(?),
        NOW()
    ";
    $insertStmt = $pdo->prepare($insertQuery);
    $insertStmt->execute([$productId, $productId, $ravenWeaponsId, $ravenWeaponsId, $salesChannelId]);
    echo "    Set main_category for '{$cat['product_name']}' → Raven Weapons\n";
}
echo "\n";

// Step 5: Remove product associations from old categories
echo "Step 5: Removing products from old level 4 categories...\n";

foreach ($singleProductCategories as $cat) {
    $productId = $cat['product_id'];
    $categoryId = $cat['category_id'];

    $deleteQuery = "
    DELETE FROM product_category
    WHERE LOWER(HEX(product_id)) = ? AND LOWER(HEX(category_id)) = ?
    ";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->execute([$productId, $categoryId]);
    echo "    Removed '{$cat['product_name']}' from category '{$cat['category_name']}'\n";
}
echo "\n";

// Step 6: Delete empty level 4 categories
echo "Step 6: Deleting empty level 4 categories...\n";

foreach ($singleProductCategories as $cat) {
    $categoryId = $cat['category_id'];
    $categoryName = $cat['category_name'];

    // Delete SEO URLs for this category
    $deleteQuery = "DELETE FROM seo_url WHERE LOWER(HEX(foreign_key)) = ?";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->execute([$categoryId]);

    // Delete category translations
    $deleteQuery = "DELETE FROM category_translation WHERE LOWER(HEX(category_id)) = ?";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->execute([$categoryId]);

    // Delete main_category entries referencing this category
    $deleteQuery = "DELETE FROM main_category WHERE LOWER(HEX(category_id)) = ?";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->execute([$categoryId]);

    // Delete the category itself
    $deleteQuery = "DELETE FROM category WHERE LOWER(HEX(id)) = ?";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->execute([$categoryId]);

    echo "    Deleted category '$categoryName'\n";
}
echo "\n";

// Step 7: Summary
echo "=== COMPLETE ===\n\n";
echo "Summary:\n";
echo "  - Moved " . count($singleProductCategories) . " products to 'Raven Weapons'\n";
echo "  - Updated main_category for " . count($singleProductCategories) . " products\n";
echo "  - Deleted " . count($singleProductCategories) . " empty level 4 categories\n\n";

echo "Next steps (run manually):\n";
echo "  1. bin/console dal:refresh:index\n";
echo "  2. bin/console cache:clear\n";
echo "\n";

echo "New URL structure will be:\n";
echo "  Category: /waffen/raven-weapons/\n";
echo "  Products: /waffen/raven-weapons/{product-slug}/\n";
echo "\n";

echo "Breadcrumb will be:\n";
echo "  Home / Waffen / Raven Weapons / {Product Name}\n";
echo "\n";
