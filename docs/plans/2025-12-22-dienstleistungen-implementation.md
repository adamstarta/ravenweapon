# Dienstleistungen Category Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create "Dienstleistungen" service category with shooting courses and 5 training products in Shopware.

**Architecture:** Create category hierarchy via SQL/API, import products with images from S3, generate SEO URLs, update header navigation template.

**Tech Stack:** Shopware 6.6, PHP, MySQL, Twig, SCSS

---

## Task 1: Create Category Hierarchy in Shopware

**Files:**
- Create: `scripts/create-dienstleistungen-categories.php`

**Step 1: Create the PHP script**

```php
<?php
/**
 * Create Dienstleistungen category hierarchy in Shopware
 *
 * Structure:
 * - Dienstleistungen
 *   - Schiesskurse
 *     - Basic-Kurse
 *     - Privatunterricht
 */

require_once __DIR__ . '/config.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get sales channel ID and language ID
$salesChannelId = '0191c12dd4b970949e9aeec40433be3e';
$languageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b'; // English (used for German content)

// Get root category ID (main navigation)
$stmt = $pdo->query("SELECT LOWER(HEX(id)) as id FROM category WHERE parent_id IS NULL LIMIT 1");
$rootCategory = $stmt->fetch(PDO::FETCH_ASSOC);
$rootCategoryId = $rootCategory['id'];

echo "Root category ID: $rootCategoryId\n";

// Function to create category
function createCategory($pdo, $name, $parentId, $languageId, $position = 1) {
    $id = bin2hex(random_bytes(16));
    $now = date('Y-m-d H:i:s.v');

    // Get parent path
    $path = '|';
    if ($parentId) {
        $stmt = $pdo->prepare("SELECT path FROM category WHERE LOWER(HEX(id)) = ?");
        $stmt->execute([$parentId]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        $path = ($parent['path'] ?? '|') . $parentId . '|';
    }

    // Insert category
    $stmt = $pdo->prepare("
        INSERT INTO category (id, version_id, parent_id, parent_version_id, path, `level`, active, visible, `type`, created_at)
        VALUES (
            UNHEX(?),
            UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425'),
            " . ($parentId ? "UNHEX(?)" : "NULL") . ",
            " . ($parentId ? "UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425')" : "NULL") . ",
            ?,
            ?,
            1,
            1,
            'page',
            ?
        )
    ");

    $level = substr_count($path, '|');
    $params = [$id];
    if ($parentId) $params[] = $parentId;
    $params = array_merge($params, [$path, $level, $now]);

    $stmt->execute($params);

    // Insert translation
    $stmt = $pdo->prepare("
        INSERT INTO category_translation (category_id, category_version_id, language_id, name, created_at)
        VALUES (UNHEX(?), UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425'), UNHEX(?), ?, ?)
    ");
    $stmt->execute([$id, $languageId, $name, $now]);

    echo "Created category: $name (ID: $id)\n";
    return $id;
}

// Create categories
echo "\n=== Creating Dienstleistungen Category Hierarchy ===\n\n";

// Level 1: Dienstleistungen
$dienstleistungenId = createCategory($pdo, 'Dienstleistungen', $rootCategoryId, $languageId, 10);

// Level 2: Schiesskurse
$schiesskurseId = createCategory($pdo, 'Schiesskurse', $dienstleistungenId, $languageId, 1);

// Level 3: Basic-Kurse
$basicKurseId = createCategory($pdo, 'Basic-Kurse', $schiesskurseId, $languageId, 1);

// Level 3: Privatunterricht
$privatunterrichtId = createCategory($pdo, 'Privatunterricht', $schiesskurseId, $languageId, 2);

// Save category IDs for next script
$categoryIds = [
    'dienstleistungen' => $dienstleistungenId,
    'schiesskurse' => $schiesskurseId,
    'basic_kurse' => $basicKurseId,
    'privatunterricht' => $privatunterrichtId
];

file_put_contents(__DIR__ . '/dienstleistungen-category-ids.json', json_encode($categoryIds, JSON_PRETTY_PRINT));

echo "\n=== Category IDs saved to dienstleistungen-category-ids.json ===\n";
echo json_encode($categoryIds, JSON_PRETTY_PRINT) . "\n";
```

