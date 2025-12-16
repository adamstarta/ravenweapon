<?php
/**
 * Shopware - Update Snigel Products with Subcategories and Colour
 *
 * This script:
 * 1. Creates Snigel subcategories (Bags & backpacks, Medical gear, etc.)
 * 2. Assigns products to correct subcategories based on scraped data
 * 3. Adds "Farbe" (Colour) property to products for filtering
 *
 * Run on server: docker exec shopware-chf php /tmp/shopware-update-snigel-categories.php
 */

$API_URL = 'http://localhost';
$PRODUCTS_JSON = '/tmp/products-with-variants.json';

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
echo "║  SHOPWARE - UPDATE SNIGEL SUBCATEGORIES & COLOURS         ║\n";
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

// ============ STEP 2: Get unique categories from products ============
echo "Step 2: Getting unique categories from scraped data...\n";

$uniqueCategories = [];
foreach ($products as $p) {
    if (!empty($p['category'])) {
        // Normalize HTML entities
        $cat = html_entity_decode($p['category'], ENT_QUOTES, 'UTF-8');
        $cat = trim($cat);
        if (!in_array($cat, $uniqueCategories)) {
            $uniqueCategories[] = $cat;
        }
    }
}
sort($uniqueCategories);
echo "  Found " . count($uniqueCategories) . " unique categories\n\n";

// ============ STEP 3: Create subcategories ============
echo "Step 3: Creating/finding subcategories...\n";

$categoryMap = []; // name => id

foreach ($uniqueCategories as $catName) {
    // Check if exists
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
        $categoryMap[$catName] = $existingId;
        echo "  EXISTS: $catName\n";
    } else {
        // Create new subcategory
        $newId = bin2hex(random_bytes(16));
        $createResult = apiRequest($API_URL, $token, 'POST', 'category', [
            'id' => $newId,
            'name' => $catName,
            'parentId' => $snigelParentId,
            'active' => true,
            'displayNestedProducts' => true
        ]);

        if ($createResult['code'] >= 200 && $createResult['code'] < 300) {
            $categoryMap[$catName] = $newId;
            echo "  CREATED: $catName\n";
        } else {
            echo "  FAILED: $catName - " . json_encode($createResult['data']['errors'][0]['detail'] ?? 'Unknown') . "\n";
        }
    }
}

echo "\n  Category map created: " . count($categoryMap) . " categories\n\n";

// ============ STEP 4: Get/Create "Farbe" property group ============
echo "Step 4: Setting up Farbe (Colour) property group...\n";

$result = apiRequest($API_URL, $token, 'POST', 'search/property-group', [
    'filter' => [
        ['type' => 'equals', 'field' => 'name', 'value' => 'Farbe']
    ]
]);

$farbeGroupId = null;
foreach ($result['data']['data'] ?? [] as $group) {
    $name = $group['name'] ?? $group['attributes']['name'] ?? '';
    if ($name === 'Farbe') {
        $farbeGroupId = $group['id'];
        break;
    }
}

if (!$farbeGroupId) {
    // Create Farbe group
    $farbeGroupId = bin2hex(random_bytes(16));
    apiRequest($API_URL, $token, 'POST', 'property-group', [
        'id' => $farbeGroupId,
        'name' => 'Farbe',
        'sortingType' => 'alphanumeric',
        'displayType' => 'text',
        'filterable' => true,
        'visibleOnProductDetailPage' => true
    ]);
    echo "  Created Farbe property group: $farbeGroupId\n";
} else {
    echo "  Found Farbe property group: $farbeGroupId\n";
}

// Get existing colour options
$result = apiRequest($API_URL, $token, 'POST', 'search/property-group-option', [
    'filter' => [
        ['type' => 'equals', 'field' => 'groupId', 'value' => $farbeGroupId]
    ],
    'limit' => 100
]);

$colourOptions = [];
foreach ($result['data']['data'] ?? [] as $opt) {
    $name = $opt['name'] ?? $opt['attributes']['name'] ?? '';
    $colourOptions[$name] = $opt['id'];
}
echo "  Existing colour options: " . count($colourOptions) . "\n\n";

// ============ STEP 5: Find Snigel manufacturer ============
echo "Step 5: Finding Snigel manufacturer...\n";

$result = apiRequest($API_URL, $token, 'POST', 'search/product-manufacturer', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'Snigel']
    ]
]);

$snigelManufacturerId = null;
foreach ($result['data']['data'] ?? [] as $m) {
    $snigelManufacturerId = $m['id'];
    break;
}
echo "  Manufacturer ID: $snigelManufacturerId\n\n";

// ============ STEP 6: Update products ============
echo "Step 6: Updating products with categories and colours...\n\n";

$updated = 0;
$notFound = 0;
$errors = 0;
$total = count($products);

foreach ($products as $i => $product) {
    $num = $i + 1;
    $sku = !empty($product['article_no']) ? $product['article_no'] : 'SN-' . $product['slug'];
    $displayName = mb_substr($product['name'], 0, 35);

    echo "[$num/$total] $displayName... ";

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

    // Prepare update data
    $updateData = [];

    // Add subcategory
    $catName = html_entity_decode($product['category'] ?? '', ENT_QUOTES, 'UTF-8');
    $catName = trim($catName);

    if (!empty($catName) && isset($categoryMap[$catName])) {
        $updateData['categories'] = [
            ['id' => $snigelParentId],  // Keep in main Snigel
            ['id' => $categoryMap[$catName]]  // Add subcategory
        ];
    }

    // Add colour property (for simple products with colour)
    $colour = $product['colour'] ?? null;
    if ($colour && !$product['hasColorVariants']) {
        // Get or create colour option
        if (!isset($colourOptions[$colour])) {
            $optionId = bin2hex(random_bytes(16));
            $createResult = apiRequest($API_URL, $token, 'POST', 'property-group-option', [
                'id' => $optionId,
                'groupId' => $farbeGroupId,
                'name' => $colour
            ]);
            if ($createResult['code'] >= 200 && $createResult['code'] < 300) {
                $colourOptions[$colour] = $optionId;
            }
        }

        if (isset($colourOptions[$colour])) {
            $updateData['properties'] = [
                ['id' => $colourOptions[$colour]]
            ];
        }
    }

    // Update product
    if (!empty($updateData)) {
        $updateResult = apiRequest($API_URL, $token, 'PATCH', "product/$productId", $updateData);

        if ($updateResult['code'] >= 200 && $updateResult['code'] < 300) {
            $catDisplay = $catName ? substr($catName, 0, 15) : '-';
            $colourDisplay = $colour ?? '-';
            echo "OK | Cat: $catDisplay | Col: $colourDisplay\n";
            $updated++;
        } else {
            echo "ERROR\n";
            $errors++;
        }
    } else {
        echo "SKIP (no updates)\n";
    }
}

// ============ SUMMARY ============
echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                      COMPLETE                              ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
echo "║  Products updated: " . str_pad($updated, 37) . "║\n";
echo "║  Not found:        " . str_pad($notFound, 37) . "║\n";
echo "║  Errors:           " . str_pad($errors, 37) . "║\n";
echo "║  Categories created: " . str_pad(count($categoryMap), 35) . "║\n";
echo "║  Colour options: " . str_pad(count($colourOptions), 39) . "║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "Done! Clear cache: bin/console cache:clear\n\n";
