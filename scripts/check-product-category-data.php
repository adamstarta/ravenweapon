<?php
/**
 * Check product category data - seoCategory and categories associations
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

echo "=== Check Product Category Data ===\n\n";

// Get the THRIVE 3-12X44 ZEROPLEX product
$productResult = apiRequest('POST', '/search/product', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'THRIVE 3-12X44 ZEROPLEX']
    ],
    'associations' => [
        'categories' => [],
        'mainCategories' => [
            'associations' => [
                'category' => [],
                'salesChannel' => []
            ]
        ]
    ],
    'limit' => 1
], $token, $config);

$product = $productResult['body']['data'][0] ?? null;

if (!$product) {
    die("Product not found\n");
}

echo "Product: {$product['name']}\n";
echo "Product ID: {$product['id']}\n\n";

echo "=== Categories Association ===\n";
$categories = $product['categories'] ?? [];
if (empty($categories)) {
    echo "  No categories found in association!\n";
} else {
    foreach ($categories as $cat) {
        echo "  - {$cat['name']} (ID: {$cat['id']})\n";
    }
}

echo "\n=== Main Categories (SEO Categories) ===\n";
$mainCategories = $product['mainCategories'] ?? [];
if (empty($mainCategories)) {
    echo "  No mainCategories found!\n";
} else {
    foreach ($mainCategories as $mc) {
        $catName = $mc['category']['name'] ?? 'Unknown';
        $catId = $mc['categoryId'] ?? 'Unknown';
        $scId = $mc['salesChannelId'] ?? 'Unknown';
        echo "  - Category: $catName (ID: $catId)\n";
        echo "    Sales Channel: $scId\n";
    }
}

// Also check the product_category table directly
echo "\n=== Product-Category Links (product_category table) ===\n";
$catResult = apiRequest('POST', '/search/category', [
    'filter' => [
        ['type' => 'equals', 'field' => 'products.id', 'value' => $product['id']]
    ],
    'limit' => 50
], $token, $config);

$linkedCategories = $catResult['body']['data'] ?? [];
if (empty($linkedCategories)) {
    echo "  No categories linked to this product!\n";
} else {
    foreach ($linkedCategories as $cat) {
        echo "  - {$cat['name']} (ID: {$cat['id']})\n";
    }
}

echo "\n=== Done ===\n";
