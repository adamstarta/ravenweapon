<?php
/**
 * Verify ALL Ausrüstung Products - SEO URLs & Accessibility
 *
 * This script:
 * 1. Gets all Ausrüstung subcategories
 * 2. Gets all products in each subcategory
 * 3. Tests each product's SEO URL is accessible
 * 4. Reports any products with issues
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

echo "\n======================================================================\n";
echo "     VERIFY ALL AUSRÜSTUNG PRODUCTS\n";
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
 * Test if a URL is accessible (returns 200)
 */
function testUrl($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_NOBODY => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return [
        'code' => $httpCode,
        'finalUrl' => $finalUrl,
        'hasContent' => strlen($response) > 1000,
    ];
}

// Authenticate
$token = getAccessToken($config);
if (!$token) die("ERROR: Failed to authenticate!\n");
echo "Authenticated OK\n\n";

// Get Ausrüstung category
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Ausrüstung']]
], $config);
$ausruestungId = $result['body']['data'][0]['id'] ?? null;
echo "Ausrüstung ID: $ausruestungId\n";

// Get sales channel
$result = apiRequest('POST', '/search/sales-channel', [
    'filter' => [['type' => 'contains', 'field' => 'name', 'value' => 'Storefront']]
], $config);
$salesChannelId = $result['body']['data'][0]['id'] ?? null;
echo "Sales Channel ID: $salesChannelId\n\n";

// Get all subcategories
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $ausruestungId]]
], $config);
$subcategories = $result['body']['data'] ?? [];
echo "Found " . count($subcategories) . " subcategories\n\n";

$totalProducts = 0;
$workingProducts = 0;
$brokenProducts = [];
$noSeoUrlProducts = [];

echo "======================================================================\n";
echo "     TESTING ALL PRODUCTS\n";
echo "======================================================================\n\n";

foreach ($subcategories as $subcategory) {
    $subId = $subcategory['id'];
    $subName = $subcategory['name'];

    echo "Category: $subName\n";
    echo str_repeat('-', 50) . "\n";

    // Get products in this category
    $result = apiRequest('POST', '/search/product', [
        'filter' => [
            ['type' => 'equals', 'field' => 'categories.id', 'value' => $subId]
        ],
        'associations' => [
            'seoUrls' => [
                'filter' => [
                    ['type' => 'equals', 'field' => 'salesChannelId', 'value' => $salesChannelId],
                    ['type' => 'equals', 'field' => 'isCanonical', 'value' => true],
                ]
            ]
        ],
        'limit' => 500
    ], $config);

    $products = $result['body']['data'] ?? [];
    echo "  Products: " . count($products) . "\n";

    foreach ($products as $product) {
        $totalProducts++;
        $productId = $product['id'];
        $productName = $product['name'];

        // Get canonical SEO URL
        $seoUrls = $product['seoUrls'] ?? [];
        $canonicalUrl = null;
        foreach ($seoUrls as $url) {
            if ($url['isCanonical'] && $url['routeName'] === 'frontend.detail.page') {
                $canonicalUrl = $url['seoPathInfo'];
                break;
            }
        }

        if (!$canonicalUrl) {
            echo "    [NO SEO URL] $productName\n";
            $noSeoUrlProducts[] = [
                'name' => $productName,
                'id' => $productId,
                'category' => $subName,
            ];
            continue;
        }

        // Check if URL contains Ausruestung
        if (strpos($canonicalUrl, 'Ausruestung') === false) {
            echo "    [WRONG PATH] $productName\n";
            echo "                 Path: /$canonicalUrl\n";
            $brokenProducts[] = [
                'name' => $productName,
                'id' => $productId,
                'category' => $subName,
                'seoUrl' => $canonicalUrl,
                'issue' => 'Wrong path - not /Ausruestung/',
            ];
            continue;
        }

        // Test the URL
        $fullUrl = $config['shopware_url'] . '/' . $canonicalUrl;
        $testResult = testUrl($fullUrl);

        if ($testResult['code'] === 200 && $testResult['hasContent']) {
            $workingProducts++;
            echo "    [OK] $productName\n";
        } else {
            echo "    [HTTP {$testResult['code']}] $productName\n";
            echo "                         URL: $fullUrl\n";
            $brokenProducts[] = [
                'name' => $productName,
                'id' => $productId,
                'category' => $subName,
                'seoUrl' => $canonicalUrl,
                'httpCode' => $testResult['code'],
                'issue' => "HTTP {$testResult['code']}",
            ];
        }
    }

    echo "\n";
}

echo "\n======================================================================\n";
echo "     VERIFICATION RESULTS\n";
echo "======================================================================\n\n";

echo "Total Products Tested: $totalProducts\n";
echo "Working Products: $workingProducts\n";
echo "Products without SEO URL: " . count($noSeoUrlProducts) . "\n";
echo "Broken Products: " . count($brokenProducts) . "\n\n";

$successRate = $totalProducts > 0 ? round(($workingProducts / $totalProducts) * 100, 2) : 0;
echo "SUCCESS RATE: $successRate%\n\n";

if (count($noSeoUrlProducts) > 0) {
    echo "----------------------------------------------------------------------\n";
    echo "PRODUCTS WITHOUT SEO URL:\n";
    echo "----------------------------------------------------------------------\n";
    foreach ($noSeoUrlProducts as $p) {
        echo "  - {$p['name']} (Category: {$p['category']})\n";
        echo "    ID: {$p['id']}\n";
    }
    echo "\n";
}

if (count($brokenProducts) > 0) {
    echo "----------------------------------------------------------------------\n";
    echo "BROKEN PRODUCTS:\n";
    echo "----------------------------------------------------------------------\n";
    foreach ($brokenProducts as $p) {
        echo "  - {$p['name']} (Category: {$p['category']})\n";
        echo "    Issue: {$p['issue']}\n";
        echo "    SEO URL: /{$p['seoUrl']}\n";
        echo "    ID: {$p['id']}\n\n";
    }
}

if ($successRate == 100) {
    echo "\n*** 100% SUCCESS - ALL PRODUCTS WORKING! ***\n\n";
}
