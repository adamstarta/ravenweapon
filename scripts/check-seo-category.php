<?php
/**
 * Check SEO Category for specific product
 */

$baseUrl = 'https://ortak.ch/api';
$clientId = 'SWIAC3HJVHFJMHQYRWRUM1E1SG';
$clientSecret = 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg';

function getAccessToken($baseUrl, $clientId, $clientSecret) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/oauth/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['access_token'];
}

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "=== Check SEO Category for Covert equipment vest -12 ===\n\n";

$productId = '824d6a33eaa1e8a00231d409ac70d594';

// Get product with seoCategory association
$productResponse = apiRequest($baseUrl, $token, 'POST', '/search/product', [
    'filter' => [
        ['type' => 'equals', 'field' => 'id', 'value' => $productId]
    ],
    'associations' => [
        'seoCategory' => [],
        'mainCategories' => []
    ],
    'includes' => [
        'product' => ['id', 'name', 'seoCategory', 'mainCategories'],
        'category' => ['id', 'name', 'breadcrumb'],
        'main_category' => ['id', 'productId', 'categoryId']
    ]
]);

if (isset($productResponse['data'][0])) {
    $product = $productResponse['data'][0];
    $attrs = $product['attributes'] ?? $product;
    $name = $attrs['name'] ?? 'Unknown';

    echo "Product: $name\n";
    echo "Product ID: {$product['id']}\n\n";

    // Check seoCategory
    if (isset($product['seoCategory']) && $product['seoCategory']) {
        $seoCat = $product['seoCategory'];
        $seoCatAttrs = $seoCat['attributes'] ?? $seoCat;
        echo "SEO Category:\n";
        echo "  - ID: " . ($seoCat['id'] ?? 'null') . "\n";
        echo "  - Name: " . ($seoCatAttrs['name'] ?? 'null') . "\n";
        echo "  - Breadcrumb: " . (isset($seoCatAttrs['breadcrumb']) ? implode(' / ', $seoCatAttrs['breadcrumb']) : 'null') . "\n";
    } else {
        echo "SEO Category: NOT SET!\n";
    }

    echo "\n";

    // Check mainCategories
    if (isset($product['mainCategories']) && count($product['mainCategories']) > 0) {
        echo "Main Categories:\n";
        foreach ($product['mainCategories'] as $mc) {
            $mcAttrs = $mc['attributes'] ?? $mc;
            echo "  - Category ID: " . ($mcAttrs['categoryId'] ?? $mc['categoryId'] ?? 'null') . "\n";
        }
    } else {
        echo "Main Categories: NONE!\n";
    }
} else {
    echo "Product not found!\n";
    print_r($productResponse);
}

// Also check how many products have seoCategory set
echo "\n\n=== Products with SEO Category Stats ===\n";

$withSeoCatResponse = apiRequest($baseUrl, $token, 'POST', '/search/product', [
    'limit' => 1,
    'filter' => [
        ['type' => 'not', 'queries' => [
            ['type' => 'equals', 'field' => 'seoCategory.id', 'value' => null]
        ]]
    ],
    'total-count-mode' => 1
]);

$withSeoCat = $withSeoCatResponse['meta']['total'] ?? 0;
echo "Products WITH seoCategory: $withSeoCat\n";

$allProductsResponse = apiRequest($baseUrl, $token, 'POST', '/search/product', [
    'limit' => 1,
    'total-count-mode' => 1
]);

$totalProducts = $allProductsResponse['meta']['total'] ?? 0;
echo "Total products: $totalProducts\n";
echo "Products WITHOUT seoCategory: " . ($totalProducts - $withSeoCat) . "\n";
