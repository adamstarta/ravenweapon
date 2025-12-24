<?php
/**
 * Assign default product listing CMS layout to Dienstleistungen categories
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

// Get the default product listing CMS page ID
$stmt = $pdo->query("
    SELECT LOWER(HEX(id)) as id
    FROM cms_page
    WHERE type = 'product_list'
    LIMIT 1
");
$cmsPage = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cmsPage) {
    die("ERROR: No product_list CMS page found!\n");
}

$cmsPageId = $cmsPage['id'];
echo "Default product listing CMS page ID: $cmsPageId\n\n";

// Categories to update
$categories = ['Dienstleistungen', 'Schiesskurse', 'Basic-Kurse', 'Privatunterricht'];

echo "=== ASSIGNING CMS LAYOUT TO CATEGORIES ===\n\n";

foreach ($categories as $catName) {
    // Get category ID
    $stmt = $pdo->prepare("
        SELECT LOWER(HEX(c.id)) as id
        FROM category c
        JOIN category_translation ct ON c.id = ct.category_id
        WHERE ct.name = ?
        LIMIT 1
    ");
    $stmt->execute([$catName]);
    $cat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cat) {
        echo "Category '$catName': NOT FOUND - skipping\n";
        continue;
    }

    $catId = $cat['id'];

    // Update category with CMS page
    $stmt = $pdo->prepare("
        UPDATE category
        SET cms_page_id = UNHEX(?)
        WHERE LOWER(HEX(id)) = ?
    ");
    $stmt->execute([$cmsPageId, $catId]);

    echo "Category '$catName' (ID: $catId): Assigned CMS layout\n";
}

echo "\n=== DONE ===\n";
echo "\nRun: bin/console cache:clear\n";
