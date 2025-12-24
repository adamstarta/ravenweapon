<?php
/**
 * Fix SEO URLs for Dienstleistungen categories and products
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

// CRITICAL: Use English language ID (sales channel language)
$languageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
$salesChannelId = '0191c12dd4b970949e9aeec40433be3e';

echo "=== FIXING DIENSTLEISTUNGEN SEO URLS ===\n\n";
echo "Language ID (English): $languageId\n";
echo "Sales Channel ID: $salesChannelId\n\n";

// Category SEO URL mappings
$categorySeoUrls = [
    'Dienstleistungen' => 'dienstleistungen/',
    'Schiesskurse' => 'dienstleistungen/schiesskurse/',
    'Basic-Kurse' => 'dienstleistungen/schiesskurse/basic-kurse/',
    'Privatunterricht' => 'dienstleistungen/schiesskurse/privatunterricht/'
];

// Product SEO URL mappings
$productSeoUrls = [
    'Basic-Kurs' => 'dienstleistungen/schiesskurse/basic-kurse/basic-kurs/',
    'Basic-Kurs-II' => 'dienstleistungen/schiesskurse/basic-kurse/basic-kurs-2-personen/',
    'Basic-Kurs-III' => 'dienstleistungen/schiesskurse/basic-kurse/basic-kurs-3-personen/',
    'Basic-Kurs-IV' => 'dienstleistungen/schiesskurse/basic-kurse/basic-kurs-4-personen/',
    'Instruktor-2-H' => 'dienstleistungen/schiesskurse/privatunterricht/instruktor-2-stunden/'
];

echo "--- FIXING CATEGORY SEO URLS ---\n\n";

foreach ($categorySeoUrls as $catName => $seoPath) {
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

    // Delete any existing SEO URLs for this category
    $stmt = $pdo->prepare("
        DELETE FROM seo_url
        WHERE LOWER(HEX(foreign_key)) = ?
        AND route_name = 'frontend.navigation.page'
    ");
    $stmt->execute([$catId]);

    // Create new SEO URL
    $seoUrlId = bin2hex(random_bytes(16));
    $pathInfo = '/navigation/' . $catId;
    $now = (new DateTime())->format('Y-m-d H:i:s.v');

    $stmt = $pdo->prepare("
        INSERT INTO seo_url (id, language_id, sales_channel_id, foreign_key, route_name, path_info, seo_path_info, is_canonical, is_modified, is_deleted, created_at)
        VALUES (UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), 'frontend.navigation.page', ?, ?, 1, 0, 0, ?)
    ");
    $stmt->execute([$seoUrlId, $languageId, $salesChannelId, $catId, $pathInfo, $seoPath, $now]);

    echo "Category '$catName': /$seoPath\n";
}

echo "\n--- FIXING PRODUCT SEO URLS ---\n\n";

foreach ($productSeoUrls as $productNumber => $seoPath) {
    // Get product ID
    $stmt = $pdo->prepare("
        SELECT LOWER(HEX(id)) as id
        FROM product
        WHERE product_number = ?
        LIMIT 1
    ");
    $stmt->execute([$productNumber]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prod) {
        echo "Product '$productNumber': NOT FOUND - skipping\n";
        continue;
    }

    $productId = $prod['id'];

    // Delete any existing SEO URLs for this product
    $stmt = $pdo->prepare("
        DELETE FROM seo_url
        WHERE LOWER(HEX(foreign_key)) = ?
        AND route_name = 'frontend.detail.page'
    ");
    $stmt->execute([$productId]);

    // Create new SEO URL
    $seoUrlId = bin2hex(random_bytes(16));
    $pathInfo = '/detail/' . $productId;
    $now = (new DateTime())->format('Y-m-d H:i:s.v');

    $stmt = $pdo->prepare("
        INSERT INTO seo_url (id, language_id, sales_channel_id, foreign_key, route_name, path_info, seo_path_info, is_canonical, is_modified, is_deleted, created_at)
        VALUES (UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), 'frontend.detail.page', ?, ?, 1, 0, 0, ?)
    ");
    $stmt->execute([$seoUrlId, $languageId, $salesChannelId, $productId, $pathInfo, $seoPath, $now]);

    echo "Product '$productNumber': /$seoPath\n";
}

echo "\n=== DONE ===\n";
echo "\nRun: bin/console cache:clear\n";