**Step 2: Run the script on production**

```bash
scp scripts/create-dienstleistungen-categories.php root@77.42.19.154:/tmp/
ssh root@77.42.19.154 "docker cp /tmp/create-dienstleistungen-categories.php shopware-chf:/tmp/"
ssh root@77.42.19.154 "docker exec shopware-chf php /tmp/create-dienstleistungen-categories.php"
```

Expected output:
```
Root category ID: [uuid]
=== Creating Dienstleistungen Category Hierarchy ===

Created category: Dienstleistungen (ID: [uuid])
Created category: Schiesskurse (ID: [uuid])
Created category: Basic-Kurse (ID: [uuid])
Created category: Privatunterricht (ID: [uuid])

=== Category IDs saved to dienstleistungen-category-ids.json ===
```

**Step 3: Verify categories exist**

```bash
ssh root@77.42.19.154 "docker exec shopware-chf mysql -u root -proot shopware -e \"SELECT LOWER(HEX(c.id)), ct.name FROM category c JOIN category_translation ct ON c.id = ct.category_id WHERE ct.name IN ('Dienstleistungen', 'Schiesskurse', 'Basic-Kurse', 'Privatunterricht');\""
```

---

## Task 2: Generate SEO URLs for Categories

**Files:**
- Create: `scripts/create-dienstleistungen-seo-urls.php`

**Step 1: Create the SEO URL script**

```php
<?php
/**
 * Generate SEO URLs for Dienstleistungen categories
 */

require_once __DIR__ . '/config.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$salesChannelId = '0191c12dd4b970949e9aeec40433be3e';
$languageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';

// SEO URL mappings
$seoUrls = [
    'Dienstleistungen' => 'dienstleistungen',
    'Schiesskurse' => 'dienstleistungen/schiesskurse',
    'Basic-Kurse' => 'dienstleistungen/schiesskurse/basic-kurse',
    'Privatunterricht' => 'dienstleistungen/schiesskurse/privatunterricht'
];

echo "=== Creating SEO URLs for Dienstleistungen Categories ===\n\n";

foreach ($seoUrls as $categoryName => $seoPath) {
    // Get category ID
    $stmt = $pdo->prepare("
        SELECT LOWER(HEX(c.id)) as id
        FROM category c
        JOIN category_translation ct ON c.id = ct.category_id
        WHERE ct.name = ?
    ");
    $stmt->execute([$categoryName]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        echo "WARNING: Category '$categoryName' not found!\n";
        continue;
    }

    $categoryId = $category['id'];
    $now = date('Y-m-d H:i:s.v');
    $seoUrlId = bin2hex(random_bytes(16));

    // Delete existing SEO URLs for this category
    $stmt = $pdo->prepare("
        DELETE FROM seo_url
        WHERE LOWER(HEX(foreign_key)) = ?
        AND route_name = 'frontend.navigation.page'
    ");
    $stmt->execute([$categoryId]);

    // Insert new SEO URL
    $stmt = $pdo->prepare("
        INSERT INTO seo_url (id, language_id, sales_channel_id, foreign_key, route_name, path_info, seo_path_info, is_canonical, is_modified, is_deleted, created_at)
        VALUES (
            UNHEX(?),
            UNHEX(?),
            UNHEX(?),
            UNHEX(?),
            'frontend.navigation.page',
            ?,
            ?,
            1,
            0,
            0,
            ?
        )
    ");

    $pathInfo = '/navigation/' . $categoryId;

    $stmt->execute([
        $seoUrlId,
        $languageId,
        $salesChannelId,
        $categoryId,
        $pathInfo,
        $seoPath,
        $now
    ]);

    echo "Created SEO URL: /$seoPath/ -> $categoryName\n";
}

echo "\n=== Done ===\n";
```

**Step 2: Run the script**

```bash
scp scripts/create-dienstleistungen-seo-urls.php root@77.42.19.154:/tmp/
ssh root@77.42.19.154 "docker cp /tmp/create-dienstleistungen-seo-urls.php shopware-chf:/tmp/"
ssh root@77.42.19.154 "docker exec shopware-chf php /tmp/create-dienstleistungen-seo-urls.php"
```

