<?php
/**
 * Check Product SEO URL v2 - More thorough check
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
echo "=== Product SEO URL Check v2 ===\n\n";

$productId = '824d6a33eaa1e8a00231d409ac70d594';
echo "Product ID: $productId\n\n";

// Get ALL SEO URLs for this product (including deleted ones)
echo "1. ALL SEO URLs (including deleted):\n";
$seoResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
    'filter' => [
        ['type' => 'equals', 'field' => 'foreignKey', 'value' => $productId],
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.detail.page']
    ],
    'includes' => [
        'seo_url' => ['id', 'seoPathInfo', 'isCanonical', 'isDeleted', 'isModified', 'salesChannelId']
    ]
]);

if (isset($seoResponse['data']) && count($seoResponse['data']) > 0) {
    foreach ($seoResponse['data'] as $url) {
        $attrs = $url['attributes'] ?? $url;
        $path = $attrs['seoPathInfo'] ?? '';
        $canonical = $attrs['isCanonical'] ?? false;
        $deleted = $attrs['isDeleted'] ?? false;

        echo "  - /$path\n";
        echo "    Canonical: " . ($canonical ? 'YES' : 'no') . ", Deleted: " . ($deleted ? 'YES' : 'no') . "\n";
    }
} else {
    echo "  No SEO URLs found for this product\n";
}

// Get only NON-deleted SEO URLs
echo "\n2. ACTIVE SEO URLs only:\n";
$seoResponse2 = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
    'filter' => [
        ['type' => 'equals', 'field' => 'foreignKey', 'value' => $productId],
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.detail.page'],
        ['type' => 'equals', 'field' => 'isDeleted', 'value' => false]
    ],
    'includes' => [
        'seo_url' => ['id', 'seoPathInfo', 'isCanonical']
    ]
]);

if (isset($seoResponse2['data']) && count($seoResponse2['data']) > 0) {
    foreach ($seoResponse2['data'] as $url) {
        $attrs = $url['attributes'] ?? $url;
        $path = $attrs['seoPathInfo'] ?? '';
        $canonical = $attrs['isCanonical'] ?? false;
        echo "  - /$path (Canonical: " . ($canonical ? 'YES' : 'no') . ")\n";
    }
} else {
    echo "  NO ACTIVE SEO URLs - product needs SEO URL regeneration!\n";
}

// Check product_category table directly via product association
echo "\n3. Product categories (direct query):\n";
$catResponse = apiRequest($baseUrl, $token, 'GET', '/product/' . $productId . '?associations[categories][]=');

if (isset($catResponse['data']['attributes']['categories'])) {
    $categories = $catResponse['data']['attributes']['categories'];
    foreach ($categories as $cat) {
        echo "  - " . ($cat['name'] ?? 'Unknown') . "\n";
    }
} else if (isset($catResponse['data']['categories'])) {
    foreach ($catResponse['data']['categories'] as $cat) {
        $catName = $cat['attributes']['name'] ?? $cat['name'] ?? 'Unknown';
        echo "  - $catName (ID: " . $cat['id'] . ")\n";
    }
} else {
    echo "  Could not fetch categories\n";
}

// Try alternative - search in product_category
echo "\n4. Category search via product filter:\n";
$searchResponse = apiRequest($baseUrl, $token, 'POST', '/search/category', [
    'filter' => [
        ['type' => 'multi', 'operator' => 'or', 'queries' => [
            ['type' => 'equals', 'field' => 'products.id', 'value' => $productId]
        ]]
    ],
    'includes' => [
        'category' => ['id', 'name', 'breadcrumb', 'translated']
    ]
]);

if (isset($searchResponse['data']) && count($searchResponse['data']) > 0) {
    foreach ($searchResponse['data'] as $cat) {
        $attrs = $cat['attributes'] ?? $cat;
        $catName = $attrs['translated']['name'] ?? $attrs['name'] ?? 'Unknown';
        $breadcrumb = $attrs['translated']['breadcrumb'] ?? $attrs['breadcrumb'] ?? [];
        echo "  - $catName\n";
        echo "    Breadcrumb: " . implode(' / ', $breadcrumb) . "\n";
        echo "    ID: " . $cat['id'] . "\n";
    }
} else {
    echo "  No categories found via product filter\n";
}

echo "\n=== Live URL Check ===\n";
echo "Test these URLs in browser:\n";
echo "  - https://ortak.ch/detail/$productId (Technical URL)\n";
echo "  - https://ortak.ch/ausruestung/koerperschutz/westen-chest-rigs/ (Category listing)\n";
