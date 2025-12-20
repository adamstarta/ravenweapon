<?php
/**
 * Check and fix SEO URL template configuration
 */

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

echo "=== SEO URL Template Configuration ===\n\n";

// Check current templates
$query = "SELECT route_name, template FROM seo_url_template";
$stmt = $pdo->query($query);
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($templates as $t) {
    echo "Route: {$t['route_name']}\n";
    echo "Template: {$t['template']}\n\n";
}

// Check how many products have SEO URLs
echo "=== SEO URL Statistics ===\n\n";

$query = "
SELECT COUNT(DISTINCT foreign_key) as count
FROM seo_url
WHERE route_name = 'frontend.detail.page'
AND is_deleted = 0
";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Products with SEO URLs: {$row['count']}\n";

$query = "
SELECT COUNT(DISTINCT id) as count
FROM product
WHERE parent_id IS NULL
";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total products: {$row['count']}\n";

// Check a sample SEO URL
echo "\n=== Sample SEO URLs ===\n\n";
$query = "
SELECT
    seo_path_info,
    is_canonical,
    LOWER(HEX(foreign_key)) as product_id
FROM seo_url
WHERE route_name = 'frontend.detail.page'
AND is_deleted = 0
AND is_canonical = 1
LIMIT 10
";
$stmt = $pdo->query($query);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    echo "- {$row['seo_path_info']}\n";
}

echo "\nDone!\n";
