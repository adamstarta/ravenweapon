<?php
/**
 * Analyze category hierarchy to understand path/breadcrumb structure
 */

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

$languageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';

echo "=== Category Hierarchy Analysis ===\n\n";

// Get Waffen and its subcategories
$query = "
SELECT
    LOWER(HEX(c.id)) as category_id,
    ct.name,
    c.level,
    LOWER(HEX(c.parent_id)) as parent_id,
    c.path,
    ct.breadcrumb
FROM category c
JOIN category_translation ct ON c.id = ct.category_id
    AND LOWER(HEX(ct.language_id)) = '$languageId'
WHERE ct.name IN (
    'Waffen',
    'Caracal Lynx', 'LYNX SPORT', 'LYNX COMPACT', 'LYNX OPEN',
    'Raven Weapons', '.22 LR RAVEN', '.223 RAVEN', '300 AAC RAVEN', '7.62x39 RAVEN', '9mm RAVEN',
    'RAPAX', 'RX Tactical', 'RX Sport', 'RX Compact'
)
ORDER BY c.level, ct.name
";

$stmt = $pdo->query($query);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($categories) . " categories\n\n";

foreach ($categories as $cat) {
    echo "Name: {$cat['name']}\n";
    echo "  ID: {$cat['category_id']}\n";
    echo "  Level: {$cat['level']}\n";
    echo "  Parent ID: {$cat['parent_id']}\n";
    echo "  Path: {$cat['path']}\n";

    $breadcrumb = json_decode($cat['breadcrumb'], true);
    if ($breadcrumb) {
        echo "  Breadcrumb: " . implode(' > ', array_values($breadcrumb)) . "\n";
        echo "  Breadcrumb Order: ";
        $i = 0;
        foreach ($breadcrumb as $id => $name) {
            echo "[$i] $name ";
            $i++;
        }
        echo "\n";
    }
    echo "\n";
}

// Also check the current SEO URLs for these categories
echo "\n=== Current SEO URLs for these categories ===\n\n";

$salesChannelId = '0191c12dd4b970949e9aeec40433be3e';

$query = "
SELECT
    ct.name,
    su.seo_path_info
FROM seo_url su
JOIN category c ON su.foreign_key = c.id
JOIN category_translation ct ON c.id = ct.category_id
    AND LOWER(HEX(ct.language_id)) = '$languageId'
WHERE su.route_name = 'frontend.navigation.page'
AND LOWER(HEX(su.sales_channel_id)) = '$salesChannelId'
AND su.is_canonical = 1
AND ct.name IN (
    'Waffen',
    'Caracal Lynx', 'LYNX SPORT', 'LYNX COMPACT', 'LYNX OPEN',
    'Raven Weapons', '.22 LR RAVEN', '.223 RAVEN', '300 AAC RAVEN', '7.62x39 RAVEN', '9mm RAVEN',
    'RAPAX', 'RX Tactical', 'RX Sport', 'RX Compact'
)
ORDER BY ct.name
";

$stmt = $pdo->query($query);
$seoUrls = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($seoUrls as $seo) {
    echo "{$seo['name']}: /{$seo['seo_path_info']}\n";
}

echo "\nDone!\n";
