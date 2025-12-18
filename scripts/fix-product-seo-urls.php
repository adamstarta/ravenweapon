<?php
/**
 * Fix product SEO URLs based on their main category
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response
    ];
}

function slugify($text) {
    $text = str_replace(['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'], ['ae', 'oe', 'ue', 'ss', 'ae', 'oe', 'ue'], $text);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

echo "=== Product SEO URL Fixer ===\n\n";

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "Got token\n\n";

// Step 1: Get all category canonical SEO URLs
echo "1. Fetching category SEO URLs...\n";
$catSeoResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
    'limit' => 500,
    'filter' => [
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page'],
        ['type' => 'equals', 'field' => 'isCanonical', 'value' => true]
    ]
]);

$categorySeoUrls = [];
if (isset($catSeoResponse['body']['data'])) {
    foreach ($catSeoResponse['body']['data'] as $url) {
        $attrs = $url['attributes'] ?? $url;
        $foreignKey = $attrs['foreignKey'] ?? '';
        if ($foreignKey) {
            $categorySeoUrls[$foreignKey] = rtrim($attrs['seoPathInfo'] ?? '', '/');
        }
    }
}
echo "   Found " . count($categorySeoUrls) . " category canonical URLs\n\n";

// Step 2: Get all products with their main_category
echo "2. Fetching products with main categories...\n";
$mainCatResponse = apiRequest($baseUrl, $token, 'POST', '/search/main-category', [
    'limit' => 500
]);

$productCategories = [];
if (isset($mainCatResponse['body']['data'])) {
    foreach ($mainCatResponse['body']['data'] as $mc) {
        $attrs = $mc['attributes'] ?? $mc;
        $productId = $attrs['productId'] ?? '';
        $categoryId = $attrs['categoryId'] ?? '';

        if ($productId && $categoryId) {
            $productCategories[$productId] = $categoryId;
        }
    }
}
echo "   Found " . count($productCategories) . " product-category assignments\n\n";

// Step 3: Get all products
echo "3. Fetching all products...\n";
$productsResponse = apiRequest($baseUrl, $token, 'POST', '/search/product', [
    'limit' => 500
]);

$products = [];
if (isset($productsResponse['body']['data'])) {
    foreach ($productsResponse['body']['data'] as $prod) {
        $attrs = $prod['attributes'] ?? $prod;
        $products[$prod['id']] = [
            'id' => $prod['id'],
            'name' => $attrs['translated']['name'] ?? $attrs['name'] ?? 'Unknown'
        ];
    }
}
echo "   Found " . count($products) . " products\n\n";

// Step 4: Get all current product SEO URLs
echo "4. Fetching current product SEO URLs...\n";
$prodSeoResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
    'limit' => 2000,
    'filter' => [
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.detail.page']
    ]
]);

$currentProductUrls = [];
if (isset($prodSeoResponse['body']['data'])) {
    foreach ($prodSeoResponse['body']['data'] as $url) {
        $attrs = $url['attributes'] ?? $url;
        $foreignKey = $attrs['foreignKey'] ?? '';
        if (!isset($currentProductUrls[$foreignKey])) {
            $currentProductUrls[$foreignKey] = [];
        }
        $currentProductUrls[$foreignKey][] = [
            'id' => $url['id'],
            'seoPathInfo' => $attrs['seoPathInfo'] ?? '',
            'isCanonical' => $attrs['isCanonical'] ?? false
        ];
    }
}
echo "   Found " . count($currentProductUrls) . " products with SEO URLs\n\n";

// Step 5: Get sales channel
$scResponse = apiRequest($baseUrl, $token, 'POST', '/search/sales-channel', ['limit' => 10]);
$salesChannelId = null;
$languageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
if (isset($scResponse['body']['data'])) {
    foreach ($scResponse['body']['data'] as $sc) {
        $attrs = $sc['attributes'] ?? $sc;
        if (($attrs['typeId'] ?? '') === '8a243080f92e4c719546314b577cf82b') {
            $salesChannelId = $sc['id'];
            break;
        }
    }
}

if (!$salesChannelId) {
    echo "ERROR: Could not find storefront sales channel\n";
    exit(1);
}
echo "Using Sales Channel: {$salesChannelId}\n\n";

// Step 6: Analyze and fix
echo "5. Analyzing and fixing product URLs...\n\n";

$fixed = 0;
$errors = 0;
$skipped = 0;

foreach ($products as $productId => $product) {
    // Get the category for this product
    if (!isset($productCategories[$productId])) {
        $skipped++;
        continue;
    }

    $categoryId = $productCategories[$productId];

    // Get the category SEO path
    if (!isset($categorySeoUrls[$categoryId])) {
        echo "   [{$product['name']}] No category URL for category {$categoryId}\n";
        $skipped++;
        continue;
    }

    $categoryPath = $categorySeoUrls[$categoryId];
    $productSlug = slugify($product['name']);
    $expectedPath = $categoryPath . '/' . $productSlug;

    // Check current URLs
    $currentUrls = $currentProductUrls[$productId] ?? [];
    $hasCorrectCanonical = false;

    foreach ($currentUrls as $url) {
        if ($url['seoPathInfo'] === $expectedPath && $url['isCanonical']) {
            $hasCorrectCanonical = true;
            break;
        }
    }

    if ($hasCorrectCanonical) {
        continue; // Already correct
    }

    // Need to fix this product
    echo "   Fixing: {$product['name']}\n";
    echo "      Expected: {$expectedPath}\n";

    // Delete all old URLs for this product
    foreach ($currentUrls as $url) {
        $deleteResponse = apiRequest($baseUrl, $token, 'DELETE', '/seo-url/' . $url['id']);
        if ($deleteResponse['code'] !== 204) {
            echo "      Warning: Could not delete {$url['seoPathInfo']}\n";
        }
    }

    // Create new canonical URL
    $createResponse = apiRequest($baseUrl, $token, 'POST', '/seo-url', [
        'foreignKey' => $productId,
        'routeName' => 'frontend.detail.page',
        'pathInfo' => '/detail/' . $productId,
        'seoPathInfo' => $expectedPath,
        'isCanonical' => true,
        'salesChannelId' => $salesChannelId,
        'languageId' => $languageId
    ]);

    if ($createResponse['code'] === 204 || $createResponse['code'] === 200) {
        echo "      OK\n";
        $fixed++;
    } else {
        echo "      ERROR: " . substr($createResponse['raw'], 0, 150) . "\n";
        $errors++;
    }
}

echo "\n=== Summary ===\n";
echo "Fixed: {$fixed}\n";
echo "Skipped (no category): {$skipped}\n";
echo "Errors: {$errors}\n";
echo "\nPlease clear the cache: docker exec shopware-chf bin/console cache:clear\n";
