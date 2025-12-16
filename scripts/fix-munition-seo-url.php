<?php
/**
 * Fix Munition Category SEO URL
 * Creates the SEO URL for the Munition category so it's accessible at /Munition/
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

echo "\n";
echo "======================================================================\n";
echo "     FIX MUNITION CATEGORY SEO URL\n";
echo "======================================================================\n\n";

// Token management
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

    if ($httpCode !== 200) {
        echo "Auth failed: HTTP $httpCode\n";
        return null;
    }

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

// Step 1: Authenticate
echo "Step 1: Authenticating...\n";
$token = getAccessToken($config);
if (!$token) {
    die("  ERROR: Failed to authenticate!\n");
}
echo "  OK - Authenticated\n\n";

// Step 2: Find Munition category
echo "Step 2: Finding Munition category...\n";
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Munition']]
], $config);

if (empty($result['body']['data'][0]['id'])) {
    die("  ERROR: Munition category not found!\n");
}

$categoryId = $result['body']['data'][0]['id'];
echo "  Found Munition category: $categoryId\n\n";

// Step 3: Get sales channel ID
echo "Step 3: Getting sales channel...\n";
$result = apiRequest('POST', '/search/sales-channel', [
    'filter' => [['type' => 'contains', 'field' => 'name', 'value' => 'Storefront']]
], $config);
$salesChannelId = $result['body']['data'][0]['id'] ?? null;
echo "  Sales Channel ID: $salesChannelId\n\n";

// Step 4: Get language ID
echo "Step 4: Getting language ID...\n";
$result = apiRequest('POST', '/search/language', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Deutsch']]
], $config);
$languageId = $result['body']['data'][0]['id'] ?? null;

if (!$languageId) {
    // Try English
    $result = apiRequest('POST', '/search/language', [
        'limit' => 1
    ], $config);
    $languageId = $result['body']['data'][0]['id'] ?? null;
}
echo "  Language ID: $languageId\n\n";

// Step 5: Check existing SEO URLs for the category
echo "Step 5: Checking existing SEO URLs...\n";
$result = apiRequest('POST', '/search/seo-url', [
    'filter' => [
        ['type' => 'equals', 'field' => 'foreignKey', 'value' => $categoryId],
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page'],
    ]
], $config);

if (!empty($result['body']['data'])) {
    echo "  Existing SEO URLs found:\n";
    foreach ($result['body']['data'] as $seoUrl) {
        echo "    - /{$seoUrl['seoPathInfo']} (isCanonical: " . ($seoUrl['isCanonical'] ? 'yes' : 'no') . ")\n";
    }
    echo "\n";
}

// Step 6: Create/Update SEO URL
echo "Step 6: Creating SEO URL for Munition category...\n";

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
];

$result = apiRequest('POST', '/seo-url', $seoUrlData, $config);

if ($result['code'] === 204 || $result['code'] === 200) {
    echo "  OK - SEO URL created: /Munition/\n";
} else {
    echo "  ERROR creating SEO URL: " . json_encode($result['body']) . "\n";

    // Try updating via upsert
    echo "\n  Trying upsert method...\n";
    $result = apiRequest('POST', '/_action/sync', [
        [
            'action' => 'upsert',
            'entity' => 'seo_url',
            'payload' => [$seoUrlData]
        ]
    ], $config);

    if ($result['code'] === 200) {
        echo "  OK - SEO URL upserted successfully\n";
    } else {
        echo "  ERROR: " . json_encode($result['body']) . "\n";
    }
}

// Step 7: Clear cache via API
echo "\nStep 7: Clearing cache...\n";
$result = apiRequest('DELETE', '/_action/cache', null, $config);
if ($result['code'] === 204 || $result['code'] === 200) {
    echo "  OK - Cache cleared\n";
} else {
    echo "  Note: Cache clear via API may require SSH access\n";
}

echo "\n======================================================================\n";
echo "     DONE!\n";
echo "======================================================================\n";
echo "Try accessing: https://ortak.ch/Munition/\n\n";
