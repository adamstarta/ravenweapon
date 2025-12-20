<?php
/**
 * Populate Variant Custom Fields for Raven Weapons and Snigel Products
 *
 * This script:
 * 1. Creates custom field definitions if they don't exist
 * 2. Populates raven_variant_options for Lockhart Tactical products based on image filenames
 * 3. Populates snigel variant options from JSON data
 *
 * Run from scripts folder: php populate-variant-custom-fields.php
 */

// Database connection - Production (inside Docker container)
$host = '127.0.0.1';
$dbname = 'shopware';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to database\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

// Raven color definitions - patterns to match in image filenames
$ravenColorDefinitions = [
    ['name' => 'Graphite Black', 'patterns' => ['graphite', 'black']],
    ['name' => 'Flat Dark Earth', 'patterns' => ['flat-dark-earth', 'earth', 'fde']],
    ['name' => 'Northern Lights', 'patterns' => ['northenlights', 'northern', 'lights']],
    ['name' => 'Olive Drab Green', 'patterns' => ['olive-drab-green', 'olive', 'green']],
    ['name' => 'Sniper Grey', 'patterns' => ['sniper', 'grey', 'gray', 'silver']],
];

// ============================================================
// STEP 1: Create Custom Field Set if not exists
// ============================================================
echo "\n=== STEP 1: Checking Custom Field Set ===\n";

$customFieldSetId = null;
$stmt = $pdo->query("SELECT id, LOWER(HEX(id)) as hex_id FROM custom_field_set WHERE name = 'product_variants'");
$existingSet = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingSet) {
    $customFieldSetId = $existingSet['hex_id'];
    echo "Custom field set 'product_variants' already exists: $customFieldSetId\n";
} else {
    // Create new custom field set
    $customFieldSetId = bin2hex(random_bytes(16));
    $now = date('Y-m-d H:i:s.v');

    $stmt = $pdo->prepare("INSERT INTO custom_field_set (id, name, config, active, position, created_at) VALUES (UNHEX(?), ?, ?, 1, 1, ?)");
    $stmt->execute([$customFieldSetId, 'product_variants', '{}', $now]);

    // Link to product entity
    $relationId = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("INSERT INTO custom_field_set_relation (id, custom_field_set_id, entity_name, created_at) VALUES (UNHEX(?), UNHEX(?), 'product', ?)");
    $stmt->execute([$relationId, $customFieldSetId, $now]);

    echo "Created custom field set 'product_variants': $customFieldSetId\n";
}

// ============================================================
// STEP 2: Create Custom Fields if not exist
// ============================================================
echo "\n=== STEP 2: Checking Custom Fields ===\n";

$customFields = [
    // Raven variants
    ['name' => 'raven_has_variants', 'type' => 'bool', 'label' => 'Hat Raven Varianten'],
    ['name' => 'raven_variant_options', 'type' => 'json', 'label' => 'Raven Varianten Optionen'],
    // Snigel color variants
    ['name' => 'snigel_has_color_variants', 'type' => 'bool', 'label' => 'Hat Snigel Farb-Varianten'],
    ['name' => 'snigel_color_options', 'type' => 'json', 'label' => 'Snigel Farb-Optionen'],
    ['name' => 'snigel_color_label', 'type' => 'text', 'label' => 'Snigel Farb-Label'],
    ['name' => 'snigel_color_count', 'type' => 'int', 'label' => 'Snigel Farb-Anzahl'],
    // Snigel size variants
    ['name' => 'snigel_has_sizes', 'type' => 'bool', 'label' => 'Hat Snigel Grössen'],
    ['name' => 'snigel_size_options', 'type' => 'text', 'label' => 'Snigel Grössen'],
    ['name' => 'snigel_variants', 'type' => 'json', 'label' => 'Snigel Varianten mit Preisen'],
    ['name' => 'snigel_variant_type', 'type' => 'text', 'label' => 'Snigel Varianten-Typ'],
];

foreach ($customFields as $field) {
    $stmt = $pdo->prepare("SELECT id FROM custom_field WHERE name = ?");
    $stmt->execute([$field['name']]);

    if ($stmt->fetch()) {
        echo "Custom field '{$field['name']}' already exists\n";
    } else {
        $fieldId = bin2hex(random_bytes(16));
        $now = date('Y-m-d H:i:s.v');

        $config = json_encode([
            'type' => $field['type'],
            'label' => ['de-DE' => $field['label'], 'en-GB' => $field['label']],
            'componentName' => $field['type'] === 'json' ? 'sw-text-editor' : ($field['type'] === 'bool' ? 'sw-checkbox' : 'sw-text-field'),
            'customFieldType' => $field['type'] === 'json' ? 'textEditor' : ($field['type'] === 'bool' ? 'checkbox' : 'text'),
        ]);

        $stmt = $pdo->prepare("INSERT INTO custom_field (id, name, type, config, active, set_id, created_at) VALUES (UNHEX(?), ?, ?, ?, 1, UNHEX(?), ?)");
        $stmt->execute([$fieldId, $field['name'], $field['type'], $config, $customFieldSetId, $now]);

        echo "Created custom field '{$field['name']}'\n";
    }
}

