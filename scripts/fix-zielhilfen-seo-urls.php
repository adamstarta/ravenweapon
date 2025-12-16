<?php
/**
 * Fix Zielhilfen, Optik & Zubehör SEO URLs
 *
 * This script fixes:
 * 1. Category SEO URLs: /spektive/ → /Zielhilfen-Optik-Zubehoer/Spektive/
 * 2. Product SEO URLs: /detail/UUID → /Zielhilfen-Optik-Zubehoer/Spektive/product-slug
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

echo "\n======================================================================\n";
echo "     FIX ZIELHILFEN, OPTIK & ZUBEHOER SEO URLS\n";
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

/**
 * Create ASCII-safe slug
 */
function createSlug($text) {
    $replacements = [
        'Ä' => 'Ae', 'ä' => 'ae',
        'Ö' => 'Oe', 'ö' => 'oe',
        'Ü' => 'Ue', 'ü' => 'ue',
        'ß' => 'ss',
        'Å' => 'A', 'å' => 'a',
        'Ø' => 'O', 'ø' => 'o',
        'Æ' => 'Ae', 'æ' => 'ae',
        '×' => 'x',
        '–' => '-', '—' => '-',
        '&' => '-',
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u',
        'Ñ' => 'N', 'ñ' => 'n',
        'Ç' => 'C', 'ç' => 'c',
    ];

    $text = str_replace(array_keys($replacements), array_values($replacements), $text);
    $slug = strtolower($text);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/\s+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');

    return $slug;
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

// Get language ID
$result = apiRequest('POST', '/search/language', ['limit' => 1], $config);
$languageId = $result['body']['data'][0]['id'] ?? null;
echo "Language ID: $languageId\n\n";

// Parent category slug
$parentSlug = 'Zielhilfen-Optik-Zubehoer';

// Define subcategories to fix (name => slug)
$subcategories = [
    'Spektive' => 'Spektive',
    'Zielfernrohre' => 'Zielfernrohre',
    'Ferngläser' => 'Fernglaeser',
    'Rotpunktvisiere' => 'Rotpunktvisiere',
];

echo "======================================================================\n";
echo "     STEP 1: FIX CATEGORY SEO URLS\n";
echo "======================================================================\n\n";

$categoryFixedCount = 0;
$categoryMap = []; // Store category ID -> slug mapping

foreach ($subcategories as $categoryName => $categorySlug) {
    echo "Looking for category: $categoryName\n";

    // Find category by name
    $result = apiRequest('POST', '/search/category', [
        'filter' => [['type' => 'equals', 'field' => 'name', 'value' => $categoryName]],
        'associations' => ['seoUrls' => []]
    ], $config);

    $category = $result['body']['data'][0] ?? null;
    if (!$category) {
        echo "  [NOT FOUND] Category '$categoryName' not found\n\n";
        continue;
    }

    $categoryId = $category['id'];
    $categoryMap[$categoryId] = [
        'name' => $categoryName,
        'slug' => $categorySlug,
    ];

    echo "  Category ID: $categoryId\n";

    // New SEO path for category
    $newCategorySeoPath = "$parentSlug/$categorySlug";
    echo "  New SEO path: /$newCategorySeoPath/\n";

    // Delete existing category SEO URLs
    $existingUrls = $category['seoUrls'] ?? [];
    foreach ($existingUrls as $url) {
        if ($url['routeName'] === 'frontend.navigation.page') {
            apiRequest('DELETE', "/seo-url/{$url['id']}", null, $config);
        }
    }

    // Create new category SEO URL
    $seoUrlId = bin2hex(random_bytes(16));
    $result = apiRequest('POST', '/seo-url', [
        'id' => $seoUrlId,
        'salesChannelId' => $salesChannelId,
        'languageId' => $languageId,
        'foreignKey' => $categoryId,
        'routeName' => 'frontend.navigation.page',
        'pathInfo' => '/navigation/' . $categoryId,
        'seoPathInfo' => $newCategorySeoPath,
        'isCanonical' => true,
        'isModified' => true,
    ], $config);

    if ($result['code'] === 204 || $result['code'] === 200) {
        echo "  [OK] Category SEO URL created\n\n";
        $categoryFixedCount++;
    } else {
        echo "  [ERROR] " . ($result['body']['errors'][0]['detail'] ?? json_encode($result['body'])) . "\n\n";
    }
}

echo "Categories fixed: $categoryFixedCount\n\n";

echo "======================================================================\n";
echo "     STEP 2: FIX PRODUCT SEO URLS\n";
echo "======================================================================\n\n";

$productFixedCount = 0;
$productErrorCount = 0;

foreach ($categoryMap as $categoryId => $categoryInfo) {
    $categoryName = $categoryInfo['name'];
    $categorySlug = $categoryInfo['slug'];

    echo "Processing products in: $categoryName\n";
    echo str_repeat('-', 60) . "\n";

    // Get all products in this category
    $result = apiRequest('POST', '/search/product', [
        'filter' => [
            ['type' => 'equals', 'field' => 'categories.id', 'value' => $categoryId]
        ],
        'associations' => [
            'seoUrls' => []
        ],
        'limit' => 500
    ], $config);

    $products = $result['body']['data'] ?? [];
    echo "  Found " . count($products) . " products\n\n";

    foreach ($products as $product) {
        $productId = $product['id'];
        $productName = $product['name'];
        $productNumber = $product['productNumber'] ?? '';

        // Create product slug
        $productSlug = createSlug($productName);
        if ($productNumber) {
            $numSlug = createSlug($productNumber);
            if ($numSlug) {
                $productSlug .= '-' . $numSlug;
            }
        }

        // New SEO path: ParentCategory/SubCategory/product-slug
        $newSeoPath = "$parentSlug/$categorySlug/$productSlug";

        echo "  $productName\n";
        echo "    -> /$newSeoPath\n";

        // Delete existing product SEO URLs
        $existingUrls = $product['seoUrls'] ?? [];
        foreach ($existingUrls as $url) {
            if ($url['routeName'] === 'frontend.detail.page') {
                apiRequest('DELETE', "/seo-url/{$url['id']}", null, $config);
            }
        }

        // Create new product SEO URL
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
            echo "    [OK]\n";
            $productFixedCount++;
        } else {
            echo "    [ERROR] " . ($result['body']['errors'][0]['detail'] ?? 'Unknown') . "\n";
            $productErrorCount++;
        }

        // Set main category for breadcrumbs
        apiRequest('POST', '/_action/sync', [
            [
                'action' => 'upsert',
                'entity' => 'main_category',
                'payload' => [
                    [
                        'productId' => $productId,
                        'categoryId' => $categoryId,
                        'salesChannelId' => $salesChannelId,
                    ]
                ]
            ]
        ], $config);
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
echo "Categories fixed: $categoryFixedCount\n";
echo "Products fixed: $productFixedCount\n";
echo "Errors: $productErrorCount\n\n";
echo "Test URLs:\n";
echo "  https://ortak.ch/$parentSlug/Spektive/\n";
echo "  https://ortak.ch/$parentSlug/Zielfernrohre/\n";
echo "  https://ortak.ch/$parentSlug/Fernglaeser/\n";
echo "  https://ortak.ch/$parentSlug/Rotpunktvisiere/\n";
