<?php
/**
 * Fix Munition SEO URL - Final Fix
 * Deletes and recreates the SEO URL properly
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

echo "\n======================================================================\n";
echo "     FIX MUNITION CATEGORY SEO URL - FINAL\n";
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

// Find Munition category
echo "Finding Munition category...\n";
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Munition']]
], $config);
$categoryId = $result['body']['data'][0]['id'] ?? null;
if (!$categoryId) die("ERROR: Munition category not found!\n");
echo "Category ID: $categoryId\n\n";

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

// Find and delete ALL existing SEO URLs for this category
echo "Finding existing SEO URLs for Munition category...\n";
$result = apiRequest('POST', '/search/seo-url', [
    'filter' => [
        ['type' => 'equals', 'field' => 'foreignKey', 'value' => $categoryId],
    ],
    'limit' => 100
], $config);

$existingUrls = $result['body']['data'] ?? [];
echo "Found " . count($existingUrls) . " existing SEO URLs\n";

foreach ($existingUrls as $url) {
    echo "  - /{$url['seoPathInfo']} (ID: {$url['id']})\n";
    // Delete it
    $delResult = apiRequest('DELETE', "/seo-url/{$url['id']}", null, $config);
    if ($delResult['code'] === 204) {
        echo "    DELETED\n";
    } else {
        echo "    Delete failed: HTTP {$delResult['code']}\n";
    }
}

echo "\nCreating fresh SEO URL...\n";

// Create new SEO URL with proper format
$seoUrlId = bin2hex(random_bytes(16));
$seoUrlData = [
    'id' => $seoUrlId,
    'salesChannelId' => $salesChannelId,
    'languageId' => $languageId,
    'foreignKey' => $categoryId,
    'routeName' => 'frontend.navigation.page',
    'pathInfo' => '/navigation/' . $categoryId,
    'seoPathInfo' => 'Munition',
    'isCanonical' => true,
    'isModified' => true,
    'isDeleted' => false,
];

$result = apiRequest('POST', '/seo-url', $seoUrlData, $config);

if ($result['code'] === 204 || $result['code'] === 200) {
    echo "OK - SEO URL created: /Munition\n";
} else {
    echo "ERROR: " . json_encode($result['body']) . "\n";
}

// Clear all caches
echo "\nClearing caches...\n";
apiRequest('DELETE', '/_action/cache', null, $config);
echo "Cache cleared\n";

// Trigger indexer
echo "Triggering SEO URL indexer...\n";
$result = apiRequest('POST', '/_action/index', ['skip' => []], $config);
if ($result['code'] === 200 || $result['code'] === 204) {
    echo "Indexer triggered\n";
}

echo "\n======================================================================\n";
echo "     DONE!\n";
echo "======================================================================\n";
echo "Try accessing: https://ortak.ch/Munition/\n";
echo "Or via navigation: https://ortak.ch/navigation/$categoryId\n\n";
