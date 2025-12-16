<?php
/**
 * Assign products to categories based on old shop structure
 * Maps old shop categories to German Ausrüstung subcategories
 */

$config = [
    'base_url' => 'https://ortak.ch',
    'client_id' => 'SWIARAVEN03399CEA2C931269',
    'client_secret' => 'RavenNavbarUpdate2025!'
];

// Mapping: Old shop category -> German Ausrüstung subcategory
$categoryMapping = [
    // Aiming aids, optics & accessories
    'Riflescopes' => 'Scharfschützen-Ausrüstung',      // Sniper Gear - precision optics
    'Red Dots' => 'Taktische Ausrüstung',              // Tactical Gear - quick target
    'Spotting scopes' => 'Scharfschützen-Ausrüstung',  // Sniper Gear
    'Binoculars' => 'Verschiedenes',                    // Miscellaneous

    // Accessories
    'Magazines' => 'Taktische Ausrüstung',             // Tactical Gear
    'Sticks & handles' => 'Taktische Ausrüstung',     // Tactical Gear
    'Rails and Accessories' => 'Taktische Ausrüstung', // Tactical Gear
    'Bipods' => 'Scharfschützen-Ausrüstung',          // Sniper Gear
    'Muzzle attachments' => 'Taktische Ausrüstung',   // Tactical Gear

    // Default for "All" category items
    'All' => 'Taktische Ausrüstung'
];

function getAccessToken($config) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['base_url'] . '/api/oauth/token',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'client_credentials',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret']
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function apiRequest($method, $endpoint, $data, $token, $config) {
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $config['base_url'] . '/api' . $endpoint,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ];
    if ($data !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

function normalizeProductName($name) {
    $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = mb_strtolower($name, 'UTF-8');
    $name = preg_replace('/[^a-z0-9\s]/', '', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

echo "=== Assign Products to Categories ===\n\n";

// Load old shop products with category info
$oldShopFile = __DIR__ . '/old-shop-data/all-products.json';
if (!file_exists($oldShopFile)) {
    die("Error: Old shop data not found. Run scraper first.\n");
}

$oldProducts = json_decode(file_get_contents($oldShopFile), true);
echo "Loaded " . count($oldProducts) . " products from old shop\n";

// Create lookup by normalized name
$oldProductLookup = [];
foreach ($oldProducts as $product) {
    $normalized = normalizeProductName($product['name']);
    $oldProductLookup[$normalized] = $product;
}

// Get token
$token = getAccessToken($config);
if (!$token) {
    die("Failed to get API token\n");
}
echo "Got API token\n\n";

// Get Ausrüstung category and its subcategories
echo "Fetching Ausrüstung subcategories...\n";
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Ausrüstung']],
    'includes' => ['category' => ['id', 'name']]
], $token, $config);

$ausruestung = $result['body']['data'][0] ?? null;
if (!$ausruestung) {
    die("Ausrüstung category not found\n");
}

$subResult = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $ausruestung['id']]],
    'includes' => ['category' => ['id', 'name']],
    'limit' => 50
], $token, $config);

$categoryIds = [];
foreach ($subResult['body']['data'] ?? [] as $cat) {
    $categoryIds[$cat['name']] = $cat['id'];
    echo "  Found: {$cat['name']} ({$cat['id']})\n";
}

// Fetch all products from ortak.ch
echo "\nFetching products from ortak.ch...\n";
$allOrtakProducts = [];
$page = 1;

do {
    $result = apiRequest('POST', '/search/product', [
        'page' => $page,
        'limit' => 500,
        'includes' => ['product' => ['id', 'name', 'productNumber', 'categories']]
    ], $token, $config);

    $products = $result['body']['data'] ?? [];
    $allOrtakProducts = array_merge($allOrtakProducts, $products);
    $hasMore = count($products) === 500;
    $page++;
} while ($hasMore);

echo "Total ortak.ch products: " . count($allOrtakProducts) . "\n\n";

// Process products
$assigned = 0;
$skipped = 0;
$notFound = 0;

$assignmentLog = [];

foreach ($allOrtakProducts as $ortakProduct) {
    $normalized = normalizeProductName($ortakProduct['name']);

    // Find in old shop data
    if (!isset($oldProductLookup[$normalized])) {
        $notFound++;
        continue;
    }

    $oldProduct = $oldProductLookup[$normalized];
    $oldSubcategory = $oldProduct['subcategory'];

    // Get target German category
    $targetCategory = $categoryMapping[$oldSubcategory] ?? $categoryMapping['All'];

    if (!isset($categoryIds[$targetCategory])) {
        echo "WARNING: Category '$targetCategory' not found for product: {$ortakProduct['name']}\n";
        $skipped++;
        continue;
    }

    $targetCategoryId = $categoryIds[$targetCategory];

    // Check if already assigned to this category
    $existingCatIds = array_column($ortakProduct['categories'] ?? [], 'id');
    if (in_array($targetCategoryId, $existingCatIds)) {
        echo "  SKIP: {$ortakProduct['name']} - already in $targetCategory\n";
        $skipped++;
        continue;
    }

    // Assign to category
    echo "  ASSIGN: {$ortakProduct['name']} -> $targetCategory\n";

    $updateResult = apiRequest('PATCH', '/product/' . $ortakProduct['id'], [
        'categories' => [
            ['id' => $targetCategoryId]
        ]
    ], $token, $config);

    if ($updateResult['code'] < 300) {
        $assigned++;
        $assignmentLog[] = [
            'product' => $ortakProduct['name'],
            'productId' => $ortakProduct['id'],
            'oldCategory' => $oldSubcategory,
            'newCategory' => $targetCategory,
            'status' => 'success'
        ];
    } else {
        echo "    ERROR: " . json_encode($updateResult['body']['errors'][0]['detail'] ?? 'Unknown') . "\n";
        $assignmentLog[] = [
            'product' => $ortakProduct['name'],
            'productId' => $ortakProduct['id'],
            'oldCategory' => $oldSubcategory,
            'newCategory' => $targetCategory,
            'status' => 'failed',
            'error' => $updateResult['body']['errors'][0]['detail'] ?? 'Unknown'
        ];
    }
}

// Summary
echo "\n=== Summary ===\n";
echo "Assigned: $assigned\n";
echo "Skipped (already assigned): $skipped\n";
echo "Not found in old shop data: $notFound\n";

// Save log
$logFile = __DIR__ . '/old-shop-data/assignment-log.json';
file_put_contents($logFile, json_encode($assignmentLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\nAssignment log saved to: assignment-log.json\n";

// Clear cache
echo "\nClearing cache... ";
$cacheResult = apiRequest('DELETE', '/_action/cache', null, $token, $config);
echo ($cacheResult['code'] < 300 ? "OK" : "FAIL") . "\n";

echo "\n=== DONE ===\n";
