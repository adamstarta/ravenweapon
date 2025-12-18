<?php
/**
 * Debug product category assignment
 */

$baseUrl = 'https://ortak.ch/api';
$clientId = 'SWIAC3HJVHFJMHQYRWRUM1E1SG';
$clientSecret = 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg';

$productId = 'b044f1a17fdc110c7ed94d6123c51bf7'; // 3 row cummerbund 1.0

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

echo "=== Product Category Debug ===\n\n";

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "Got token\n\n";

// Get product with categories
echo "1. Fetching product...\n";
$response = apiRequest($baseUrl, $token, 'POST', '/search/product', [
    'filter' => [
        ['type' => 'equals', 'field' => 'id', 'value' => $productId]
    ],
    'associations' => [
        'categories' => [
            'limit' => 50
        ],
        'seoUrls' => [
            'limit' => 20
        ]
    ]
]);

if (!isset($response['data']) || empty($response['data'])) {
    echo "Product not found!\n";
    exit(1);
}

$product = $response['data'][0];
$attrs = $product['attributes'] ?? $product;

echo "   Product: " . ($attrs['translated']['name'] ?? $attrs['name'] ?? 'Unknown') . "\n";
echo "   ID: " . $product['id'] . "\n\n";

// Check categories
echo "2. Product Categories:\n";
$categories = $attrs['categories'] ?? [];
if (empty($categories)) {
    // Check relationships
    if (isset($product['relationships']['categories']['data'])) {
        $categories = $product['relationships']['categories']['data'];
    }
}

if (empty($categories)) {
    echo "   No direct categories found in product data\n";
} else {
    foreach ($categories as $cat) {
        $catId = $cat['id'] ?? $cat;
        echo "   - Category ID: $catId\n";
    }
}

// Get product_category mapping
echo "\n3. Fetching product-category assignments...\n";
$catResponse = apiRequest($baseUrl, $token, 'POST', '/search/product', [
    'filter' => [
        ['type' => 'equals', 'field' => 'id', 'value' => $productId]
    ],
    'includes' => [
        'product' => ['id', 'categories', 'categoryTree', 'streamIds']
    ],
    'associations' => [
        'categories' => []
    ]
]);

if (isset($catResponse['data'][0])) {
    $catAttrs = $catResponse['data'][0]['attributes'] ?? $catResponse['data'][0];
    $categoryTree = $catAttrs['categoryTree'] ?? [];
    echo "   Category Tree IDs: " . json_encode($categoryTree) . "\n";
}

// Get main category for sales channel
echo "\n4. Fetching main_category for this product...\n";
$mainCatResponse = apiRequest($baseUrl, $token, 'POST', '/search/main-category', [
    'filter' => [
        ['type' => 'equals', 'field' => 'productId', 'value' => $productId]
    ],
    'associations' => [
        'category' => []
    ]
]);

if (isset($mainCatResponse['data']) && !empty($mainCatResponse['data'])) {
    foreach ($mainCatResponse['data'] as $mainCat) {
        $mainCatAttrs = $mainCat['attributes'] ?? $mainCat;
        echo "   Main Category Entry:\n";
        echo "      Sales Channel: " . ($mainCatAttrs['salesChannelId'] ?? 'N/A') . "\n";
        echo "      Category ID: " . ($mainCatAttrs['categoryId'] ?? 'N/A') . "\n";

        // Get category details
        if (isset($mainCatAttrs['categoryId'])) {
            $catDetailResponse = apiRequest($baseUrl, $token, 'GET', '/category/' . $mainCatAttrs['categoryId']);
            if (isset($catDetailResponse['data'])) {
                $catDetail = $catDetailResponse['data']['attributes'] ?? $catDetailResponse['data'];
                echo "      Category Name: " . ($catDetail['translated']['name'] ?? $catDetail['name'] ?? 'N/A') . "\n";
                echo "      Category Level: " . ($catDetail['level'] ?? 'N/A') . "\n";
                echo "      Category Path: " . ($catDetail['path'] ?? 'N/A') . "\n";
                echo "      Breadcrumb: " . json_encode($catDetail['translated']['breadcrumb'] ?? $catDetail['breadcrumb'] ?? []) . "\n";
            }
        }
    }
} else {
    echo "   No main_category entries found\n";
}

// Get SEO URLs for product
echo "\n5. Product SEO URLs:\n";
$seoResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
    'filter' => [
        ['type' => 'equals', 'field' => 'foreignKey', 'value' => $productId],
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.detail.page']
    ],
    'limit' => 20
]);

if (isset($seoResponse['data'])) {
    foreach ($seoResponse['data'] as $seoUrl) {
        $seoAttrs = $seoUrl['attributes'] ?? $seoUrl;
        $canonical = ($seoAttrs['isCanonical'] ?? false) ? ' [CANONICAL]' : '';
        echo "   - " . ($seoAttrs['seoPathInfo'] ?? 'N/A') . $canonical . "\n";
    }
}

echo "\n=== Done ===\n";
