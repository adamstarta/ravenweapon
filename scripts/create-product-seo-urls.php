<?php
/**
 * Create product SEO URLs based on their main category breadcrumb
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

echo "=== Create Product SEO URLs ===\n\n";

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "Got token\n\n";

// Step 1: Get sales channel info
echo "1. Getting sales channel...\n";
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
echo "   Sales Channel: $salesChannelId\n\n";

// Step 2: Get all categories with their breadcrumbs
echo "2. Fetching all categories...\n";
$catResponse = apiRequest($baseUrl, $token, 'POST', '/search/category', ['limit' => 500]);
$categories = [];

if (isset($catResponse['body']['data'])) {
    foreach ($catResponse['body']['data'] as $cat) {
        $attrs = $cat['attributes'] ?? $cat;
        $breadcrumb = $attrs['translated']['breadcrumb'] ?? $attrs['breadcrumb'] ?? [];
        $categories[$cat['id']] = [
            'name' => $attrs['translated']['name'] ?? $attrs['name'] ?? '',
            'breadcrumb' => $breadcrumb
        ];
    }
}
echo "   Found " . count($categories) . " categories\n\n";

// Step 3: Get all main_category entries
echo "3. Fetching main category assignments...\n";
$mainCatResponse = apiRequest($baseUrl, $token, 'POST', '/search/main-category', ['limit' => 500]);
$productMainCategories = [];

if (isset($mainCatResponse['body']['data'])) {
    foreach ($mainCatResponse['body']['data'] as $mc) {
        $attrs = $mc['attributes'] ?? $mc;
        $productId = $attrs['productId'] ?? '';
        $categoryId = $attrs['categoryId'] ?? '';
        if ($productId && $categoryId) {
            $productMainCategories[$productId] = $categoryId;
        }
    }
}
echo "   Found " . count($productMainCategories) . " product-category assignments\n\n";

// Step 4: Get all products
echo "4. Fetching all products...\n";
$productsResponse = apiRequest($baseUrl, $token, 'POST', '/search/product', ['limit' => 500]);
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

// Step 5: Create SEO URLs
echo "5. Creating SEO URLs...\n\n";

$created = 0;
$skipped = 0;
$errors = 0;

foreach ($products as $productId => $product) {
    // Get main category for this product
    if (!isset($productMainCategories[$productId])) {
        $skipped++;
        continue;
    }

    $categoryId = $productMainCategories[$productId];

    if (!isset($categories[$categoryId])) {
        echo "   [{$product['name']}] Category $categoryId not found\n";
        $skipped++;
        continue;
    }

    $breadcrumb = $categories[$categoryId]['breadcrumb'];

    if (empty($breadcrumb) || count($breadcrumb) < 2) {
        echo "   [{$product['name']}] No valid breadcrumb\n";
        $skipped++;
        continue;
    }

    // Generate SEO path from breadcrumb (skip first element which is root)
    $pathParts = array_slice($breadcrumb, 1);
    $pathParts = array_map('slugify', $pathParts);
    $categoryPath = implode('/', $pathParts);

    $productSlug = slugify($product['name']);
    $seoPath = $categoryPath . '/' . $productSlug;

    // Create the SEO URL
    $createResponse = apiRequest($baseUrl, $token, 'POST', '/seo-url', [
        'foreignKey' => $productId,
        'routeName' => 'frontend.detail.page',
        'pathInfo' => '/detail/' . $productId,
        'seoPathInfo' => $seoPath,
        'isCanonical' => true,
        'salesChannelId' => $salesChannelId,
        'languageId' => $languageId
    ]);

    if ($createResponse['code'] === 204 || $createResponse['code'] === 200) {
        $created++;
        if ($created % 50 == 0) {
            echo "   Created $created URLs...\n";
        }
    } else {
        $errors++;
        $errorMsg = '';
        if (isset($createResponse['body']['errors'][0]['detail'])) {
            $errorMsg = $createResponse['body']['errors'][0]['detail'];
        } else {
            $errorMsg = substr($createResponse['raw'], 0, 100);
        }

        // Only show first 10 errors
        if ($errors <= 10) {
            echo "   ERROR [{$product['name']}]: $errorMsg\n";
        }
    }
}

echo "\n=== Summary ===\n";
echo "Created: $created\n";
echo "Skipped (no category): $skipped\n";
echo "Errors: $errors\n";
echo "\nRun: docker exec shopware-chf bin/console cache:clear\n";
