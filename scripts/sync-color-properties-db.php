<?php
/**
 * Sync Color Properties from Custom Fields to Shopware Properties (Direct DB)
 *
 * This script:
 * 1. Creates "Farbe" property group (if not exists)
 * 2. Creates color options (Black, Grey, Multicam, etc.)
 * 3. Reads snigel_color_options from product custom fields
 * 4. Assigns color properties to products
 *
 * Run: php sync-color-properties-db.php
 */

// Database connection
$host = '127.0.0.1';
$dbname = 'shopware';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

echo "=== Sync Color Properties (Direct DB) ===\n\n";
echo "[OK] Connected to database\n";

// Actual colors whitelist (no sizes)
$COLOR_WHITELIST = [
    'Black',
    'Clear',
    'Coyote',
    'Grey',
    'HighVis yellow',
    'Khaki',
    'Multicam',
    'Navy',
    'Olive',
    'Ranger Green',
    'Swecam',
    'Various',
    'White'
];

function generateUuid() {
    return strtolower(sprintf('%04x%04x%04x%04x%04x%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    ));
}

// Get default language ID (English)
$langStmt = $pdo->query("SELECT LOWER(HEX(id)) as id FROM language WHERE name = 'English' OR name = 'Deutsch' LIMIT 1");
$defaultLangId = $langStmt->fetchColumn();
if (!$defaultLangId) {
    $langStmt = $pdo->query("SELECT LOWER(HEX(id)) as id FROM language LIMIT 1");
    $defaultLangId = $langStmt->fetchColumn();
}
echo "[OK] Using language ID: $defaultLangId\n";

// Step 1: Check/Create "Farbe" property group
echo "\n[1] Checking for Farbe property group...\n";

$stmt = $pdo->prepare("
    SELECT LOWER(HEX(pg.id)) as id
    FROM property_group pg
    JOIN property_group_translation pgt ON pg.id = pgt.property_group_id
    WHERE pgt.name = 'Farbe'
    LIMIT 1
");
$stmt->execute();
$propertyGroupId = $stmt->fetchColumn();

if ($propertyGroupId) {
    echo "    Found existing: $propertyGroupId\n";
} else {
    echo "    Creating Farbe property group...\n";
    $propertyGroupId = generateUuid();
    $now = date('Y-m-d H:i:s');

    $pdo->prepare("
        INSERT INTO property_group (id, display_type, sorting_type, filterable, visible_on_product_detail_page, created_at)
        VALUES (UNHEX(?), 'text', 'alphanumeric', 1, 1, ?)
    ")->execute([$propertyGroupId, $now]);

    $pdo->prepare("
        INSERT INTO property_group_translation (property_group_id, language_id, name, created_at)
        VALUES (UNHEX(?), UNHEX(?), 'Farbe', ?)
    ")->execute([$propertyGroupId, $defaultLangId, $now]);

    echo "    [OK] Created: $propertyGroupId\n";
}

// Step 2: Create color options
echo "\n[2] Creating color options...\n";

$colorOptionIds = [];

// Get existing options
$stmt = $pdo->prepare("
    SELECT LOWER(HEX(pgo.id)) as id, pgot.name
    FROM property_group_option pgo
    JOIN property_group_option_translation pgot ON pgo.id = pgot.property_group_option_id
    WHERE pgo.property_group_id = UNHEX(?)
");
$stmt->execute([$propertyGroupId]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $colorOptionIds[$row['name']] = $row['id'];
}

$now = date('Y-m-d H:i:s');

foreach ($COLOR_WHITELIST as $colorName) {
    if (isset($colorOptionIds[$colorName])) {
        echo "    [EXISTS] $colorName\n";
    } else {
        $optionId = generateUuid();

        try {
            $pdo->prepare("
                INSERT INTO property_group_option (id, property_group_id, created_at)
                VALUES (UNHEX(?), UNHEX(?), ?)
            ")->execute([$optionId, $propertyGroupId, $now]);

            $pdo->prepare("
                INSERT INTO property_group_option_translation (property_group_option_id, language_id, name, created_at)
                VALUES (UNHEX(?), UNHEX(?), ?, ?)
            ")->execute([$optionId, $defaultLangId, $colorName, $now]);

            $colorOptionIds[$colorName] = $optionId;
            echo "    [CREATED] $colorName\n";
        } catch (PDOException $e) {
            echo "    [ERROR] $colorName: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n    Total color options: " . count($colorOptionIds) . "\n";

// Step 3: Get products with color custom fields and assign properties
echo "\n[3] Assigning colors to products...\n";

$stmt = $pdo->query("
    SELECT
        LOWER(HEX(p.id)) as id,
        p.product_number,
        pt.custom_fields
    FROM product p
    JOIN product_translation pt ON p.id = pt.product_id
    WHERE p.parent_id IS NULL
    AND (
        pt.custom_fields LIKE '%snigel_color_options%'
        OR pt.custom_fields LIKE '%raven_variant_options%'
    )
");

$totalUpdated = 0;
$totalSkipped = 0;

while ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $productId = $product['id'];
    $productNumber = $product['product_number'];
    $customFields = json_decode($product['custom_fields'], true) ?: [];

    // Get colors from custom fields
    $productColors = [];

    // From snigel_color_options
    $snigelColors = $customFields['snigel_color_options'] ?? [];
    if (is_array($snigelColors)) {
        foreach ($snigelColors as $colorData) {
            if (is_array($colorData) && isset($colorData['name'])) {
                $productColors[] = $colorData['name'];
            } elseif (is_string($colorData)) {
                $productColors[] = $colorData;
            }
        }
    }

    // From raven_variant_options
    $ravenColors = $customFields['raven_variant_options'] ?? [];
    if (is_array($ravenColors)) {
        foreach ($ravenColors as $variantData) {
            if (is_array($variantData) && isset($variantData['name'])) {
                $productColors[] = $variantData['name'];
            }
        }
    }

    // Filter to only whitelisted colors
    $validColors = array_unique(array_filter($productColors, function($c) use ($COLOR_WHITELIST) {
        return in_array($c, $COLOR_WHITELIST);
    }));

    if (empty($validColors)) {
        $totalSkipped++;
        continue;
    }

    // Assign properties to product
    $assignedCount = 0;
    foreach ($validColors as $colorName) {
        if (!isset($colorOptionIds[$colorName])) continue;

        $optionId = $colorOptionIds[$colorName];

        // Check if already assigned
        $checkStmt = $pdo->prepare("
            SELECT 1 FROM product_property
            WHERE product_id = UNHEX(?) AND property_group_option_id = UNHEX(?)
        ");
        $checkStmt->execute([$productId, $optionId]);

        if (!$checkStmt->fetchColumn()) {
            try {
                $pdo->prepare("
                    INSERT INTO product_property (product_id, property_group_option_id)
                    VALUES (UNHEX(?), UNHEX(?))
                ")->execute([$productId, $optionId]);
                $assignedCount++;
            } catch (PDOException $e) {
                // Ignore duplicate errors
            }
        }
    }

    if ($assignedCount > 0) {
        $totalUpdated++;
        echo "    [OK] $productNumber: " . implode(', ', $validColors) . "\n";
    }
}

// Step 4: Update product version timestamps (for cache invalidation)
echo "\n[4] Updating product timestamps...\n";
$pdo->query("UPDATE product SET updated_at = NOW() WHERE parent_id IS NULL");

echo "\n=== SUMMARY ===\n";
echo "Products updated: $totalUpdated\n";
echo "Products skipped (no colors): $totalSkipped\n";
echo "Color options available: " . count($colorOptionIds) . "\n";
echo "\nDone! Now run: bin/console cache:clear\n";
