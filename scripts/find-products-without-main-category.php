<?php
/**
 * Find all products that don't have a main_category assigned
 */

// Database connection - try multiple methods
$host = getenv('DATABASE_HOST') ?: 'localhost';
$dbname = getenv('DATABASE_NAME') ?: 'shopware';
$user = getenv('DATABASE_USER') ?: 'shopware';
$password = getenv('DATABASE_PASSWORD') ?: 'shopware';

// Try to read from .env if available
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

echo "=== Products Without Main Category ===\n\n";

$query = "
SELECT
    LOWER(HEX(p.id)) as product_id,
    MAX(pt.name) as product_name,
    LOWER(HEX(pc.category_id)) as assigned_category_id,
    MAX(ct.name) as category_name
FROM product p
JOIN product_translation pt ON p.id = pt.product_id
JOIN product_category pc ON p.id = pc.product_id
LEFT JOIN category_translation ct ON pc.category_id = ct.category_id
LEFT JOIN main_category mc ON p.id = mc.product_id
WHERE mc.product_id IS NULL
AND p.parent_id IS NULL
GROUP BY p.id, pc.category_id
ORDER BY MAX(ct.name), MAX(pt.name)
";

$stmt = $pdo->query($query);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$byCategory = [];
foreach ($products as $p) {
    $cat = $p['category_name'] ?: 'UNCATEGORIZED';
    if (!isset($byCategory[$cat])) {
        $byCategory[$cat] = [];
    }
    // Only add if not already in this category (avoid duplicates from multiple translations)
    $found = false;
    foreach ($byCategory[$cat] as $existing) {
        if ($existing['product_id'] === $p['product_id']) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $byCategory[$cat][] = $p;
    }
}

$total = 0;
foreach ($byCategory as $cat => $prods) {
    echo "Category: $cat (" . count($prods) . " products)\n";
    foreach ($prods as $p) {
        echo "  - " . $p['product_name'] . "\n";
        echo "    Product ID: " . $p['product_id'] . "\n";
        echo "    Category ID: " . $p['assigned_category_id'] . "\n";
        $total++;
    }
    echo "\n";
}

echo "Total products without main_category: $total\n";
