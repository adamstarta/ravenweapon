<?php
/**
 * Set main_category for all products that don't have one
 * Uses a preferred category from product_category based on category level
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

// Get the sales channel ID
$scQuery = "SELECT LOWER(HEX(id)) as id FROM sales_channel WHERE active = 1 LIMIT 1";
$scStmt = $pdo->query($scQuery);
$salesChannelId = $scStmt->fetchColumn();

echo "=== Setting Main Categories for Products ===\n";
echo "Sales Channel: $salesChannelId\n\n";

// Find products without main_category, preferring higher-level categories (more specific)
// Exclude "Alle Produkte" and similar meta-categories
$query = "
SELECT DISTINCT
    LOWER(HEX(p.id)) as product_id,
    MAX(pt.name) as product_name,
    LOWER(HEX(pc.category_id)) as category_id,
    MAX(ct.name) as category_name,
    c.level
FROM product p
JOIN product_translation pt ON p.id = pt.product_id
JOIN product_category pc ON p.id = pc.product_id
JOIN category c ON pc.category_id = c.id
LEFT JOIN category_translation ct ON pc.category_id = ct.category_id
LEFT JOIN main_category mc ON p.id = mc.product_id AND mc.sales_channel_id = UNHEX('$salesChannelId')
WHERE mc.product_id IS NULL
AND p.parent_id IS NULL
AND c.level >= 2
AND ct.name NOT IN ('Alle Produkte', 'All Products')
GROUP BY p.id, pc.category_id, c.level
ORDER BY MAX(pt.name), c.level DESC
";

$stmt = $pdo->query($query);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($results) . " product-category combinations\n\n";

if (count($results) === 0) {
    echo "All products have main_category assigned.\n";
    exit(0);
}

// Group by product and pick the best category (highest level = most specific)
$byProduct = [];
foreach ($results as $row) {
    $pid = $row['product_id'];
    if (!isset($byProduct[$pid])) {
        $byProduct[$pid] = [
            'name' => $row['product_name'],
            'category_id' => $row['category_id'],
            'category_name' => $row['category_name'],
            'level' => $row['level']
        ];
    } else {
        // Prefer higher level (more specific) categories
        if ($row['level'] > $byProduct[$pid]['level']) {
            $byProduct[$pid]['category_id'] = $row['category_id'];
            $byProduct[$pid]['category_name'] = $row['category_name'];
            $byProduct[$pid]['level'] = $row['level'];
        }
    }
}

echo "Unique products to update: " . count($byProduct) . "\n\n";

echo "Products to update:\n";
echo str_repeat("-", 80) . "\n";
foreach ($byProduct as $pid => $data) {
    echo sprintf("%-50s -> %s (L%d)\n",
        substr($data['name'], 0, 48),
        $data['category_name'],
        $data['level']
    );
}
echo str_repeat("-", 80) . "\n\n";

echo "Proceed with update? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) !== 'yes') {
    echo "Aborted.\n";
    exit(0);
}

echo "\nUpdating main_category entries...\n\n";

$updated = 0;
$errors = 0;

foreach ($byProduct as $pid => $data) {
    $categoryId = $data['category_id'];

    $insertQuery = "
    INSERT INTO main_category (id, product_id, product_version_id, category_id, category_version_id, sales_channel_id, created_at)
    SELECT
        UNHEX(REPLACE(UUID(), '-', '')),
        UNHEX('$pid'),
        p.version_id,
        UNHEX('$categoryId'),
        c.version_id,
        UNHEX('$salesChannelId'),
        NOW()
    FROM product p, category c
    WHERE LOWER(HEX(p.id)) = '$pid'
    AND LOWER(HEX(c.id)) = '$categoryId'
    LIMIT 1
    ";

    try {
        $pdo->exec($insertQuery);
        echo "  SET: " . $data['name'] . " -> " . $data['category_name'] . "\n";
        $updated++;
    } catch (PDOException $e) {
        echo "  ERROR: " . $data['name'] . " - " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n";
echo "===========================================\n";
echo "Summary:\n";
echo "  - Updated: $updated products\n";
echo "  - Errors: $errors\n";
echo "===========================================\n\n";

echo "IMPORTANT: Run these commands to regenerate SEO URLs:\n";
echo "  bin/console dal:refresh:index\n";
echo "  bin/console cache:clear\n";
echo "\n";
