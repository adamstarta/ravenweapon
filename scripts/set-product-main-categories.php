<?php
/**
 * Set Main Categories for Products
 *
 * Problem: Products have category assignments but no "main category" set
 * Solution: For each product with categories, set its first category as main category
 * This enables Shopware to generate proper SEO URLs
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

echo "=== Set Product Main Categories ===\n\n";

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "Got API token\n\n";

// Get sales channel
echo "1. Getting sales channel...\n";
$scResponse = apiRequest($baseUrl, $token, 'POST', '/search/sales-channel', [
    'limit' => 1,
    'includes' => ['sales_channel' => ['id', 'name']]
]);
$salesChannelId = $scResponse['body']['data'][0]['id'] ?? null;
echo "   Sales Channel: $salesChannelId\n\n";

// Get existing main categories
echo "2. Getting existing main categories...\n";
$mainCatResponse = apiRequest($baseUrl, $token, 'POST', '/search/main-category', [
    'limit' => 500,
    'includes' => ['main_category' => ['id', 'productId', 'categoryId']]
]);

$existingMainCategories = [];
if (isset($mainCatResponse['body']['data'])) {
    foreach ($mainCatResponse['body']['data'] as $mc) {
        $attrs = $mc['attributes'] ?? $mc;
        $productId = $attrs['productId'] ?? '';
        if ($productId) {
            $existingMainCategories[$productId] = true;
        }
    }
}
echo "   Found " . count($existingMainCategories) . " products with main categories\n\n";

// Get all products with their categories
echo "3. Getting all products with categories...\n";
$page = 1;
$limit = 100;
$productsToSet = [];

while (true) {
    $productResponse = apiRequest($baseUrl, $token, 'POST', '/search/product', [
        'limit' => $limit,
        'page' => $page,
        'associations' => [
            'categories' => []
        ],
        'includes' => [
            'product' => ['id', 'name', 'categories'],
            'category' => ['id', 'name']
        ]
    ]);

    if (!isset($productResponse['body']['data']) || count($productResponse['body']['data']) === 0) {
        break;
    }

    foreach ($productResponse['body']['data'] as $product) {
        $productId = $product['id'];
        $attrs = $product['attributes'] ?? $product;
        $name = $attrs['name'] ?? 'Unknown';
        $categories = $product['categories'] ?? $attrs['categories'] ?? [];

        // Skip if already has main category or no categories
        if (isset($existingMainCategories[$productId])) {
            continue;
        }

        if (empty($categories)) {
            continue;
        }

        // Use first category as main category
        $firstCategoryId = $categories[0]['id'] ?? null;
        if ($firstCategoryId) {
            $productsToSet[] = [
                'productId' => $productId,
                'name' => $name,
                'categoryId' => $firstCategoryId
            ];
        }
    }

    if (count($productResponse['body']['data']) < $limit) break;
    $page++;
}

echo "   Found " . count($productsToSet) . " products needing main category\n\n";

if (count($productsToSet) === 0) {
    echo "All products already have main categories set!\n";
    exit(0);
}

// Set main categories
echo "4. Setting main categories...\n\n";
$created = 0;
$errors = 0;

foreach ($productsToSet as $item) {
    $mainCategoryData = [
        'productId' => $item['productId'],
        'categoryId' => $item['categoryId'],
        'salesChannelId' => $salesChannelId
    ];

    $response = apiRequest($baseUrl, $token, 'POST', '/main-category', $mainCategoryData);

    if ($response['code'] >= 200 && $response['code'] < 300) {
        echo "   ✓ {$item['name']}\n";
        $created++;
    } else {
        $error = $response['body']['errors'][0]['detail'] ?? 'Unknown error';
        if (strpos($error, 'Duplicate') !== false) {
            echo "   ~ {$item['name']} (already exists)\n";
        } else {
            echo "   ✗ {$item['name']} - " . substr($error, 0, 100) . "\n";
            $errors++;
        }
    }
}

echo "\n=== SUMMARY ===\n";
echo "Main categories set: $created\n";
echo "Errors: $errors\n\n";

echo "=== NEXT STEPS ===\n";
echo "1. Regenerate product indexes:\n";
echo "   docker exec shopware-chf bash -c 'cd /var/www/html && bin/console dal:refresh:index --only=product.indexer'\n\n";
echo "2. Clear cache:\n";
echo "   docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'\n";
