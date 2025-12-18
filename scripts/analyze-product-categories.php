<?php
/**
 * Analyze products with multiple category assignments
 * This will help us understand the scope of the problem
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

echo "=== Analyze Product Categories ===\n\n";

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "Got token\n\n";

// Step 1: Get all categories with their names and paths
echo "1. Fetching all categories...\n";
$catResponse = apiRequest($baseUrl, $token, 'POST', '/search/category', [
    'limit' => 500,
    'includes' => [
        'category' => ['id', 'name', 'parentId', 'breadcrumb', 'translated']
    ]
]);

$categories = [];
if (isset($catResponse['body']['data'])) {
    foreach ($catResponse['body']['data'] as $cat) {
        $attrs = $cat['attributes'] ?? $cat;
        $breadcrumb = $attrs['translated']['breadcrumb'] ?? $attrs['breadcrumb'] ?? [];
        $categories[$cat['id']] = [
            'name' => $attrs['translated']['name'] ?? $attrs['name'] ?? 'Unknown',
            'breadcrumb' => $breadcrumb,
            'path' => implode(' > ', array_slice($breadcrumb, 1)) // Skip root
        ];
    }
}
echo "   Found " . count($categories) . " categories\n\n";

// Step 2: Get all products with their categories
echo "2. Fetching all products with categories...\n";
$productsResponse = apiRequest($baseUrl, $token, 'POST', '/search/product', [
    'limit' => 500,
    'associations' => [
        'categories' => []
    ],
    'includes' => [
        'product' => ['id', 'name', 'productNumber', 'categories', 'translated'],
        'category' => ['id', 'name']
    ]
]);

$products = [];
$multiCategoryProducts = [];
$singleCategoryProducts = [];
$noCategoryProducts = [];

if (isset($productsResponse['body']['data'])) {
    foreach ($productsResponse['body']['data'] as $prod) {
        $attrs = $prod['attributes'] ?? $prod;
        $name = $attrs['translated']['name'] ?? $attrs['name'] ?? 'Unknown';
        $productNumber = $attrs['productNumber'] ?? '';

        // Get categories
        $prodCategories = [];
        if (isset($prod['relationships']['categories']['data'])) {
            foreach ($prod['relationships']['categories']['data'] as $catRef) {
                $catId = $catRef['id'];
                if (isset($categories[$catId])) {
                    $prodCategories[$catId] = $categories[$catId];
                }
            }
        }

        $productData = [
            'id' => $prod['id'],
            'name' => $name,
            'productNumber' => $productNumber,
            'categoryCount' => count($prodCategories),
            'categories' => $prodCategories
        ];

        $products[$prod['id']] = $productData;

        if (count($prodCategories) === 0) {
            $noCategoryProducts[] = $productData;
        } elseif (count($prodCategories) === 1) {
            $singleCategoryProducts[] = $productData;
        } else {
            $multiCategoryProducts[] = $productData;
        }
    }
}

echo "   Found " . count($products) . " products total\n\n";

// Step 3: Print summary
echo "=== SUMMARY ===\n\n";
echo "Products with NO category:     " . count($noCategoryProducts) . "\n";
echo "Products with 1 category:      " . count($singleCategoryProducts) . "\n";
echo "Products with 2+ categories:   " . count($multiCategoryProducts) . "\n\n";

// Step 4: Show products with multiple categories
if (count($multiCategoryProducts) > 0) {
    echo "=== PRODUCTS WITH MULTIPLE CATEGORIES ===\n\n";

    foreach ($multiCategoryProducts as $i => $prod) {
        echo ($i + 1) . ". " . $prod['name'] . " (" . $prod['productNumber'] . ")\n";
        echo "   Categories (" . $prod['categoryCount'] . "):\n";
        foreach ($prod['categories'] as $catId => $cat) {
            echo "   - " . $cat['path'] . "\n";
        }
        echo "\n";
    }
}

// Step 5: Show products with no category
if (count($noCategoryProducts) > 0) {
    echo "=== PRODUCTS WITH NO CATEGORY ===\n\n";
    foreach ($noCategoryProducts as $prod) {
        echo "- " . $prod['name'] . " (" . $prod['productNumber'] . ")\n";
    }
    echo "\n";
}

echo "=== ANALYSIS COMPLETE ===\n";
