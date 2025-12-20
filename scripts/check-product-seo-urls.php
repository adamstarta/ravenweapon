<?php
/**
 * Check SEO URLs for 223 Remington product (debug phantom breadcrumb)
 */

$user = 'root';
$password = 'root';
$dbname = 'shopware';

// Try multiple connection methods
$pdo = null;
$connectionMethods = [
    "mysql:host=localhost;dbname=$dbname;charset=utf8mb4",
    "mysql:host=127.0.0.1;port=3306;dbname=$dbname;charset=utf8mb4",
];

foreach ($connectionMethods as $dsn) {
    try {
        $pdo = new PDO($dsn, $user, $password);
        break;
    } catch (PDOException $e) {
        continue;
    }
}

if ($pdo === null) {
    die("Database connection failed.\n");
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$productId = 'dead78ac80c804734cbe6f316e0a2dee';

echo "=== SEO URLs for 223 Remington product ===\n\n";

$query = "
SELECT seo_path_info, is_canonical
FROM seo_url
WHERE LOWER(HEX(foreign_key)) = '$productId'
AND route_name = 'frontend.detail.page'
AND is_deleted = 0
ORDER BY is_canonical DESC
";

$stmt = $pdo->query($query);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) == 0) {
    echo "No SEO URLs found\n";
} else {
    foreach ($rows as $row) {
        $canonical = $row['is_canonical'] ? '[CANONICAL]' : '';
        echo "  - {$row['seo_path_info']} $canonical\n";
    }
}

echo "\n=== Main Category for this product ===\n\n";

$query = "
SELECT
    LOWER(HEX(mc.category_id)) as main_category_id,
    ct.name as category_name,
    ct.breadcrumb
FROM main_category mc
JOIN category_translation ct ON mc.category_id = ct.category_id
WHERE LOWER(HEX(mc.product_id)) = '$productId'
LIMIT 1
";

$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo "Main Category ID: {$row['main_category_id']}\n";
    echo "Main Category Name: {$row['category_name']}\n";
    echo "Breadcrumb: {$row['breadcrumb']}\n";
    if ($row['breadcrumb']) {
        $bc = json_decode($row['breadcrumb'], true);
        if ($bc) {
            echo "Breadcrumb decoded:\n";
            foreach ($bc as $k => $v) {
                echo "  [$k] => $v\n";
            }
        }
    }
} else {
    echo "No main_category found!\n";
}

echo "\n=== Product Category Assignments ===\n\n";

$query = "
SELECT
    LOWER(HEX(pc.category_id)) as category_id,
    ct.name as category_name,
    c.level,
    ct.breadcrumb
FROM product_category pc
JOIN category c ON pc.category_id = c.id
LEFT JOIN category_translation ct ON c.id = ct.category_id
WHERE LOWER(HEX(pc.product_id)) = '$productId'
ORDER BY c.level DESC
";

$stmt = $pdo->query($query);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) == 0) {
    echo "No category assignments found\n";
} else {
    foreach ($rows as $row) {
        echo "  - {$row['category_name']} (Level {$row['level']})\n";
        echo "    ID: {$row['category_id']}\n";
        echo "    Breadcrumb: {$row['breadcrumb']}\n";
    }
}

echo "\n=== Product table seoCategory data ===\n\n";

// Check if there's any seoCategory data stored on the product itself
$query = "
SELECT
    LOWER(HEX(p.id)) as product_id,
    pt.name as product_name,
    p.category_tree
FROM product p
JOIN product_translation pt ON p.id = pt.product_id AND pt.name IS NOT NULL
WHERE LOWER(HEX(p.id)) = '$productId'
LIMIT 1
";

$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo "Product: {$row['product_name']}\n";
    echo "Category Tree (raw): {$row['category_tree']}\n";
    if ($row['category_tree']) {
        $tree = json_decode($row['category_tree'], true);
        if ($tree) {
            echo "Category Tree decoded:\n";
            foreach ($tree as $catId) {
                echo "  - $catId\n";
            }
        }
    }
}

echo "\n=== All categories with '223' in breadcrumb ===\n\n";

$query = "
SELECT
    LOWER(HEX(c.id)) as category_id,
    ct.name,
    ct.breadcrumb,
    c.level
FROM category c
JOIN category_translation ct ON c.id = ct.category_id
WHERE ct.breadcrumb LIKE '%223%'
";

$stmt = $pdo->query($query);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) == 0) {
    echo "No categories found with '223' in breadcrumb\n";
} else {
    foreach ($rows as $row) {
        echo "  - {$row['name']} (Level {$row['level']})\n";
        echo "    ID: {$row['category_id']}\n";
        echo "    Breadcrumb: {$row['breadcrumb']}\n";
    }
}
