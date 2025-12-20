<?php
/**
 * Debug seoCategory breadcrumb data
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

echo "=== Debug seoCategory Breadcrumb ===\n\n";

// Check Munition category's breadcrumb
$query = "
SELECT
    LOWER(HEX(c.id)) as id,
    ct.name,
    ct.breadcrumb,
    c.level,
    c.path
FROM category c
JOIN category_translation ct ON c.id = ct.category_id
WHERE ct.name = 'Munition'
LIMIT 1
";
$stmt = $pdo->query($query);
$munition = $stmt->fetch(PDO::FETCH_ASSOC);

if ($munition) {
    echo "Munition Category:\n";
    echo "  ID: {$munition['id']}\n";
    echo "  Name: {$munition['name']}\n";
    echo "  Level: {$munition['level']}\n";
    echo "  Path: {$munition['path']}\n";
    echo "  Breadcrumb (raw): " . ($munition['breadcrumb'] ?: 'NULL') . "\n";

    if ($munition['breadcrumb']) {
        $breadcrumb = json_decode($munition['breadcrumb'], true);
        echo "  Breadcrumb (decoded):\n";
        if ($breadcrumb) {
            foreach ($breadcrumb as $key => $value) {
                echo "    [$key] => $value\n";
            }
        }
    }
}

echo "\n";

// Also check what seoUrls exist for Munition
echo "Munition SEO URLs:\n";
$munitionId = $munition['id'];
$query = "
SELECT
    seo_path_info,
    is_canonical,
    is_deleted
FROM seo_url
WHERE LOWER(HEX(foreign_key)) = '$munitionId'
AND route_name = 'frontend.navigation.page'
AND is_deleted = 0
ORDER BY is_canonical DESC
";
$stmt = $pdo->query($query);
$seoUrls = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($seoUrls as $url) {
    $canonical = $url['is_canonical'] ? '[CANONICAL]' : '';
    echo "  - {$url['seo_path_info']} $canonical\n";
}
if (count($seoUrls) == 0) {
    echo "  (none)\n";
}

echo "\n";
