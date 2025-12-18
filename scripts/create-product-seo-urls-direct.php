<?php
/**
 * Create Product SEO URLs Directly
 *
 * Problem: Shopware's SEO URL template can't render because seoCategory is not computed
 * Solution: Manually create SEO URLs based on product's main category's SEO URL path
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
    $replacements = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
        ' & ' => '-', '&' => '-', ' ' => '-', ',' => '', '/' => '-',
        '(' => '', ')' => '', "'" => '', '"' => '', '.' => ''
    ];
    $text = str_replace(array_keys($replacements), array_values($replacements), $text);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9-]/', '', $text);
    $text = preg_replace('/-+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

echo "=== Create Product SEO URLs Directly ===\n\n";

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

// Get category SEO URLs
echo "2. Getting category SEO URL paths...\n";
$catSeoResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
    'limit' => 500,
    'filter' => [
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page'],
        ['type' => 'equals', 'field' => 'isCanonical', 'value' => true],
        ['type' => 'equals', 'field' => 'isDeleted', 'value' => false]
    ],
    'includes' => [
        'seo_url' => ['id', 'foreignKey', 'seoPathInfo']
    ]
]);

$categorySeoUrls = [];
if (isset($catSeoResponse['body']['data'])) {
    foreach ($catSeoResponse['body']['data'] as $url) {
        $attrs = $url['attributes'] ?? $url;
        $categoryId = $attrs['foreignKey'] ?? '';
        $path = rtrim($attrs['seoPathInfo'] ?? '', '/');
        if ($categoryId && $path) {
            $categorySeoUrls[$categoryId] = $path;
        }
    }
}
echo "   Found SEO URLs for " . count($categorySeoUrls) . " categories\n\n";

// Get main categories
echo "3. Getting product main categories...\n";
$mainCatResponse = apiRequest($baseUrl, $token, 'POST', '/search/main-category', [
    'limit' => 500,
    'includes' => ['main_category' => ['productId', 'categoryId']]
]);

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
echo "   Found main categories for " . count($productMainCategories) . " products\n\n";

// Get all products
echo "4. Getting all products...\n";
$page = 1;
$limit = 100;
$allProducts = [];

while (true) {
    $productResponse = apiRequest($baseUrl, $token, 'POST', '/search/product', [
        'limit' => $limit,
        'page' => $page,
        'includes' => [
            'product' => ['id', 'name', 'translated']
        ]
    ]);

    if (!isset($productResponse['body']['data']) || count($productResponse['body']['data']) === 0) {
        break;
    }

    foreach ($productResponse['body']['data'] as $product) {
        $attrs = $product['attributes'] ?? $product;
        $name = $attrs['translated']['name'] ?? $attrs['name'] ?? 'Unknown';
        $allProducts[$product['id']] = $name;
    }

    if (count($productResponse['body']['data']) < $limit) break;
    $page++;
}
echo "   Found " . count($allProducts) . " products\n\n";

// Check existing product SEO URLs
echo "5. Checking existing product SEO URLs...\n";
$existingSeoUrls = [];
$page = 1;
while (true) {
    $seoResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
        'limit' => 500,
        'page' => $page,
        'filter' => [
            ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.detail.page'],
            ['type' => 'equals', 'field' => 'isDeleted', 'value' => false]
        ],
        'includes' => [
            'seo_url' => ['id', 'foreignKey', 'seoPathInfo', 'isCanonical']
        ]
    ]);

    if (!isset($seoResponse['body']['data']) || count($seoResponse['body']['data']) === 0) {
        break;
    }

    foreach ($seoResponse['body']['data'] as $url) {
        $attrs = $url['attributes'] ?? $url;
        $productId = $attrs['foreignKey'] ?? '';
        if ($productId) {
            $existingSeoUrls[$productId] = [
                'id' => $url['id'],
                'path' => $attrs['seoPathInfo'] ?? ''
            ];
        }
    }

    if (count($seoResponse['body']['data']) < 500) break;
    $page++;
}
echo "   Found " . count($existingSeoUrls) . " existing product SEO URLs\n\n";

// Create SEO URLs for products that need them
echo "6. Creating SEO URLs for products...\n\n";
$created = 0;
$skipped = 0;
$errors = 0;

foreach ($allProducts as $productId => $productName) {
    // Skip if already has SEO URL
    if (isset($existingSeoUrls[$productId])) {
        continue;
    }

    // Get main category
    if (!isset($productMainCategories[$productId])) {
        $skipped++;
        continue;
    }

    $categoryId = $productMainCategories[$productId];

    // Get category SEO URL path
    if (!isset($categorySeoUrls[$categoryId])) {
        echo "   ! $productName - No SEO URL for category $categoryId\n";
        $skipped++;
        continue;
    }

    $categoryPath = $categorySeoUrls[$categoryId];
    $productSlug = slugify($productName);
    $seoPath = $categoryPath . '/' . $productSlug;

    // Create SEO URL
    $seoUrlData = [
        'foreignKey' => $productId,
        'routeName' => 'frontend.detail.page',
        'pathInfo' => '/detail/' . $productId,
        'seoPathInfo' => $seoPath,
        'salesChannelId' => $salesChannelId,
        'isCanonical' => true,
        'isModified' => true,
        'isDeleted' => false
    ];

    $response = apiRequest($baseUrl, $token, 'POST', '/seo-url', $seoUrlData);

    if ($response['code'] >= 200 && $response['code'] < 300) {
        echo "   ✓ /$seoPath\n";
        $created++;
    } else {
        $error = $response['body']['errors'][0]['detail'] ?? 'Unknown error';
        if (strpos($error, 'Duplicate') !== false || strpos($error, 'unique') !== false) {
            echo "   ~ /$seoPath (exists)\n";
        } else {
            echo "   ✗ /$seoPath - " . substr($error, 0, 80) . "\n";
            $errors++;
        }
    }
}

echo "\n=== SUMMARY ===\n";
echo "Created: $created\n";
echo "Skipped (no main category): $skipped\n";
echo "Errors: $errors\n";
echo "Already had SEO URL: " . count($existingSeoUrls) . "\n\n";

echo "=== NEXT STEPS ===\n";
echo "1. Clear cache:\n";
echo "   docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'\n\n";
echo "2. Test URLs like:\n";
echo "   https://ortak.ch/ausruestung/koerperschutz/westen-chest-rigs/covert-equipment-vest-12\n";
