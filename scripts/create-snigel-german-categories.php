<?php
/**
 * Shopware - Create Snigel German Subcategories & Assign Products
 *
 * This script:
 * 1. Creates 19 German subcategories under Snigel
 * 2. Maps English category names from JSON to German names
 * 3. Assigns products to correct German categories
 *
 * Run on server: docker exec shopware-chf php /tmp/create-snigel-german-categories.php
 */

$API_URL = 'http://localhost';
$PRODUCTS_JSON = '/tmp/products-with-variants.json';

// ============ ENGLISH → GERMAN CATEGORY MAPPING ============
$CATEGORY_MAP = [
    'Tactical Gear' => 'Taktische Ausrüstung',
    'Tactical gear' => 'Taktische Ausrüstung',
    'Tactical Clothing' => 'Taktische Bekleidung',
    'Tactical clothing' => 'Taktische Bekleidung',
    'Vests & Chest Rigs' => 'Westen & Chest Rigs',
    'Vests & Chest rigs' => 'Westen & Chest Rigs',
    'Vests &amp; Chest Rigs' => 'Westen & Chest Rigs',
    'Bags & Backpacks' => 'Taschen & Rucksäcke',
    'Bags & backpacks' => 'Taschen & Rucksäcke',
    'Bags &amp; Backpacks' => 'Taschen & Rucksäcke',
    'Belts' => 'Gürtel',
    'Ballistic Protection' => 'Ballistischer Schutz',
    'Ballistic protection' => 'Ballistischer Schutz',
    'Slings & Holsters' => 'Tragegurte & Holster',
    'Slings & holsters' => 'Tragegurte & Holster',
    'Slings &amp; Holsters' => 'Tragegurte & Holster',
    'Medical Gear' => 'Medizinische Ausrüstung',
    'Medical gear' => 'Medizinische Ausrüstung',
    'Police Gear' => 'Polizeiausrüstung',
    'Police gear' => 'Polizeiausrüstung',
    'Admin Gear' => 'Verwaltungsausrüstung',
    'Admin gear' => 'Verwaltungsausrüstung',
    'Holders & Pouches' => 'Holster & Taschen',
    'Holders & pouches' => 'Holster & Taschen',
    'Holders &amp; Pouches' => 'Holster & Taschen',
    'Patches' => 'Patches',
    'K9 Gear' => 'K9-Ausrüstung',
    'K9 gear' => 'K9-Ausrüstung',
    'K9-units gear' => 'K9-Ausrüstung',
    'Leg Panels' => 'Beinpanels',
    'Leg panels' => 'Beinpanels',
    'Duty Gear' => 'Dienstausrüstung',
    'Duty gear' => 'Dienstausrüstung',
    'Covert Gear' => 'Verdeckte Ausrüstung',
    'Covert gear' => 'Verdeckte Ausrüstung',
    'Sniper Gear' => 'Scharfschützen-Ausrüstung',
    'Sniper gear' => 'Scharfschützen-Ausrüstung',
    'Source® Hydration' => 'Source® Hydration',
    'Source® hydration' => 'Source® Hydration',
    'Source Hydration' => 'Source® Hydration',
    'HighVis' => 'Warnschutz',
    'Highvis' => 'Warnschutz',
    'High Vis' => 'Warnschutz',
    'Miscellaneous' => 'Verschiedenes',
    'Miscellaneous products' => 'Verschiedenes',
    'Multicam' => 'Multicam',
];

// All 19 German categories to create
$GERMAN_CATEGORIES = [
    'Taktische Ausrüstung',
    'Taktische Bekleidung',
    'Westen & Chest Rigs',
    'Taschen & Rucksäcke',
    'Gürtel',
    'Ballistischer Schutz',
    'Tragegurte & Holster',
    'Medizinische Ausrüstung',
    'Polizeiausrüstung',
    'Verwaltungsausrüstung',
    'Holster & Taschen',
    'Patches',
    'K9-Ausrüstung',
    'Beinpanels',
    'Dienstausrüstung',
    'Verdeckte Ausrüstung',
    'Scharfschützen-Ausrüstung',
    'Source® Hydration',
    'Verschiedenes',
    'Warnschutz',
    'Multicam',
];

// ============ API HELPER FUNCTIONS ============

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => 'admin',
            'password' => 'shopware'
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true)['access_token'] ?? null;
}

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

