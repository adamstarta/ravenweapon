<?php
/**
 * Fix main_category for ALL products
 * Sets the deepest category as main_category for each product
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

echo "=== Fix main_category for ALL products ===\n\n";

// Step 1: Get all products with their categories
echo "Step 1: Finding all products and their categories...\n";

$query = "
SELECT
    LOWER(HEX(p.id)) as product_id,
    pt.name as product_name,
    LOWER(HEX(pc.category_id)) as category_id,
    c.level as category_level,
    ct.name as category_name
FROM product p
JOIN product_translation pt ON p.id = pt.product_id AND pt.name IS NOT NULL
JOIN product_category pc ON p.id = pc.product_id
JOIN category c ON pc.category_id = c.id
JOIN category_translation ct ON c.id = ct.category_id AND ct.name IS NOT NULL
WHERE p.parent_id IS NULL
ORDER BY p.id, c.level DESC
";

$stmt = $pdo->query($query);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by product - take the deepest category for each
$productCategories = [];
foreach ($rows as $row) {
    $productId = $row['product_id'];
    if (!isset($productCategories[$productId])) {
        $productCategories[$productId] = [
            'name' => $row['product_name'],
            'category_id' => $row['category_id'],
            'category_name' => $row['category_name'],
            'category_level' => $row['category_level']
        ];
    }
}

$totalProducts = count($productCategories);
echo "Found $totalProducts products with categories.\n\n";

// Step 2: Check existing main_category entries
echo "Step 2: Checking existing main_category entries...\n";

$query = "
SELECT LOWER(HEX(product_id)) as product_id
FROM main_category
WHERE LOWER(HEX(sales_channel_id)) = '$salesChannelId'
";
$stmt = $pdo->query($query);
$existingMainCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
$existingSet = array_flip($existingMainCategories);

$existingCount = count($existingMainCategories);
echo "Found $existingCount existing main_category entries.\n\n";

// Step 3: Insert missing main_category entries
echo "Step 3: Inserting/updating main_category entries...\n\n";

$inserted = 0;
$updated = 0;
$skipped = 0;

foreach ($productCategories as $productId => $data) {
    $categoryId = $data['category_id'];
    $productName = $data['name'];
    $categoryName = $data['category_name'];

    if (isset($existingSet[$productId])) {
        // Update existing entry
        $updateQuery = "
        UPDATE main_category
        SET category_id = UNHEX(?), updated_at = NOW()
        WHERE LOWER(HEX(product_id)) = ?
        AND LOWER(HEX(sales_channel_id)) = ?
        ";
        try {
            $stmt = $pdo->prepare($updateQuery);
            $stmt->execute([strtoupper($categoryId), $productId, $salesChannelId]);
            $updated++;
            echo "  Updated: $productName -> $categoryName\n";
        } catch (PDOException $e) {
            echo "  ERROR updating $productName: " . $e->getMessage() . "\n";
        }
    } else {
        // Insert new entry
        $insertQuery = "
        INSERT INTO main_category (id, product_id, product_version_id, category_id, category_version_id, sales_channel_id, created_at)
        VALUES (
            UNHEX(REPLACE(UUID(), '-', '')),
            UNHEX(?),
            UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425'),
            UNHEX(?),
            UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425'),
            UNHEX(?),
            NOW()
        )
        ";
        try {
            $stmt = $pdo->prepare($insertQuery);
            $stmt->execute([strtoupper($productId), strtoupper($categoryId), strtoupper($salesChannelId)]);
            $inserted++;
            echo "  Inserted: $productName -> $categoryName\n";
        } catch (PDOException $e) {
            echo "  ERROR inserting $productName: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== Summary ===\n";
echo "Total products: $totalProducts\n";
echo "Inserted: $inserted\n";
echo "Updated: $updated\n";
echo "Total with main_category: " . ($inserted + $updated + (count($existingSet) - $updated)) . "\n";

// Step 4: Find products without any categories
echo "\n=== Products without categories ===\n";

$query = "
SELECT
    LOWER(HEX(p.id)) as product_id,
    pt.name as product_name
FROM product p
JOIN product_translation pt ON p.id = pt.product_id AND pt.name IS NOT NULL
LEFT JOIN product_category pc ON p.id = pc.product_id
WHERE p.parent_id IS NULL
AND pc.product_id IS NULL
";

$stmt = $pdo->query($query);
$orphans = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($orphans) > 0) {
    echo "WARNING: Found " . count($orphans) . " products without ANY category:\n";
    foreach ($orphans as $orphan) {
        echo "  - {$orphan['product_name']} (ID: {$orphan['product_id']})\n";
    }
} else {
    echo "All products have at least one category assigned.\n";
}

echo "\nDone!\n";