// ============================================================
// STEP 3: Populate Raven Weapons variant options
// ============================================================
echo "\n=== STEP 3: Populating Raven Weapons Variants ===\n";

// Get Lockhart Tactical manufacturer ID
$stmt = $pdo->query("SELECT LOWER(HEX(product_manufacturer_id)) as id FROM product_manufacturer_translation WHERE name = 'Lockhart Tactical'");
$manufacturer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$manufacturer) {
    echo "WARNING: Lockhart Tactical manufacturer not found\n";
} else {
    $manufacturerId = $manufacturer['id'];
    echo "Found Lockhart Tactical manufacturer: $manufacturerId\n";

    // Get all Lockhart Tactical products with their images
    $stmt = $pdo->prepare("
        SELECT
            LOWER(HEX(p.id)) as product_id,
            pt.name as product_name,
            pt.custom_fields
        FROM product p
        JOIN product_translation pt ON p.id = pt.product_id
        JOIN product_manufacturer pm ON p.product_manufacturer_id = pm.id
        JOIN product_manufacturer_translation pmt ON pm.id = pmt.product_manufacturer_id
        WHERE pmt.name = 'Lockhart Tactical'
        AND p.parent_id IS NULL
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($products) . " Lockhart Tactical products\n";

    foreach ($products as $product) {
        $productId = $product['product_id'];
        $productName = $product['product_name'];

        // Get product images
        $stmt = $pdo->prepare("
            SELECT
                m.path,
                m.file_name
            FROM product_media pm
            JOIN media m ON pm.media_id = m.id
            WHERE LOWER(HEX(pm.product_id)) = ?
            ORDER BY pm.position
        ");
        $stmt->execute([$productId]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($images) < 2) {
            echo "  - $productName: Only " . count($images) . " image(s), skipping variants\n";
            continue;
        }

        // Match images to colors
        $variantOptions = [];
        foreach ($ravenColorDefinitions as $colorDef) {
            foreach ($images as $image) {
                $fileName = strtolower($image['file_name'] ?? '');
                // Path already includes 'media/' prefix from Shopware
                $path = $image['path'];
                $url = '/' . $path;

                foreach ($colorDef['patterns'] as $pattern) {
                    if (strpos($fileName, $pattern) !== false) {
                        $variantOptions[] = [
                            'name' => $colorDef['name'],
                            'imageUrl' => $url,
                            'thumbUrl' => $url,
                        ];
                        break 2; // Found match, go to next color
                    }
                }
            }
        }

        if (count($variantOptions) < 2) {
            echo "  - $productName: Only " . count($variantOptions) . " color(s) matched, skipping\n";
            continue;
        }

        // Update custom fields in product_translation
        $existingCustomFields = json_decode($product['custom_fields'] ?? '{}', true) ?: [];
        $existingCustomFields['raven_has_variants'] = true;
        $existingCustomFields['raven_variant_options'] = $variantOptions;

        $stmt = $pdo->prepare("UPDATE product_translation SET custom_fields = ? WHERE LOWER(HEX(product_id)) = ?");
        $stmt->execute([json_encode($existingCustomFields), $productId]);

        echo "  - $productName: Added " . count($variantOptions) . " color variants\n";
        foreach ($variantOptions as $vo) {
            echo "      * {$vo['name']}\n";
        }
    }
}

// ============================================================
// STEP 4: Populate Snigel variant options from JSON
// ============================================================
echo "\n=== STEP 4: Populating Snigel Variants from JSON ===\n";

$jsonPath = __DIR__ . '/snigel-b2b-data/snigel-b2b-prices.json';
if (!file_exists($jsonPath)) {
    echo "WARNING: snigel-b2b-prices.json not found at $jsonPath\n";
} else {
    $snigelData = json_decode(file_get_contents($jsonPath), true);
    echo "Loaded " . count($snigelData) . " products from JSON\n";

    $updated = 0;
    $skipped = 0;

    foreach ($snigelData as $snigelProduct) {
        $slug = $snigelProduct['slug'] ?? '';
        $hasVariants = $snigelProduct['hasVariants'] ?? false;
        $variants = $snigelProduct['variants'] ?? [];
        $dropdownTypes = $snigelProduct['dropdownTypes'] ?? [];
        $variantLabels = $snigelProduct['variantLabels'] ?? [];

        if (!$hasVariants || empty($variants)) {
            $skipped++;
            continue;
        }

        // Find product by SKU pattern (SN-{slug})
        $sku = 'SN-' . $slug;
        $stmt = $pdo->prepare("
            SELECT
                LOWER(HEX(p.id)) as product_id,
                pt.name as product_name,
                pt.custom_fields
            FROM product p
            JOIN product_translation pt ON p.id = pt.product_id
            WHERE p.product_number = ?
            AND p.parent_id IS NULL
        ");
        $stmt->execute([$sku]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            continue; // Product not in Shopware
        }

        $productId = $product['product_id'];
        $productName = $product['product_name'];
        $existingCustomFields = json_decode($product['custom_fields'] ?? '{}', true) ?: [];

        // Determine variant type
        $hasColors = in_array('colour', $dropdownTypes);
        $hasSizes = in_array('sizes', $dropdownTypes);

        // Get correct label for size selector (find index of 'sizes' in dropdownTypes)
        $sizeIndex = array_search('sizes', $dropdownTypes);
        $colorIndex = array_search('colour', $dropdownTypes);
        $sizeLabel = ($sizeIndex !== false && isset($variantLabels[$sizeIndex])) ? $variantLabels[$sizeIndex] : 'GRÖSSE';
        $colorLabel = ($colorIndex !== false && isset($variantLabels[$colorIndex])) ? $variantLabels[$colorIndex] : 'FARBE';

        // Build size options string (unique sizes)
        // Also extract color-to-image mappings
        $sizes = [];
        $colors = [];
        $colorImages = []; // Map color name to image URL
        foreach ($variants as $v) {
            if (isset($v['sizes'])) $sizes[$v['sizes']] = true;
            if (isset($v['colour'])) {
                $colors[$v['colour']] = true;
                // Store image URL for this color (if available)
                if (isset($v['imageUrl']) && !isset($colorImages[$v['colour']])) {
                    $colorImages[$v['colour']] = $v['imageUrl'];
                }
            }
        }

        // Update custom fields based on variant type
        if ($hasSizes) {
            $existingCustomFields['snigel_has_sizes'] = true;

            // Store size options as JSON array of objects (expected by JavaScript)
            $sizeOptionsArray = [];
            foreach (array_keys($sizes) as $sizeName) {
                $sizeOptionsArray[] = ['name' => $sizeName, 'value' => strtolower($sizeName)];
            }
            $existingCustomFields['snigel_size_options'] = json_encode($sizeOptionsArray);
            $existingCustomFields['snigel_variant_type'] = $sizeLabel; // Use correct size label

            // Store full variants with prices (for price lookup)
            $variantData = [];
            foreach ($variants as $v) {
                $variantData[] = [
                    'name' => $v['name'],
                    'sellingPriceCHF' => $v['sellingPriceCHF'] ?? 0,
                ];
            }
            $existingCustomFields['snigel_variants'] = json_encode($variantData);
        }

        // Always save color info if colors exist (even just 1)
        // Template will decide: 1 color = info text, 2+ colors = selector buttons
        if ($hasColors && count($colors) >= 1) {
            $existingCustomFields['snigel_has_color_variants'] = true;
            $existingCustomFields['snigel_color_label'] = $colorLabel;
            $existingCustomFields['snigel_color_count'] = count($colors); // Store count for template logic
            // Color options array with image URLs
            $colorOptions = [];
            foreach (array_keys($colors) as $colorName) {
                $colorOption = ['name' => $colorName];
                // Add image URL if available from scraper
                if (isset($colorImages[$colorName])) {
                    $colorOption['imageUrl'] = $colorImages[$colorName];
                }
                $colorOptions[] = $colorOption;
            }
            $existingCustomFields['snigel_color_options'] = $colorOptions;
        }

        $stmt = $pdo->prepare("UPDATE product_translation SET custom_fields = ? WHERE LOWER(HEX(product_id)) = ?");
        $stmt->execute([json_encode($existingCustomFields), $productId]);

        $updated++;
        $info = [];
        if ($hasSizes) $info[] = count($sizes) . " sizes";
        if ($hasColors) $info[] = count($colors) . " colors";
        echo "  - $productName: " . implode(', ', $info) . "\n";
    }

    echo "\nSnigel: Updated $updated products, skipped $skipped without variants\n";
}

// ============================================================
// STEP 5: Clear cache reminder
// ============================================================
echo "\n=== DONE ===\n";
echo "Custom fields populated!\n\n";
echo "Now run on server:\n";
echo "  docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'\n\n";
