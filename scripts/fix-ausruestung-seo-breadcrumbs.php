<?php
/**
 * Fix Ausrüstung Product SEO URLs and Breadcrumbs
 *
 * Problem: Products use /Snigel/... URLs instead of /Ausruestung/... URLs
 * Solution: Update SEO URLs and main category to use Ausrüstung path
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

echo "\n======================================================================\n";
echo "     FIX AUSRÜSTUNG SEO URLs & BREADCRUMBS\n";
echo "======================================================================\n\n";

$GLOBALS['token_data'] = ['token' => null, 'expires_at' => 0];

function getAccessToken($config, $forceRefresh = false) {
    if (!$forceRefresh && $GLOBALS['token_data']['token'] && $GLOBALS['token_data']['expires_at'] > time() + 60) {
        return $GLOBALS['token_data']['token'];
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . '/api/oauth/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => $config['api_user'],
            'password' => $config['api_password'],
        ]),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) return null;
    $data = json_decode($response, true);
    $GLOBALS['token_data']['token'] = $data['access_token'] ?? null;
    $GLOBALS['token_data']['expires_at'] = time() + ($data['expires_in'] ?? 600);
    return $GLOBALS['token_data']['token'];
}

function apiRequest($method, $endpoint, $data, $config, $retry = true) {
    $token = getAccessToken($config);
    if (!$token) return ['code' => 0, 'body' => null];
    $ch = curl_init();
    $url = $config['shopware_url'] . '/api/' . ltrim($endpoint, '/');
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    if ($data && in_array($method, ['POST', 'PATCH', 'PUT'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 401 && $retry) {
        $GLOBALS['token_data']['token'] = null;
        return apiRequest($method, $endpoint, $data, $config, false);
    }
    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

// Authenticate
$token = getAccessToken($config);
if (!$token) die("ERROR: Failed to authenticate!\n");
echo "Authenticated OK\n\n";

// Get sales channel
$result = apiRequest('POST', '/search/sales-channel', [
    'filter' => [['type' => 'contains', 'field' => 'name', 'value' => 'Storefront']]
], $config);
$salesChannelId = $result['body']['data'][0]['id'] ?? null;
echo "Sales Channel ID: $salesChannelId\n";

// Get language
$result = apiRequest('POST', '/search/language', ['limit' => 1], $config);
$languageId = $result['body']['data'][0]['id'] ?? null;
echo "Language ID: $languageId\n\n";

// Step 1: Get Ausrüstung category and all its subcategories
echo "Step 1: Finding Ausrüstung categories...\n";
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Ausrüstung']]
], $config);
$ausruestungId = $result['body']['data'][0]['id'] ?? null;
echo "  Ausrüstung ID: $ausruestungId\n";

// Get all subcategories of Ausrüstung
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $ausruestungId]]
], $config);
$subcategories = $result['body']['data'] ?? [];
echo "  Found " . count($subcategories) . " subcategories:\n";

$categoryMap = [];
foreach ($subcategories as $sub) {
    echo "    - {$sub['name']} (ID: {$sub['id']})\n";
    $categoryMap[$sub['id']] = $sub['name'];
}
echo "\n";

// Step 2: For each subcategory, get products and fix their SEO URLs
echo "Step 2: Processing products in each subcategory...\n\n";

$fixedCount = 0;
$errorCount = 0;

foreach ($subcategories as $subcategory) {
    $subId = $subcategory['id'];
    $subName = $subcategory['name'];

    // Create URL-friendly version of category name
    $subNameUrl = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß-]/', '-', $subName);
    $subNameUrl = preg_replace('/-+/', '-', $subNameUrl);
    $subNameUrl = trim($subNameUrl, '-');

    echo "Processing: $subName\n";
    echo "  Category URL segment: $subNameUrl\n";

    // Get products in this category
    $result = apiRequest('POST', '/search/product', [
        'filter' => [
            ['type' => 'equals', 'field' => 'categories.id', 'value' => $subId]
        ],
        'associations' => [
            'seoUrls' => [],
            'categories' => []
        ],
        'limit' => 500
    ], $config);

    $products = $result['body']['data'] ?? [];
    echo "  Found " . count($products) . " products\n";

    foreach ($products as $product) {
        $productId = $product['id'];
        $productName = $product['name'];
        $productNumber = $product['productNumber'] ?? '';

        // Create SEO-friendly product slug
        $productSlug = strtolower($productName);
        $productSlug = preg_replace('/[^a-z0-9äöüß\s-]/i', '', $productSlug);
        $productSlug = preg_replace('/\s+/', '-', $productSlug);
        $productSlug = preg_replace('/-+/', '-', $productSlug);
        $productSlug = trim($productSlug, '-');
        if ($productNumber) {
            $productSlug .= '-' . strtolower(preg_replace('/[^a-z0-9-]/i', '-', $productNumber));
        }

        // Build the new SEO path: Ausruestung/SubcategoryName/product-slug
        $newSeoPath = "Ausruestung/$subNameUrl/$productSlug";

        echo "    Product: $productName\n";
        echo "      New SEO path: /$newSeoPath\n";

        // Delete existing SEO URLs for this product
        $existingUrls = $product['seoUrls'] ?? [];
        foreach ($existingUrls as $url) {
            if ($url['routeName'] === 'frontend.detail.page') {
                apiRequest('DELETE', "/seo-url/{$url['id']}", null, $config);
            }
        }

        // Create new SEO URL
        $seoUrlId = bin2hex(random_bytes(16));
        $result = apiRequest('POST', '/seo-url', [
            'id' => $seoUrlId,
            'salesChannelId' => $salesChannelId,
            'languageId' => $languageId,
            'foreignKey' => $productId,
            'routeName' => 'frontend.detail.page',
            'pathInfo' => '/detail/' . $productId,
            'seoPathInfo' => $newSeoPath,
            'isCanonical' => true,
            'isModified' => true,
        ], $config);

        if ($result['code'] === 204 || $result['code'] === 200) {
            echo "      SEO URL: OK\n";
        } else {
            echo "      SEO URL: ERROR - " . ($result['body']['errors'][0]['detail'] ?? 'Unknown') . "\n";
            $errorCount++;
            continue;
        }

        // Update product's main category to this Ausrüstung subcategory
        // This is done via product.mainCategories which sets the breadcrumb path
        $result = apiRequest('POST', '/_action/sync', [
            [
                'action' => 'upsert',
                'entity' => 'main_category',
                'payload' => [
                    [
                        'productId' => $productId,
                        'categoryId' => $subId,
                        'salesChannelId' => $salesChannelId,
                    ]
                ]
            ]
        ], $config);

        if ($result['code'] === 200) {
            echo "      Main category: OK\n";
            $fixedCount++;
        } else {
            echo "      Main category: ERROR\n";
            $errorCount++;
        }
    }

    echo "\n";
}

// Clear cache
echo "Clearing cache...\n";
apiRequest('DELETE', '/_action/cache', null, $config);
echo "Cache cleared\n";

echo "\n======================================================================\n";
echo "     DONE!\n";
echo "======================================================================\n";
echo "Fixed: $fixedCount products\n";
echo "Errors: $errorCount\n\n";
echo "Test URL: https://ortak.ch/Ausruestung/Verwaltungsausruestung/\n";
