<?php
/**
 * Shopware - Update Snigel Products with Multiple Categories
 *
 * Updates products to be assigned to ALL their categories based on
 * the scraped B2B category data (product-categories-mapping.json)
 *
 * Run on server:
 * 1. Copy files:
 *    scp scripts/update-snigel-multi-categories.php root@77.42.19.154:/tmp/
 *    scp scripts/snigel-data/product-categories-mapping.json root@77.42.19.154:/tmp/
 * 2. Copy to container:
 *    ssh root@77.42.19.154 "docker cp /tmp/update-snigel-multi-categories.php shopware-chf:/tmp/"
 *    ssh root@77.42.19.154 "docker cp /tmp/product-categories-mapping.json shopware-chf:/tmp/"
 * 3. Run:
 *    docker exec shopware-chf php /tmp/update-snigel-multi-categories.php
 */

$API_URL = 'http://localhost';
$MAPPING_JSON = '/tmp/product-categories-mapping.json';

// The 21 English category names we created in Shopware
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
    'HighVis',
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

function normalizeProductName($name) {
    // Normalize product name for matching
    $name = trim($name);
    $name = preg_replace('/\s+/', ' ', $name);
    return $name;
}

// ============ MAIN SCRIPT ============

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  SHOPWARE - UPDATE SNIGEL MULTI-CATEGORY ASSIGNMENTS      ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Load product-categories mapping
if (!file_exists($MAPPING_JSON)) {
    die("Error: $MAPPING_JSON not found\n");
}

$productCategoriesMap = json_decode(file_get_contents($MAPPING_JSON), true);
echo "Loaded mapping for " . count($productCategoriesMap) . " products\n\n";

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

// ============ STEP 2: Get all Snigel subcategory IDs ============
echo "Step 2: Getting Snigel subcategory IDs...\n";

$categoryIds = []; // Category name => ID

foreach ($ENGLISH_CATEGORIES as $catName) {
    $result = apiRequest($API_URL, $token, 'POST', 'search/category', [
        'filter' => [
            ['type' => 'equals', 'field' => 'name', 'value' => $catName],
            ['type' => 'equals', 'field' => 'parentId', 'value' => $snigelParentId]
        ]
    ]);

    foreach ($result['data']['data'] ?? [] as $cat) {
        $categoryIds[$catName] = $cat['id'];
        echo "  Found: $catName\n";
        break;
    }
}

echo "\n  Total categories found: " . count($categoryIds) . " / " . count($ENGLISH_CATEGORIES) . "\n\n";

// ============ STEP 3: Get ALL products from Shopware ============
echo "Step 3: Finding ALL products in Shopware...\n";

// Get all products (no filter - will match by name later)
$allProducts = [];
$page = 1;
$limit = 100;

do {
    $result = apiRequest($API_URL, $token, 'POST', 'search/product', [
        'limit' => $limit,
        'page' => $page,
        'includes' => ['product' => ['id', 'name', 'productNumber']]
    ]);

    $products = $result['data']['data'] ?? [];
    foreach ($products as $prod) {
        $allProducts[] = [
            'id' => $prod['id'],
            'name' => $prod['name'] ?? $prod['attributes']['name'] ?? '',
            'productNumber' => $prod['productNumber'] ?? $prod['attributes']['productNumber'] ?? ''
        ];
    }

    $total = $result['data']['meta']['total'] ?? count($allProducts);
    echo "  Fetched page $page: " . count($products) . " products (total: " . count($allProducts) . ")\n";
    $page++;
} while (count($products) === $limit);

echo "\n  Found " . count($allProducts) . " total products in Shopware\n\n";

// ============ STEP 4: Update products with multi-categories ============
echo "Step 4: Updating product category assignments...\n\n";

$updated = 0;
$noMatch = 0;
$errors = 0;
$noCategories = 0;
$totalProducts = count($allProducts);

foreach ($allProducts as $i => $product) {
    $num = $i + 1;
    $shopName = normalizeProductName($product['name']);
    $displayName = mb_substr($shopName, 0, 40);

    echo "[$num/$totalProducts] $displayName... ";

    // Find matching product in the mapping
    $matchedCategories = null;
    foreach ($productCategoriesMap as $b2bName => $categories) {
        $normalizedB2B = normalizeProductName($b2bName);
        if (strcasecmp($shopName, $normalizedB2B) === 0 ||
            stripos($shopName, $normalizedB2B) !== false ||
            stripos($normalizedB2B, $shopName) !== false) {
            $matchedCategories = $categories;
            break;
        }
    }

    if (!$matchedCategories) {
        echo "NO MATCH\n";
        $noMatch++;
        continue;
    }

    // Build category IDs array
    $productCategoryIds = [['id' => $snigelParentId]]; // Always include parent

    foreach ($matchedCategories as $catName) {
        if (isset($categoryIds[$catName])) {
            $productCategoryIds[] = ['id' => $categoryIds[$catName]];
        }
    }

    if (count($productCategoryIds) <= 1) {
        echo "NO VALID CATEGORIES\n";
        $noCategories++;
        continue;
    }

    // Update product categories
    $updateResult = apiRequest($API_URL, $token, 'PATCH', "product/{$product['id']}", [
        'categories' => $productCategoryIds
    ]);

    if ($updateResult['code'] >= 200 && $updateResult['code'] < 300) {
        $catCount = count($productCategoryIds) - 1; // Exclude parent
        $catNames = implode(', ', $matchedCategories);
        echo "OK ($catCount cats)\n";
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
printf("║  Products updated:       %-31s ║\n", $updated);
printf("║  No match found:         %-31s ║\n", $noMatch);
printf("║  No valid categories:    %-31s ║\n", $noCategories);
printf("║  Errors:                 %-31s ║\n", $errors);
printf("║  Total Snigel products:  %-31s ║\n", $totalProducts);
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "Done! Now run: bin/console cache:clear\n\n";
