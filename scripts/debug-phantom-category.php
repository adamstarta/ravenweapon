<?php
/**
 * Debug phantom category issue
 */

// Database connection
$host = getenv('DATABASE_HOST') ?: 'localhost';
$dbname = getenv('DATABASE_NAME') ?: 'shopware';
$user = getenv('DATABASE_USER') ?: 'shopware';
$password = getenv('DATABASE_PASSWORD') ?: 'shopware';

$envFile = '/var/www/html/.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    if (preg_match('/DATABASE_URL=mysql:\/\/([^:]+):([^@]+)@([^:\\/]+)(?::(\\d+))?\\/(\\w+)/', $envContent, $matches)) {
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

$productId = 'dead78ac80c804734cbe6f316e0a2dee';

echo "=== Debug Phantom Category Issue ===\n\n";

// 1. Check what main_category is set for this product
echo "1. Main Category for 223 Remington product:\n";
$query = "
SELECT
    LOWER(HEX(mc.product_id)) as product_id,
    LOWER(HEX(mc.category_id)) as category_id,
    ct.name as category_name,
    c.level,
    c.path
FROM main_category mc
JOIN category c ON mc.category_id = c.id
JOIN category_translation ct ON c.id = ct.category_id
WHERE LOWER(HEX(mc.product_id)) = '$productId'
LIMIT 1
";
$stmt = $pdo->query($query);
$mainCat = $stmt->fetch(PDO::FETCH_ASSOC);
if ($mainCat) {
    echo "  Category: {$mainCat['category_name']} (ID: {$mainCat['category_id']})\n";
    echo "  Level: {$mainCat['level']}\n";
    echo "  Path: {$mainCat['path']}\n";
} else {
    echo "  No main_category found!\n";
}

// 2. Check if there's a category named "223 Remington..."
echo "\n2. Searching for categories with '223' in name:\n";
$query = "
SELECT
    LOWER(HEX(c.id)) as id,
    ct.name,
    c.level,
    c.path,
    c.active
FROM category c
JOIN category_translation ct ON c.id = ct.category_id
WHERE ct.name LIKE '%223%'
";
$stmt = $pdo->query($query);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($categories) > 0) {
    foreach ($categories as $cat) {
        echo "  - {$cat['name']} (ID: {$cat['id']}, Level: {$cat['level']}, Active: {$cat['active']})\n";
        echo "    Path: {$cat['path']}\n";
    }
} else {
    echo "  No categories found with '223' in name\n";
}

// 3. Check Munition category and its children
echo "\n3. Munition category structure:\n";
$query = "
SELECT
    LOWER(HEX(c.id)) as id,
    ct.name,
    c.level,
    c.path,
    c.child_count,
    LOWER(HEX(c.parent_id)) as parent_id
FROM category c
JOIN category_translation ct ON c.id = ct.category_id
WHERE ct.name = 'Munition'
LIMIT 1
";
$stmt = $pdo->query($query);
$munition = $stmt->fetch(PDO::FETCH_ASSOC);
if ($munition) {
    echo "  Munition ID: {$munition['id']}\n";
    echo "  Level: {$munition['level']}\n";
    echo "  Path: {$munition['path']}\n";
    echo "  Child Count: {$munition['child_count']}\n";

    // Check for children
    $munitionId = $munition['id'];
    $query = "
    SELECT
        LOWER(HEX(c.id)) as id,
        ct.name,
        c.level,
        c.active
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE LOWER(HEX(c.parent_id)) = '$munitionId'
    ";
    $stmt = $pdo->query($query);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "  Children:\n";
    if (count($children) > 0) {
        foreach ($children as $child) {
            echo "    - {$child['name']} (ID: {$child['id']}, Level: {$child['level']}, Active: {$child['active']})\n";
        }
    } else {
        echo "    (none)\n";
    }
}

// 4. Check SEO URLs with the phantom path
echo "\n4. SEO URLs containing '223-remington':\n";
$query = "
SELECT
    LOWER(HEX(id)) as id,
    route_name,
    seo_path_info,
    is_canonical,
    is_deleted,
    LOWER(HEX(foreign_key)) as foreign_key
FROM seo_url
WHERE seo_path_info LIKE '%223-remington%'
AND is_deleted = 0
ORDER BY seo_path_info
";
$stmt = $pdo->query($query);
$seoUrls = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($seoUrls) > 0) {
    foreach ($seoUrls as $url) {
        echo "  - {$url['seo_path_info']}\n";
        echo "    Route: {$url['route_name']}, Canonical: {$url['is_canonical']}\n";
        echo "    Foreign Key: {$url['foreign_key']}\n";
    }
} else {
    echo "  No SEO URLs found\n";
}

// 5. Check product's seo_category
echo "\n5. Product's SEO category in product table:\n";
$query = "
SELECT
    LOWER(HEX(p.id)) as product_id,
    pt.name as product_name,
    p.category_tree
FROM product p
JOIN product_translation pt ON p.id = pt.product_id
WHERE LOWER(HEX(p.id)) = '$productId'
LIMIT 1
";
$stmt = $pdo->query($query);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if ($product) {
    echo "  Product: {$product['product_name']}\n";
    echo "  Category Tree: {$product['category_tree']}\n";
}

echo "\n";