Expected output:
```
=== Creating SEO URLs for Dienstleistungen Categories ===

Created SEO URL: /dienstleistungen/ -> Dienstleistungen
Created SEO URL: /dienstleistungen/schiesskurse/ -> Schiesskurse
Created SEO URL: /dienstleistungen/schiesskurse/basic-kurse/ -> Basic-Kurse
Created SEO URL: /dienstleistungen/schiesskurse/privatunterricht/ -> Privatunterricht

=== Done ===
```

**Step 3: Clear cache**

```bash
ssh root@77.42.19.154 "docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'"
```

---

## Task 3: Create Training Products

**Files:**
- Create: `scripts/create-training-products.php`

**Step 1: Create the product import script**

```php
<?php
/**
 * Create training course products in Shopware
 */

require_once __DIR__ . '/config.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$salesChannelId = '0191c12dd4b970949e9aeec40433be3e';
$languageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';

// Get category IDs
$stmt = $pdo->prepare("
    SELECT LOWER(HEX(c.id)) as id, ct.name
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE ct.name IN ('Basic-Kurse', 'Privatunterricht')
");
$stmt->execute();
$categories = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $categories[$row['name']] = $row['id'];
}

echo "Category IDs:\n";
print_r($categories);

// Get default tax ID (7.7% Swiss VAT or 0% for services)
$stmt = $pdo->query("SELECT LOWER(HEX(id)) as id FROM tax WHERE tax_rate = 7.7 LIMIT 1");
$tax = $stmt->fetch(PDO::FETCH_ASSOC);
$taxId = $tax['id'];

// Product data
$products = [
    [
        'number' => 'Basic-Kurs',
        'name' => 'Raven Basic-Kurs – Dein Einstieg in den Schiesssport (1 Person)',
        'price' => 480.00,
        'category' => 'Basic-Kurse',
        'active' => true,
        'image_url' => 'https://makaris-prod-public.s3.ewstorage.ch/45684/ChatGPT-Image-29.-Okt.-2025%2C-15_02_48.png',
        'description' => '<p><strong>Dein Start mit der Raven - sicher, präzise, professionell.</strong><br>In diesem kompakten Einsteigerkurs lernst du den sicheren Umgang, stabile Haltung und präzises Schiessen mit der Raven.</p><p><strong>Kursinhalt:</strong></p><ul><li>Sicherheitsregeln und Waffenhandhabung</li><li>Stabile Körperhaltung und Atemtechnik</li><li>Zielen und Abzugstechnik</li><li>Praktische Schiessübungen</li></ul><p><strong>Dauer:</strong> ca. 2-3 Stunden</p><p><strong>Teilnehmer:</strong> 1 Person</p>'
    ],
    [
        'number' => 'Basic-Kurs-II',
        'name' => 'Raven Basic-Kurs – Dein Einstieg in den Schiesssport (2 Personen)',
        'price' => 800.00,
        'category' => 'Basic-Kurse',
        'active' => true,
        'image_url' => 'https://makaris-prod-public.s3.ewstorage.ch/45685/ChatGPT-Image-29.-Okt.-2025%2C-15_06_14.png',
        'description' => '<p><strong>Dein Start mit der Raven - sicher, präzise, professionell.</strong><br>In diesem kompakten Einsteigerkurs lernst du den sicheren Umgang, stabile Haltung und präzises Schiessen mit der Raven.</p><p><strong>Kursinhalt:</strong></p><ul><li>Sicherheitsregeln und Waffenhandhabung</li><li>Stabile Körperhaltung und Atemtechnik</li><li>Zielen und Abzugstechnik</li><li>Praktische Schiessübungen</li></ul><p><strong>Dauer:</strong> ca. 2-3 Stunden</p><p><strong>Teilnehmer:</strong> 2 Personen</p>'
    ],
    [
        'number' => 'Basic-Kurs-III',
        'name' => 'Raven Basic-Kurs – Dein Einstieg in den Schiesssport (3 Personen)',
        'price' => 1050.00,
        'category' => 'Basic-Kurse',
        'active' => true,
        'image_url' => 'https://makaris-prod-public.s3.ewstorage.ch/45686/ChatGPT-Image-29.-Okt.-2025%2C-15_08_34.png',
        'description' => '<p><strong>Dein Start mit der Raven - sicher, präzise, professionell.</strong><br>In diesem kompakten Einsteigerkurs lernst du den sicheren Umgang, stabile Haltung und präzises Schiessen mit der Raven.</p><p><strong>Kursinhalt:</strong></p><ul><li>Sicherheitsregeln und Waffenhandhabung</li><li>Stabile Körperhaltung und Atemtechnik</li><li>Zielen und Abzugstechnik</li><li>Praktische Schiessübungen</li></ul><p><strong>Dauer:</strong> ca. 2-3 Stunden</p><p><strong>Teilnehmer:</strong> 3 Personen</p>'
    ],
    [
        'number' => 'Basic-Kurs-IV',
        'name' => 'Raven Basic-Kurs – Dein Einstieg in den Schiesssport (4 Personen)',
        'price' => 1200.00,
        'category' => 'Basic-Kurse',
        'active' => true,
        'image_url' => 'https://makaris-prod-public.s3.ewstorage.ch/45687/ChatGPT-Image-29.-Okt.-2025%2C-15_13_15.png',
        'description' => '<p><strong>Dein Start mit der Raven - sicher, präzise, professionell.</strong><br>In diesem kompakten Einsteigerkurs lernst du den sicheren Umgang, stabile Haltung und präzises Schiessen mit der Raven.</p><p><strong>Kursinhalt:</strong></p><ul><li>Sicherheitsregeln und Waffenhandhabung</li><li>Stabile Körperhaltung und Atemtechnik</li><li>Zielen und Abzugstechnik</li><li>Praktische Schiessübungen</li></ul><p><strong>Dauer:</strong> ca. 2-3 Stunden</p><p><strong>Teilnehmer:</strong> 4 Personen</p>'
    ],
    [
        'number' => 'Instruktor-2-H',
        'name' => 'Instruktor 2 Stunden',
        'price' => 300.00,
        'category' => 'Privatunterricht',
        'active' => false,
        'image_url' => null, // Placeholder
        'description' => '<p><strong>Privater Schiessunterricht mit professionellem Instruktor.</strong></p><p>Buchen Sie 2 Stunden individuellen Unterricht mit einem erfahrenen Instruktor.</p><p><strong>Dauer:</strong> 2 Stunden</p>'
    ]
];

echo "\n=== Creating Training Products ===\n\n";

foreach ($products as $product) {
    $productId = bin2hex(random_bytes(16));
    $now = date('Y-m-d H:i:s.v');
    $categoryId = $categories[$product['category']];

    // Check if product already exists
    $stmt = $pdo->prepare("SELECT LOWER(HEX(id)) as id FROM product WHERE product_number = ?");
    $stmt->execute([$product['number']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo "SKIP: Product {$product['number']} already exists\n";
        continue;
    }

    // Insert product
    $stmt = $pdo->prepare("
        INSERT INTO product (
            id, version_id, product_number, active, stock, is_closeout,
            purchase_steps, min_purchase, max_purchase,
            tax_id, created_at
        ) VALUES (
            UNHEX(?),
            UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425'),
            ?, ?, 999, 0,
            1, 1, 10,
            UNHEX(?), ?
        )
    ");
    $stmt->execute([
        $productId,
        $product['number'],
        $product['active'] ? 1 : 0,
        $taxId,
        $now
    ]);

    // Insert translation
    $stmt = $pdo->prepare("
        INSERT INTO product_translation (
            product_id, product_version_id, language_id,
            name, description, created_at
        ) VALUES (
            UNHEX(?),
            UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425'),
            UNHEX(?),
            ?, ?, ?
        )
    ");
    $stmt->execute([
        $productId,
        $languageId,
        $product['name'],
        $product['description'],
        $now
    ]);

    // Insert price
    $priceJson = json_encode([
        [
            'currencyId' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
            'gross' => $product['price'],
            'net' => round($product['price'] / 1.077, 2),
            'linked' => true
        ]
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO product_price (
            id, version_id, product_id, product_version_id,
            rule_id, price, quantity_start, quantity_end, created_at
        ) VALUES (
            UNHEX(?),
            UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425'),
            UNHEX(?),
            UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425'),
            NULL, ?, 1, NULL, ?
        )
    ");
    $stmt->execute([bin2hex(random_bytes(16)), $productId, $priceJson, $now]);

    // Link to category
    $stmt = $pdo->prepare("
        INSERT INTO product_category (
            product_id, product_version_id,
            category_id, category_version_id
        ) VALUES (
            UNHEX(?),
            UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425'),
            UNHEX(?),
            UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425')
        )
    ");
    $stmt->execute([$productId, $categoryId]);

    // Set main category
    $stmt = $pdo->prepare("
        INSERT INTO main_category (
            id, product_id, product_version_id,
            category_id, category_version_id,
            sales_channel_id, created_at
        ) VALUES (
            UNHEX(?),
            UNHEX(?),
            UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425'),
            UNHEX(?),
            UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425'),
            UNHEX(?), ?
        )
    ");
    $stmt->execute([
        bin2hex(random_bytes(16)),
        $productId,
        $categoryId,
        $salesChannelId,
        $now
    ]);

    // Link to sales channel
    $stmt = $pdo->prepare("
        INSERT INTO product_visibility (
            id, product_id, product_version_id,
            sales_channel_id, visibility, created_at
        ) VALUES (
            UNHEX(?),
            UNHEX(?),
            UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425'),
            UNHEX(?), 30, ?
        )
    ");
    $stmt->execute([bin2hex(random_bytes(16)), $productId, $salesChannelId, $now]);

    echo "Created: {$product['number']} - {$product['name']} - CHF {$product['price']}\n";
}

echo "\n=== Done ===\n";
```

