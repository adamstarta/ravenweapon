<?php
/**
 * Fix product SEO Category for correct breadcrumbs
 *
 * Problem: Products show breadcrumb "Home / Product Name" instead of
 * "Home / Zielfernrohre / Product Name"
 *
 * Solution: Set the seoCategory for each product to their correct category
 */

$config = [
    'base_url' => 'https://ortak.ch',
    'client_id' => 'SWIARAVEN03399CEA2C931269',
    'client_secret' => 'RavenNavbarUpdate2025!'
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

$token = getAccessToken($config);
if (!$token) die("Failed to get token\n");

echo "=== Fix Product SEO Category for Breadcrumbs ===\n\n";

// Get sales channel ID
$scResult = apiRequest('POST', '/search/sales-channel', ['limit' => 1], $token, $config);
$salesChannelId = $scResult['body']['data'][0]['id'] ?? null;
echo "Sales Channel ID: $salesChannelId\n\n";

// Category mapping: category ID => category name (for logging)
$categoryMapping = [
    // Zielhilfen subcategories (under Waffenzubehoer)
    '499800fd06224f779acc5c4ac243d2e8' => 'Zielfernrohre',
    'c34b9bb9a7a6473d928dd9c7c0f10f8b' => 'Rotpunktvisiere',
    '461b4f23bcd74823abbb55550af5008c' => 'Spektive',
    'b47d4447067c45aaa0aed7081ac465c4' => 'Fernglaeser',

    // Zubehör subcategories (under Zubehoer)
    '00a19869155b4c0d9508dfcfeeaf93d7' => 'Magazine',
    '17d31faee72a4f0eb9863adf8bab9b00' => 'Zweibeine',
    '2d9fa9cea22f4d8e8c80fc059f8fc47d' => 'Schienen-Zubehoer',
    '30d2d3ee371248d592cb1cdfdfd0f412' => 'Zielfernrohrmontagen',
    '6aa33f0f12e543fbb28d2bd7ede4dbf2' => 'Griffe-Handschutz',
    'b2f4bafcda154b899c22bfb74496d140' => 'Muendungsaufsaetze',
];

$totalUpdated = 0;
$totalFailed = 0;

foreach ($categoryMapping as $categoryId => $categoryName) {
    echo "=== Processing: $categoryName ===\n";

    // Get all products in this category
    $productsResult = apiRequest('POST', '/search/product', [
        'filter' => [
            ['type' => 'equals', 'field' => 'categories.id', 'value' => $categoryId]
        ],
        'includes' => ['product' => ['id', 'name']],
        'limit' => 200
    ], $token, $config);

    $products = $productsResult['body']['data'] ?? [];
    echo "Found " . count($products) . " products\n";

    foreach ($products as $product) {
        $productId = $product['id'];
        $productName = $product['name'];

        // Update the product's seoCategory for this sales channel
        $updateResult = apiRequest('PATCH', '/product/' . $productId, [
            'mainCategories' => [
                [
                    'productId' => $productId,
                    'categoryId' => $categoryId,
                    'salesChannelId' => $salesChannelId
                ]
            ]
        ], $token, $config);

        if ($updateResult['code'] < 300) {
            echo "  OK: $productName\n";
            $totalUpdated++;
        } else {
            $error = $updateResult['body']['errors'][0]['detail'] ?? 'Unknown error';
            echo "  FAIL: $productName - $error\n";
            $totalFailed++;
        }
    }

    echo "\n";
}

echo "=== Summary ===\n";
echo "Updated: $totalUpdated\n";
echo "Failed: $totalFailed\n";

// Clear cache
echo "\nClearing cache... ";
$cacheResult = apiRequest('DELETE', '/_action/cache', null, $token, $config);
echo ($cacheResult['code'] < 300 ? "OK" : "FAIL") . "\n";

echo "\n=== Done ===\n";
