<?php
/**
 * Shopware - Create Snigel English Subcategories & Assign Products
 *
 * Creates exactly 19 categories matching the B2B site (English names)
 * and assigns products based on products-with-variants.json
 *
 * Run on server: docker exec shopware-chf php /tmp/create-snigel-english-categories.php
 */

$API_URL = 'http://localhost';
$PRODUCTS_JSON = '/tmp/products-with-variants.json';

// ============ EXACT 19 CATEGORIES FROM B2B SITE ============
$ENGLISH_CATEGORIES = [
    'Tactical Gear',
    'Tactical Clothing',
    'Vests & Chest Rigs',
    'Bags & Backpacks',
    'Belts',
    'Ballistic Protection',
    'Slings & Holsters',
    'Medical Gear',
    'Police Gear',
    'Admin Gear',
    'Holders & Pouches',
    'Patches',
    'K9 Gear',
    'Leg Panels',
    'Duty Gear',
    'Covert Gear',
    'Sniper Gear',
    'Source Hydration',
    'Miscellaneous',
];

// ============ NORMALIZE CATEGORY NAMES FROM JSON ============
// Maps variations in JSON data to the standard 19 category names
$CATEGORY_NORMALIZE = [
    // Tactical Gear variations
    'Tactical Gear' => 'Tactical Gear',
    'Tactical gear' => 'Tactical Gear',

    // Tactical Clothing variations
    'Tactical Clothing' => 'Tactical Clothing',
    'Tactical clothing' => 'Tactical Clothing',

    // Vests & Chest Rigs variations
    'Vests & Chest Rigs' => 'Vests & Chest Rigs',
    'Vests & Chest rigs' => 'Vests & Chest Rigs',
    'Vests &amp; Chest Rigs' => 'Vests & Chest Rigs',
    'Vests &amp; Chest rigs' => 'Vests & Chest Rigs',

    // Bags & Backpacks variations
    'Bags & Backpacks' => 'Bags & Backpacks',
    'Bags & backpacks' => 'Bags & Backpacks',
    'Bags &amp; Backpacks' => 'Bags & Backpacks',
    'Bags &amp; backpacks' => 'Bags & Backpacks',

    // Belts
    'Belts' => 'Belts',

    // Ballistic Protection variations
    'Ballistic Protection' => 'Ballistic Protection',
    'Ballistic protection' => 'Ballistic Protection',

    // Slings & Holsters variations
    'Slings & Holsters' => 'Slings & Holsters',
    'Slings & holsters' => 'Slings & Holsters',
    'Slings &amp; Holsters' => 'Slings & Holsters',
    'Slings &amp; holsters' => 'Slings & Holsters',

    // Medical Gear variations
    'Medical Gear' => 'Medical Gear',
    'Medical gear' => 'Medical Gear',

    // Police Gear variations
    'Police Gear' => 'Police Gear',
    'Police gear' => 'Police Gear',

    // Admin Gear variations (maps Admin products too)
    'Admin Gear' => 'Admin Gear',
    'Admin gear' => 'Admin Gear',
    'Admin products' => 'Admin Gear',

    // Holders & Pouches variations
    'Holders & Pouches' => 'Holders & Pouches',
    'Holders & pouches' => 'Holders & Pouches',
    'Holders &amp; Pouches' => 'Holders & Pouches',
    'Holders &amp; pouches' => 'Holders & Pouches',

    // Patches
    'Patches' => 'Patches',

    // K9 Gear variations
    'K9 Gear' => 'K9 Gear',
    'K9 gear' => 'K9 Gear',
    'K9-units gear' => 'K9 Gear',
    'K9 Units Gear' => 'K9 Gear',

    // Leg Panels variations
    'Leg Panels' => 'Leg Panels',
    'Leg panels' => 'Leg Panels',

    // Duty Gear variations
    'Duty Gear' => 'Duty Gear',
    'Duty gear' => 'Duty Gear',

    // Covert Gear variations
    'Covert Gear' => 'Covert Gear',
    'Covert gear' => 'Covert Gear',

    // Sniper Gear variations
    'Sniper Gear' => 'Sniper Gear',
    'Sniper gear' => 'Sniper Gear',

    // Source Hydration variations
    'Source Hydration' => 'Source Hydration',
    'Source® Hydration' => 'Source Hydration',
    'Source® hydration' => 'Source Hydration',
    'Source hydration' => 'Source Hydration',

    // Miscellaneous variations
    'Miscellaneous' => 'Miscellaneous',
    'Miscellaneous products' => 'Miscellaneous',

    // Map extras to Miscellaneous
    'The Brand' => 'Miscellaneous',
    'HighVis' => 'Miscellaneous',
    'Highvis' => 'Miscellaneous',
    'Multicam' => 'Miscellaneous',
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

// ============ MAIN SCRIPT ============

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  SHOPWARE - CREATE 19 SNIGEL ENGLISH SUBCATEGORIES        ║\n";
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

// ============ STEP 2: Create English subcategories ============
echo "Step 2: Creating 19 English subcategories...\n";

$categoryIds = []; // English name => ID

foreach ($ENGLISH_CATEGORIES as $catName) {
    // Check if already exists
    $result = apiRequest($API_URL, $token, 'POST', 'search/category', [
        'filter' => [
            ['type' => 'equals', 'field' => 'name', 'value' => $catName],
            ['type' => 'equals', 'field' => 'parentId', 'value' => $snigelParentId]
        ]
    ]);

    $existingId = null;
    foreach ($result['data']['data'] ?? [] as $cat) {
        $existingId = $cat['id'];
        break;
    }

    if ($existingId) {
        $categoryIds[$catName] = $existingId;
        echo "  EXISTS: $catName\n";
    } else {
        // Create new subcategory
        $newId = bin2hex(random_bytes(16));
        $createResult = apiRequest($API_URL, $token, 'POST', 'category', [
            'id' => $newId,
            'name' => $catName,
            'parentId' => $snigelParentId,
            'active' => true,
            'displayNestedProducts' => true,
            'type' => 'page'
        ]);

        if ($createResult['code'] >= 200 && $createResult['code'] < 300) {
            $categoryIds[$catName] = $newId;
            echo "  CREATED: $catName\n";
        } else {
            $error = $createResult['data']['errors'][0]['detail'] ?? 'Unknown error';
            echo "  FAILED: $catName - $error\n";
        }
    }
}

echo "\n  Total categories: " . count($categoryIds) . " (should be 19)\n\n";

// ============ STEP 3: Assign products to categories ============
echo "Step 3: Assigning products to categories...\n\n";

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

    // Get category from JSON
    $jsonCat = $product['category'] ?? '';
    if (empty($jsonCat) && !empty($product['categories'])) {
        $jsonCat = $product['categories'][0] ?? '';
    }
    $jsonCat = html_entity_decode(trim($jsonCat), ENT_QUOTES, 'UTF-8');

    if (empty($jsonCat)) {
        echo "NO CATEGORY\n";
        $noCategory++;
        continue;
    }

    // Normalize to standard English name
    $englishCat = $CATEGORY_NORMALIZE[$jsonCat] ?? null;

    // Try case-insensitive if not found
    if (!$englishCat) {
        foreach ($CATEGORY_NORMALIZE as $key => $val) {
            if (strcasecmp($key, $jsonCat) === 0) {
                $englishCat = $val;
                break;
            }
        }
    }

    if (!$englishCat) {
        echo "UNMAPPED: $jsonCat\n";
        $noCategory++;
        continue;
    }

    $catId = $categoryIds[$englishCat] ?? null;
    if (!$catId) {
        echo "NO CAT ID: $englishCat\n";
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
            ['id' => $snigelParentId],
            ['id' => $catId]
        ]
    ]);

    if ($updateResult['code'] >= 200 && $updateResult['code'] < 300) {
        echo "OK → $englishCat\n";
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
printf("║  Categories created:  %-35s ║\n", count($categoryIds) . " (target: 19)");
printf("║  Products updated:    %-35s ║\n", $updated);
printf("║  Not found in shop:   %-35s ║\n", $notFound);
printf("║  No/unmapped category:%-35s ║\n", $noCategory);
printf("║  Errors:              %-35s ║\n", $errors);
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "Done! Now run: bin/console cache:clear\n\n";