**Step 2: Run the script**

```bash
scp scripts/create-training-products.php root@77.42.19.154:/tmp/
ssh root@77.42.19.154 "docker cp /tmp/create-training-products.php shopware-chf:/tmp/"
ssh root@77.42.19.154 "docker exec shopware-chf php /tmp/create-training-products.php"
```

**Step 3: Clear cache**

```bash
ssh root@77.42.19.154 "docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'"
```

---

## Task 4: Download and Upload Product Images

**Files:**
- Create: `scripts/upload-training-images.php`

**Step 1: Create image upload script**

```php
<?php
/**
 * Download images from S3 and upload to Shopware media
 */

require_once __DIR__ . '/config.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$languageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';

$imageUrls = [
    'Basic-Kurs' => 'https://makaris-prod-public.s3.ewstorage.ch/45684/ChatGPT-Image-29.-Okt.-2025%2C-15_02_48.png',
    'Basic-Kurs-II' => 'https://makaris-prod-public.s3.ewstorage.ch/45685/ChatGPT-Image-29.-Okt.-2025%2C-15_06_14.png',
    'Basic-Kurs-III' => 'https://makaris-prod-public.s3.ewstorage.ch/45686/ChatGPT-Image-29.-Okt.-2025%2C-15_08_34.png',
    'Basic-Kurs-IV' => 'https://makaris-prod-public.s3.ewstorage.ch/45687/ChatGPT-Image-29.-Okt.-2025%2C-15_13_15.png'
];

echo "=== Uploading Training Course Images ===\n\n";

foreach ($imageUrls as $productNumber => $imageUrl) {
    echo "Processing: $productNumber\n";

    // Get product ID
    $stmt = $pdo->prepare("SELECT LOWER(HEX(id)) as id FROM product WHERE product_number = ?");
    $stmt->execute([$productNumber]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo "  ERROR: Product not found\n";
        continue;
    }

    $productId = $product['id'];

    // Download image
    $imageData = @file_get_contents($imageUrl);
    if (!$imageData) {
        echo "  ERROR: Could not download image\n";
        continue;
    }

    // Create media entry
    $mediaId = bin2hex(random_bytes(16));
    $now = date('Y-m-d H:i:s.v');
    $fileName = strtolower(str_replace('-', '_', $productNumber));

    // Get media folder ID for products
    $stmt = $pdo->query("SELECT LOWER(HEX(id)) as id FROM media_folder WHERE name = 'Product Media' LIMIT 1");
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);
    $folderId = $folder ? $folder['id'] : null;

    // Insert media
    $stmt = $pdo->prepare("
        INSERT INTO media (id, media_folder_id, mime_type, file_extension, file_size, created_at)
        VALUES (UNHEX(?), " . ($folderId ? "UNHEX(?)" : "NULL") . ", 'image/png', 'png', ?, ?)
    ");
    $params = [$mediaId];
    if ($folderId) $params[] = $folderId;
    $params[] = strlen($imageData);
    $params[] = $now;
    $stmt->execute($params);

    // Insert media translation
    $stmt = $pdo->prepare("
        INSERT INTO media_translation (media_id, language_id, title, created_at)
        VALUES (UNHEX(?), UNHEX(?), ?, ?)
    ");
    $stmt->execute([$mediaId, $languageId, $productNumber, $now]);

    // Save file to filesystem
    $mediaPath = "/var/www/html/public/media";
    $subPath = substr($mediaId, 0, 2) . '/' . substr($mediaId, 2, 2);
    $fullPath = "$mediaPath/$subPath";

    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0755, true);
    }

    file_put_contents("$fullPath/$fileName.png", $imageData);

    // Update media with path
    $stmt = $pdo->prepare("UPDATE media SET file_name = ?, path = ? WHERE LOWER(HEX(id)) = ?");
    $stmt->execute([$fileName, "media/$subPath/$fileName.png", $mediaId]);

    // Link media to product as cover
    $productMediaId = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("
        INSERT INTO product_media (id, version_id, product_id, product_version_id, media_id, position, created_at)
        VALUES (
            UNHEX(?),
            UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425'),
            UNHEX(?),
            UNHEX('0fa91ce3e96a4bc2be4bd9ce752c3425'),
            UNHEX(?), 1, ?
        )
    ");
    $stmt->execute([$productMediaId, $productId, $mediaId, $now]);

    // Set as cover
    $stmt = $pdo->prepare("
        UPDATE product
        SET cover_id = UNHEX(?)
        WHERE LOWER(HEX(id)) = ?
    ");
    $stmt->execute([$productMediaId, $productId]);

    echo "  OK: Image uploaded and set as cover\n";
}

echo "\n=== Done ===\n";
```