function slugify($text) {
    $text = mb_strtolower($text, 'UTF-8');
    $text = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

// ============ MAIN SCRIPT ============

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  SHOPWARE - CREATE SNIGEL GERMAN SUBCATEGORIES            ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Load products JSON
if (!file_exists($PRODUCTS_JSON)) {
    die("Error: $PRODUCTS_JSON not found\n");
}

$products = json_decode(file_get_contents($PRODUCTS_JSON), true);
echo "Loaded " . count($products) . " products from JSON\n\n";

// Get token
$token = getToken($API_URL);
if (!$token) {
    die("Error: Failed to get API token\n");
}
echo "Got API token\n\n";

// ============ STEP 1: Find Snigel parent category ============
echo "Step 1: Finding Snigel parent category...\n";

$result = apiRequest($API_URL, $token, 'POST', 'search/category', [
    'filter' => [
        ['type' => 'equals', 'field' => 'name', 'value' => 'Snigel']
    ]
]);

$snigelParentId = null;
foreach ($result['data']['data'] ?? [] as $cat) {
    $name = $cat['name'] ?? $cat['attributes']['name'] ?? '';
    if ($name === 'Snigel') {
        $snigelParentId = $cat['id'];
        break;
    }
}

if (!$snigelParentId) {
    die("Error: Snigel parent category not found\n");
}
echo "  Found Snigel parent: $snigelParentId\n\n";

// ============ STEP 2: Create German subcategories ============
echo "Step 2: Creating German subcategories...\n";

$germanCategoryIds = []; // German name => ID

foreach ($GERMAN_CATEGORIES as $germanName) {
    // Check if already exists
    $result = apiRequest($API_URL, $token, 'POST', 'search/category', [
        'filter' => [
            ['type' => 'equals', 'field' => 'name', 'value' => $germanName],
            ['type' => 'equals', 'field' => 'parentId', 'value' => $snigelParentId]
        ]
    ]);

    $existingId = null;
    foreach ($result['data']['data'] ?? [] as $cat) {
        $existingId = $cat['id'];
        break;
    }

    if ($existingId) {
        $germanCategoryIds[$germanName] = $existingId;
        echo "  EXISTS: $germanName\n";
    } else {
        // Create new subcategory
        $newId = bin2hex(random_bytes(16));
        $createResult = apiRequest($API_URL, $token, 'POST', 'category', [
            'id' => $newId,
            'name' => $germanName,
            'parentId' => $snigelParentId,
            'active' => true,
            'displayNestedProducts' => true,
            'cmsPageId' => null,
            'type' => 'page'
        ]);

        if ($createResult['code'] >= 200 && $createResult['code'] < 300) {
            $germanCategoryIds[$germanName] = $newId;
            echo "  CREATED: $germanName\n";
        } else {
            $error = $createResult['data']['errors'][0]['detail'] ?? 'Unknown error';
            echo "  FAILED: $germanName - $error\n";
        }
    }
}

echo "\n  Total German categories: " . count($germanCategoryIds) . "\n\n";

// ============ STEP 3: Assign products to categories ============
echo "Step 3: Assigning products to German categories...\n\n";

$updated = 0;
$notFound = 0;
$noCategory = 0;
$errors = 0;
$total = count($products);

foreach ($products as $i => $product) {
    $num = $i + 1;
    $sku = !empty($product['article_no']) ? $product['article_no'] : 'SN-' . $product['slug'];
    $displayName = mb_substr($product['name'], 0, 30);

    echo "[$num/$total] $displayName... ";

    // Get English category from JSON
    $englishCat = $product['category'] ?? '';
    if (empty($englishCat) && !empty($product['categories'])) {
        $englishCat = $product['categories'][0] ?? '';
    }
    $englishCat = html_entity_decode(trim($englishCat), ENT_QUOTES, 'UTF-8');

    if (empty($englishCat)) {
        echo "NO CATEGORY\n";
        $noCategory++;
        continue;
    }

    // Map to German
    $germanCat = $CATEGORY_MAP[$englishCat] ?? null;
    if (!$germanCat) {
        // Try case-insensitive search
        foreach ($CATEGORY_MAP as $eng => $ger) {
            if (strcasecmp($eng, $englishCat) === 0) {
                $germanCat = $ger;
                break;
            }
        }
    }

    if (!$germanCat) {
        echo "UNMAPPED: $englishCat\n";
        $noCategory++;
        continue;
    }

    $germanCatId = $germanCategoryIds[$germanCat] ?? null;
    if (!$germanCatId) {
        echo "NO CAT ID: $germanCat\n";
        $errors++;
        continue;
    }

    // Find product by SKU
    $result = apiRequest($API_URL, $token, 'POST', 'search/product', [
        'filter' => [
            ['type' => 'equals', 'field' => 'productNumber', 'value' => $sku]
        ]
    ]);

    $productId = $result['data']['data'][0]['id'] ?? null;

    if (!$productId) {
        echo "NOT FOUND\n";
        $notFound++;
        continue;
    }

    // Update product categories
    $updateResult = apiRequest($API_URL, $token, 'PATCH', "product/$productId", [
        'categories' => [
            ['id' => $snigelParentId],    // Keep in main Snigel
            ['id' => $germanCatId]         // Add German subcategory
        ]
    ]);

    if ($updateResult['code'] >= 200 && $updateResult['code'] < 300) {
        echo "OK → $germanCat\n";
        $updated++;
    } else {
        echo "ERROR\n";
        $errors++;
    }
}

// ============ SUMMARY ============
echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                      COMPLETE                              ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
printf("║  Products updated:    %-35s ║\n", $updated);
printf("║  Not found in shop:   %-35s ║\n", $notFound);
printf("║  No/unmapped category:%-35s ║\n", $noCategory);
printf("║  Errors:              %-35s ║\n", $errors);
printf("║  German categories:   %-35s ║\n", count($germanCategoryIds));
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "Done! Now run: bin/console cache:clear\n\n";
