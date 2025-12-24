<?php
/**
 * Create Dienstleistungen category hierarchy, products, and SEO URLs
 *
 * Structure:
 * - Dienstleistungen
 *   - Schiesskurse
 *     - Basic-Kurse (4 products)
 *     - Privatunterricht (1 product)
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
$connectionMethods = [
    "mysql:host=127.0.0.1;port=3306;dbname=$dbname;charset=utf8mb4",
    "mysql:host=$host;port=3306;dbname=$dbname;charset=utf8mb4",
];

foreach ($connectionMethods as $dsn) {
    try {
        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Connected via: $dsn\n";
        break;
    } catch (PDOException $e) {
        continue;
    }
}

if (!$pdo) {
    die("Could not connect to database\n");
}

// Constants
$salesChannelId = '0191c12dd4b970949e9aeec40433be3e';
$languageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
$versionId = '0fa91ce3e96a4bc2be4bd9ce752c3425';

// Get root category ID
$stmt = $pdo->query("SELECT LOWER(HEX(id)) as id FROM category WHERE parent_id IS NULL LIMIT 1");
$rootCategory = $stmt->fetch(PDO::FETCH_ASSOC);
$rootCategoryId = $rootCategory['id'];
echo "Root category ID: $rootCategoryId\n\n";

// Get tax ID (7.7% Swiss VAT)
$stmt = $pdo->query("SELECT LOWER(HEX(id)) as id FROM tax WHERE tax_rate = 7.7 LIMIT 1");
$tax = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$tax) {
    $stmt = $pdo->query("SELECT LOWER(HEX(id)) as id FROM tax LIMIT 1");
    $tax = $stmt->fetch(PDO::FETCH_ASSOC);
}
$taxId = $tax['id'];
echo "Tax ID: $taxId\n\n";

// ============================================
// PART 1: CREATE CATEGORIES
// ============================================
echo "=== PART 1: Creating Categories ===\n\n";

function createCategory($pdo, $name, $parentId, $languageId, $versionId) {
    // Check if category already exists
    $stmt = $pdo->prepare("
        SELECT LOWER(HEX(c.id)) as id FROM category c
        JOIN category_translation ct ON c.id = ct.category_id
        WHERE ct.name = ? AND ct.language_id = UNHEX(?)
    ");
    $stmt->execute([$name, $languageId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo "EXISTS: $name (ID: {$existing['id']})\n";
        return $existing['id'];
    }

    $id = bin2hex(random_bytes(16));
    $now = (new DateTime())->format('Y-m-d H:i:s.v');

    // Get parent path and level
    $path = '|';
    $level = 1;
    if ($parentId) {
        $stmt = $pdo->prepare("SELECT path, level FROM category WHERE LOWER(HEX(id)) = ?");
        $stmt->execute([$parentId]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($parent) {
            $path = $parent['path'] . $parentId . '|';
            $level = $parent['level'] + 1;
        }
    }

    // Insert category
    $sql = "
        INSERT INTO category (id, version_id, parent_id, parent_version_id, path, level, active, visible, type, display_nested_products, created_at)
        VALUES (
            UNHEX(?),
            UNHEX(?),
            " . ($parentId ? "UNHEX(?)" : "NULL") . ",
            " . ($parentId ? "UNHEX(?)" : "NULL") . ",
            ?, ?, 1, 1, 'page', 1, ?
        )
    ";

    $params = [$id, $versionId];
    if ($parentId) {
        $params[] = $parentId;
        $params[] = $versionId;
    }
    $params[] = $path;
    $params[] = $level;
    $params[] = $now;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Insert translation
    $stmt = $pdo->prepare("
        INSERT INTO category_translation (category_id, category_version_id, language_id, name, created_at)
        VALUES (UNHEX(?), UNHEX(?), UNHEX(?), ?, ?)
    ");
    $stmt->execute([$id, $versionId, $languageId, $name, $now]);

    echo "CREATED: $name (ID: $id)\n";
    return $id;
}

// Create hierarchy
$dienstleistungenId = createCategory($pdo, 'Dienstleistungen', $rootCategoryId, $languageId, $versionId);
$schiesskurseId = createCategory($pdo, 'Schiesskurse', $dienstleistungenId, $languageId, $versionId);
$basicKurseId = createCategory($pdo, 'Basic-Kurse', $schiesskurseId, $languageId, $versionId);
$privatunterrichtId = createCategory($pdo, 'Privatunterricht', $schiesskurseId, $languageId, $versionId);

$categoryIds = [
    'Dienstleistungen' => $dienstleistungenId,
    'Schiesskurse' => $schiesskurseId,
    'Basic-Kurse' => $basicKurseId,
    'Privatunterricht' => $privatunterrichtId
];

// ============================================
// PART 2: CREATE CATEGORY SEO URLS
// ============================================
echo "\n=== PART 2: Creating Category SEO URLs ===\n\n";

$categorySeoUrls = [
    'Dienstleistungen' => 'dienstleistungen',
    'Schiesskurse' => 'dienstleistungen/schiesskurse',
    'Basic-Kurse' => 'dienstleistungen/schiesskurse/basic-kurse',
    'Privatunterricht' => 'dienstleistungen/schiesskurse/privatunterricht'
];

foreach ($categorySeoUrls as $categoryName => $seoPath) {
    $categoryId = $categoryIds[$categoryName];
    $now = (new DateTime())->format('Y-m-d H:i:s.v');
    $seoUrlId = bin2hex(random_bytes(16));

    // Delete existing
    $stmt = $pdo->prepare("
        DELETE FROM seo_url
        WHERE LOWER(HEX(foreign_key)) = ?
        AND route_name = 'frontend.navigation.page'
    ");
    $stmt->execute([$categoryId]);

    // Insert new
    $pathInfo = '/navigation/' . $categoryId;
    $stmt = $pdo->prepare("
        INSERT INTO seo_url (id, language_id, sales_channel_id, foreign_key, route_name, path_info, seo_path_info, is_canonical, is_modified, is_deleted, created_at)
        VALUES (UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), 'frontend.navigation.page', ?, ?, 1, 0, 0, ?)
    ");
    $stmt->execute([$seoUrlId, $languageId, $salesChannelId, $categoryId, $pathInfo, $seoPath, $now]);

    echo "SEO URL: /$seoPath/ -> $categoryName\n";
}

// ============================================
// PART 3: CREATE PRODUCTS
// ============================================
echo "\n=== PART 3: Creating Products ===\n\n";

$products = [
    [
        'number' => 'Basic-Kurs',
        'name' => 'Raven Basic-Kurs – Dein Einstieg in den Schiesssport (1 Person)',
        'price' => 480.00,
        'category' => 'Basic-Kurse',
        'active' => true,
        'description' => '<p><strong>Dein Start mit der Raven - sicher, präzise, professionell.</strong></p><p>In diesem kompakten Einsteigerkurs lernst du den sicheren Umgang, stabile Haltung und präzises Schiessen mit der Raven.</p><p><strong>Kursinhalt:</strong></p><ul><li>Sicherheitsregeln und Waffenhandhabung</li><li>Stabile Körperhaltung und Atemtechnik</li><li>Zielen und Abzugstechnik</li><li>Praktische Schiessübungen</li></ul><p><strong>Dauer:</strong> ca. 2-3 Stunden</p><p><strong>Teilnehmer:</strong> 1 Person</p>',
        'seo_path' => 'dienstleistungen/schiesskurse/basic-kurse/basic-kurs'
    ],
    [
        'number' => 'Basic-Kurs-II',
        'name' => 'Raven Basic-Kurs – Dein Einstieg in den Schiesssport (2 Personen)',
        'price' => 800.00,
        'category' => 'Basic-Kurse',
        'active' => true,
        'description' => '<p><strong>Dein Start mit der Raven - sicher, präzise, professionell.</strong></p><p>In diesem kompakten Einsteigerkurs lernst du den sicheren Umgang, stabile Haltung und präzises Schiessen mit der Raven.</p><p><strong>Kursinhalt:</strong></p><ul><li>Sicherheitsregeln und Waffenhandhabung</li><li>Stabile Körperhaltung und Atemtechnik</li><li>Zielen und Abzugstechnik</li><li>Praktische Schiessübungen</li></ul><p><strong>Dauer:</strong> ca. 2-3 Stunden</p><p><strong>Teilnehmer:</strong> 2 Personen</p>',
        'seo_path' => 'dienstleistungen/schiesskurse/basic-kurse/basic-kurs-2-personen'
    ],
    [
        'number' => 'Basic-Kurs-III',
        'name' => 'Raven Basic-Kurs – Dein Einstieg in den Schiesssport (3 Personen)',
        'price' => 1050.00,
        'category' => 'Basic-Kurse',
        'active' => true,
        'description' => '<p><strong>Dein Start mit der Raven - sicher, präzise, professionell.</strong></p><p>In diesem kompakten Einsteigerkurs lernst du den sicheren Umgang, stabile Haltung und präzises Schiessen mit der Raven.</p><p><strong>Kursinhalt:</strong></p><ul><li>Sicherheitsregeln und Waffenhandhabung</li><li>Stabile Körperhaltung und Atemtechnik</li><li>Zielen und Abzugstechnik</li><li>Praktische Schiessübungen</li></ul><p><strong>Dauer:</strong> ca. 2-3 Stunden</p><p><strong>Teilnehmer:</strong> 3 Personen</p>',
        'seo_path' => 'dienstleistungen/schiesskurse/basic-kurse/basic-kurs-3-personen'
    ],
    [
        'number' => 'Basic-Kurs-IV',
        'name' => 'Raven Basic-Kurs – Dein Einstieg in den Schiesssport (4 Personen)',
        'price' => 1200.00,
        'category' => 'Basic-Kurse',
        'active' => true,
        'description' => '<p><strong>Dein Start mit der Raven - sicher, präzise, professionell.</strong></p><p>In diesem kompakten Einsteigerkurs lernst du den sicheren Umgang, stabile Haltung und präzises Schiessen mit der Raven.</p><p><strong>Kursinhalt:</strong></p><ul><li>Sicherheitsregeln und Waffenhandhabung</li><li>Stabile Körperhaltung und Atemtechnik</li><li>Zielen und Abzugstechnik</li><li>Praktische Schiessübungen</li></ul><p><strong>Dauer:</strong> ca. 2-3 Stunden</p><p><strong>Teilnehmer:</strong> 4 Personen</p>',
        'seo_path' => 'dienstleistungen/schiesskurse/basic-kurse/basic-kurs-4-personen'
    ],
    [
        'number' => 'Instruktor-2-H',
        'name' => 'Instruktor 2 Stunden',
        'price' => 300.00,
        'category' => 'Privatunterricht',
        'active' => false,
        'description' => '<p><strong>Privater Schiessunterricht mit professionellem Instruktor.</strong></p><p>Buchen Sie 2 Stunden individuellen Unterricht mit einem erfahrenen Instruktor.</p><p><strong>Dauer:</strong> 2 Stunden</p>',
        'seo_path' => 'dienstleistungen/schiesskurse/privatunterricht/instruktor-2-stunden'
    ]
];

// Get CHF currency ID
$stmt = $pdo->query("SELECT LOWER(HEX(id)) as id FROM currency WHERE iso_code = 'CHF' LIMIT 1");
$currency = $stmt->fetch(PDO::FETCH_ASSOC);
$currencyId = $currency ? $currency['id'] : 'b7d2554b0ce847cd82f3ac9bd1c0dfca';

foreach ($products as $product) {
    // Check if product exists
    $stmt = $pdo->prepare("SELECT LOWER(HEX(id)) as id FROM product WHERE product_number = ?");
    $stmt->execute([$product['number']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo "EXISTS: {$product['number']}\n";
        $productId = $existing['id'];
    } else {
        $productId = bin2hex(random_bytes(16));
        $now = (new DateTime())->format('Y-m-d H:i:s.v');
        $categoryId = $categoryIds[$product['category']];

        // Calculate net price (CHF prices are gross, 7.7% VAT)
        $netPrice = round($product['price'] / 1.077, 2);
        $priceJson = json_encode([[
            'currencyId' => $currencyId,
            'gross' => $product['price'],
            'net' => $netPrice,
            'linked' => true
        ]]);

        // Insert product
        $stmt = $pdo->prepare("
            INSERT INTO product (
                id, version_id, product_number, active, stock, is_closeout,
                purchase_steps, min_purchase, max_purchase, shipping_free,
                tax_id, price, created_at
            ) VALUES (
                UNHEX(?), UNHEX(?), ?, ?, 999, 0,
                1, 1, 10, 1,
                UNHEX(?), ?, ?
            )
        ");
        $stmt->execute([
            $productId, $versionId, $product['number'],
            $product['active'] ? 1 : 0,
            $taxId, $priceJson, $now
        ]);

        // Insert translation
        $stmt = $pdo->prepare("
            INSERT INTO product_translation (
                product_id, product_version_id, language_id,
                name, description, created_at
            ) VALUES (UNHEX(?), UNHEX(?), UNHEX(?), ?, ?, ?)
        ");
        $stmt->execute([$productId, $versionId, $languageId, $product['name'], $product['description'], $now]);

        // Link to category
        $stmt = $pdo->prepare("
            INSERT INTO product_category (product_id, product_version_id, category_id, category_version_id)
            VALUES (UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?))
        ");
        $stmt->execute([$productId, $versionId, $categoryId, $versionId]);

        // Set main category
        $stmt = $pdo->prepare("
            INSERT INTO main_category (id, product_id, product_version_id, category_id, category_version_id, sales_channel_id, created_at)
            VALUES (UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), ?)
        ");
        $stmt->execute([bin2hex(random_bytes(16)), $productId, $versionId, $categoryId, $versionId, $salesChannelId, $now]);

        // Link to sales channel
        $stmt = $pdo->prepare("
            INSERT INTO product_visibility (id, product_id, product_version_id, sales_channel_id, visibility, created_at)
            VALUES (UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), 30, ?)
        ");
        $stmt->execute([bin2hex(random_bytes(16)), $productId, $versionId, $salesChannelId, $now]);

        echo "CREATED: {$product['number']} - CHF {$product['price']}\n";
    }

    // Create SEO URL for product
    $now = (new DateTime())->format('Y-m-d H:i:s.v');
    $seoUrlId = bin2hex(random_bytes(16));

    // Delete existing product SEO URL
    $stmt = $pdo->prepare("
        DELETE FROM seo_url WHERE LOWER(HEX(foreign_key)) = ? AND route_name = 'frontend.detail.page'
    ");
    $stmt->execute([$productId]);

    // Insert new SEO URL
    $pathInfo = '/detail/' . $productId;
    $stmt = $pdo->prepare("
        INSERT INTO seo_url (id, language_id, sales_channel_id, foreign_key, route_name, path_info, seo_path_info, is_canonical, is_modified, is_deleted, created_at)
        VALUES (UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), 'frontend.detail.page', ?, ?, 1, 0, 0, ?)
    ");
    $stmt->execute([$seoUrlId, $languageId, $salesChannelId, $productId, $pathInfo, $product['seo_path'], $now]);

    echo "  SEO: /{$product['seo_path']}/\n";
}

echo "\n=== ALL DONE ===\n";
echo "\nCategory IDs:\n";
print_r($categoryIds);
echo "\nNext: Update header navigation and upload images.\n";