**Step 2: Run the script**

```bash
scp scripts/upload-training-images.php root@77.42.19.154:/tmp/
ssh root@77.42.19.154 "docker cp /tmp/upload-training-images.php shopware-chf:/tmp/"
ssh root@77.42.19.154 "docker exec shopware-chf php /tmp/upload-training-images.php"
```

---

## Task 5: Generate Product SEO URLs

**Files:**
- Create: `scripts/create-training-seo-urls.php`

**Step 1: Create SEO URL script for products**

```php
<?php
/**
 * Generate SEO URLs for training products
 */

require_once __DIR__ . '/config.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$salesChannelId = '0191c12dd4b970949e9aeec40433be3e';
$languageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';

$seoUrls = [
    'Basic-Kurs' => 'dienstleistungen/schiesskurse/basic-kurse/basic-kurs',
    'Basic-Kurs-II' => 'dienstleistungen/schiesskurse/basic-kurse/basic-kurs-2-personen',
    'Basic-Kurs-III' => 'dienstleistungen/schiesskurse/basic-kurse/basic-kurs-3-personen',
    'Basic-Kurs-IV' => 'dienstleistungen/schiesskurse/basic-kurse/basic-kurs-4-personen',
    'Instruktor-2-H' => 'dienstleistungen/schiesskurse/privatunterricht/instruktor-2-stunden'
];

echo "=== Creating SEO URLs for Training Products ===\n\n";

foreach ($seoUrls as $productNumber => $seoPath) {
    $stmt = $pdo->prepare("SELECT LOWER(HEX(id)) as id FROM product WHERE product_number = ?");
    $stmt->execute([$productNumber]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo "WARNING: Product '$productNumber' not found!\n";
        continue;
    }

    $productId = $product['id'];
    $now = date('Y-m-d H:i:s.v');
    $seoUrlId = bin2hex(random_bytes(16));

    // Delete existing
    $stmt = $pdo->prepare("
        DELETE FROM seo_url
        WHERE LOWER(HEX(foreign_key)) = ?
        AND route_name = 'frontend.detail.page'
    ");
    $stmt->execute([$productId]);

    // Insert new
    $stmt = $pdo->prepare("
        INSERT INTO seo_url (id, language_id, sales_channel_id, foreign_key, route_name, path_info, seo_path_info, is_canonical, is_modified, is_deleted, created_at)
        VALUES (UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), 'frontend.detail.page', ?, ?, 1, 0, 0, ?)
    ");

    $pathInfo = '/detail/' . $productId;
    $stmt->execute([$seoUrlId, $languageId, $salesChannelId, $productId, $pathInfo, $seoPath, $now]);

    echo "Created: /$seoPath/ -> $productNumber\n";
}

echo "\n=== Done ===\n";
```

