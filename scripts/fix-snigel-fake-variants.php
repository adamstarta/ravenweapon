<?php
/**
 * Fix Snigel Fake Variants
 *
 * Converts products with fake color variants to simple products with gallery images.
 * Products that should NOT have variant selector (hasColorVariants = false) will be fixed.
 *
 * Run: docker exec shopware-chf php /tmp/fix-snigel-fake-variants.php
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
echo "║  FIX SNIGEL FAKE VARIANTS - CONVERT TO SIMPLE PRODUCTS    ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Load products JSON
if (!file_exists($PRODUCTS_JSON)) {
    die("Error: $PRODUCTS_JSON not found\n");
}

$products = json_decode(file_get_contents($PRODUCTS_JSON), true);
echo "Loaded " . count($products) . " products from JSON\n\n";

// Filter products that should be SIMPLE (no real color variants)
$simpleProducts = array_filter($products, function($p) {
    return !$p['hasColorVariants'];
});
echo "Products that should be SIMPLE (no variants): " . count($simpleProducts) . "\n\n";

// Get token
$token = getToken($API_URL);
if (!$token) {
    die("Error: Failed to get API token\n");
}
echo "Got API token\n\n";

// Find Snigel manufacturer
$result = apiRequest($API_URL, $token, 'POST', 'search/product-manufacturer', [
    'filter' => [['type' => 'contains', 'field' => 'name', 'value' => 'Snigel']]
]);
$snigelManufacturerId = $result['data']['data'][0]['id'] ?? null;
echo "Snigel manufacturer ID: $snigelManufacturerId\n\n";

// Get all Snigel products with their variant info
echo "Fetching Snigel products with configurator info...\n";
$result = apiRequest($API_URL, $token, 'POST', 'search/product', [
    'limit' => 500,
    'filter' => [
        ['type' => 'equals', 'field' => 'manufacturerId', 'value' => $snigelManufacturerId],
        ['type' => 'equals', 'field' => 'parentId', 'value' => null]  // Only parent products
    ],
    'associations' => [
        'configuratorSettings' => [],
        'children' => [
            'associations' => ['media' => []]
        ],
        'media' => []
    ]
]);

$shopwareProducts = [];
foreach ($result['data']['data'] ?? [] as $p) {
    $sku = $p['productNumber'] ?? $p['attributes']['productNumber'] ?? '';
    $shopwareProducts[$sku] = $p;
}
echo "Found " . count($shopwareProducts) . " Snigel parent products in Shopware\n\n";

// Process each simple product
echo "Processing products...\n\n";

$fixed = 0;
$alreadySimple = 0;
$notFound = 0;
$errors = 0;
$total = count($simpleProducts);
$count = 0;

foreach ($simpleProducts as $product) {
    $count++;
    $sku = !empty($product['article_no']) ? $product['article_no'] : 'SN-' . $product['slug'];
    $displayName = mb_substr($product['name'], 0, 40);

    echo "[$count/$total] $displayName... ";

    if (!isset($shopwareProducts[$sku])) {
        echo "NOT FOUND\n";
        $notFound++;
        continue;
    }

    $swProduct = $shopwareProducts[$sku];
    $productId = $swProduct['id'];

    // Check if product has configurator settings (variants)
    $configuratorSettings = $swProduct['configuratorSettings'] ?? $swProduct['attributes']['configuratorSettings'] ?? [];
    $children = $swProduct['children'] ?? $swProduct['attributes']['children'] ?? [];

    if (empty($configuratorSettings) && empty($children)) {
        echo "ALREADY SIMPLE\n";
        $alreadySimple++;
        continue;
    }

    // Product has variants - need to fix it
    $numVariants = count($children);
    echo "HAS $numVariants VARIANTS - ";

    // Step 1: Collect all media from children (variant products)
    $allMediaIds = [];
    foreach ($children as $child) {
        $childMedia = $child['media'] ?? $child['attributes']['media'] ?? [];
        foreach ($childMedia as $m) {
            $mediaId = $m['mediaId'] ?? $m['attributes']['mediaId'] ?? null;
            if ($mediaId && !in_array($mediaId, $allMediaIds)) {
                $allMediaIds[] = $mediaId;
            }
        }
    }

    // Step 2: Delete child products (variants)
    foreach ($children as $child) {
        $childId = $child['id'];
        apiRequest($API_URL, $token, 'DELETE', "product/$childId");
    }

    // Step 3: Remove configurator settings from parent
    // We do this by updating the product with empty configuratorSettings
    $updateResult = apiRequest($API_URL, $token, 'PATCH', "product/$productId", [
        'configuratorSettings' => []
    ]);

    // Step 4: Add collected media to parent as gallery
    if (!empty($allMediaIds)) {
        $mediaAssociations = [];
        $position = 1;
        foreach ($allMediaIds as $mediaId) {
            $mediaAssociations[] = [
                'mediaId' => $mediaId,
                'position' => $position++
            ];
        }

        apiRequest($API_URL, $token, 'PATCH', "product/$productId", [
            'media' => $mediaAssociations
        ]);
    }

    if ($updateResult['code'] >= 200 && $updateResult['code'] < 300) {
        echo "FIXED\n";
        $fixed++;
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
echo "║  Fixed (variants removed):  " . str_pad($fixed, 28) . "║\n";
echo "║  Already simple:            " . str_pad($alreadySimple, 28) . "║\n";
echo "║  Not found:                 " . str_pad($notFound, 28) . "║\n";
echo "║  Errors:                    " . str_pad($errors, 28) . "║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "Done! Clear cache: bin/console cache:clear\n\n";