**Step 2: Run the script**

```bash
scp scripts/create-training-seo-urls.php root@77.42.19.154:/tmp/
ssh root@77.42.19.154 "docker cp /tmp/create-training-seo-urls.php shopware-chf:/tmp/"
ssh root@77.42.19.154 "docker exec shopware-chf php /tmp/create-training-seo-urls.php"
```

---

## Task 6: Update Header Navigation

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig`

**Step 1: Read current header template**

Check the existing header structure and add Dienstleistungen menu item.

**Step 2: Add Dienstleistungen to navigation**

Add after existing navigation items:

```twig
{# Dienstleistungen Dropdown #}
<div class="nav-item dropdown">
    <a href="{{ seoUrl('frontend.navigation.page', { navigationId: dienstleistungenCategoryId }) }}"
       class="nav-link dropdown-toggle"
       data-bs-toggle="dropdown">
        Dienstleistungen
    </a>
    <div class="dropdown-menu">
        <a class="dropdown-item" href="/dienstleistungen/schiesskurse/">
            Schiesskurse
        </a>
        <div class="dropdown-submenu">
            <a class="dropdown-item" href="/dienstleistungen/schiesskurse/basic-kurse/">
                Basic-Kurse
            </a>
            <a class="dropdown-item" href="/dienstleistungen/schiesskurse/privatunterricht/">
                Privatunterricht
            </a>
        </div>
    </div>
</div>
```

**Step 3: Deploy theme changes**

```bash
scp -r shopware-theme/RavenTheme root@77.42.19.154:/tmp/
ssh root@77.42.19.154 "docker cp /tmp/RavenTheme shopware-chf:/var/www/html/custom/plugins/"
ssh root@77.42.19.154 "docker exec shopware-chf bash -c 'cd /var/www/html && bin/console theme:compile && bin/console cache:clear'"
```

---

## Task 7: Final Verification

**Step 1: Test category pages**

```
https://ravenweapon.ch/dienstleistungen/
https://ravenweapon.ch/dienstleistungen/schiesskurse/
https://ravenweapon.ch/dienstleistungen/schiesskurse/basic-kurse/
https://ravenweapon.ch/dienstleistungen/schiesskurse/privatunterricht/
```

**Step 2: Test product pages**

```
https://ravenweapon.ch/dienstleistungen/schiesskurse/basic-kurse/basic-kurs/
https://ravenweapon.ch/dienstleistungen/schiesskurse/basic-kurse/basic-kurs-2-personen/
https://ravenweapon.ch/dienstleistungen/schiesskurse/basic-kurse/basic-kurs-3-personen/
https://ravenweapon.ch/dienstleistungen/schiesskurse/basic-kurse/basic-kurs-4-personen/
```

**Step 3: Verify breadcrumbs**

Each product page should show:
```
Home > Dienstleistungen > Schiesskurse > Basic-Kurse > [Product Name]
```

**Step 4: Test header navigation**

- Hover over "Dienstleistungen" should show dropdown
- All links should work correctly

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | Create category hierarchy | `scripts/create-dienstleistungen-categories.php` |
| 2 | Generate category SEO URLs | `scripts/create-dienstleistungen-seo-urls.php` |
| 3 | Create 5 training products | `scripts/create-training-products.php` |
| 4 | Upload product images | `scripts/upload-training-images.php` |
| 5 | Generate product SEO URLs | `scripts/create-training-seo-urls.php` |
| 6 | Update header navigation | `header.html.twig` |
| 7 | Final verification | Manual testing |
