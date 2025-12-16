<?php
/**
 * Fix Ausrüstung SEO URLs - change from /Snigel/ to /Ausruestung/
 */

$config = [
    'base_url' => 'http://localhost',
    'client_id' => 'SWIARAVEN03399CEA2C931269',
    'client_secret' => 'RavenNavbarUpdate2025!'
];

function getAccessToken($config) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['base_url'] . '/api/oauth/token',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'client_credentials',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret']
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function apiRequest($method, $endpoint, $data, $token, $config) {
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $config['base_url'] . '/api' . $endpoint,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true
    ];
    if ($data !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

function generateUuid() {
    return sprintf('%04x%04x%04x%04x%04x%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
}

$token = getAccessToken($config);
if (!$token) {
    die("Failed to get token\n");
}
echo "Got token\n\n";

// Get sales channel ID
$scResult = apiRequest('POST', '/search/sales-channel', [
    'limit' => 1,
    'includes' => ['sales_channel' => ['id', 'name']]
], $token, $config);
$salesChannelId = $scResult['body']['data'][0]['id'] ?? null;
echo "Sales Channel ID: $salesChannelId\n\n";

// Get language ID
$langResult = apiRequest('POST', '/search/language', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Deutsch']],
    'limit' => 1,
    'includes' => ['language' => ['id', 'name']]
], $token, $config);
$languageId = $langResult['body']['data'][0]['id'] ?? null;
if (!$languageId) {
    // Try Swiss German
    $langResult = apiRequest('POST', '/search/language', [
        'limit' => 1,
        'includes' => ['language' => ['id', 'name']]
    ], $token, $config);
    $languageId = $langResult['body']['data'][0]['id'] ?? null;
}
echo "Language ID: $languageId\n\n";

// Find Ausrüstung category
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Ausrüstung']],
    'includes' => ['category' => ['id', 'name']]
], $token, $config);

if (!isset($result['body']['data'][0])) {
    die("Ausrüstung not found\n");
}

$ausruestung = $result['body']['data'][0];
echo "=== UPDATING SEO URLs ===\n\n";

// Update main category SEO URL
echo "1. Main category: Ausrüstung\n";
$seoData = [
    'id' => generateUuid(),
    'salesChannelId' => $salesChannelId,
    'languageId' => $languageId,
    'foreignKey' => $ausruestung['id'],
    'routeName' => 'frontend.navigation.page',
    'pathInfo' => '/navigation/' . $ausruestung['id'],
    'seoPathInfo' => 'Ausruestung/',
    'isCanonical' => true,
    'isModified' => true
];
$updateResult = apiRequest('POST', '/seo-url', $seoData, $token, $config);
echo "   -> /Ausruestung/ " . ($updateResult['code'] < 300 ? "OK" : "FAIL: " . json_encode($updateResult['body'])) . "\n";

// Get subcategories
$subResult = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $ausruestung['id']]],
    'includes' => ['category' => ['id', 'name']],
    'limit' => 50
], $token, $config);

echo "\n2. Subcategories:\n";
foreach ($subResult['body']['data'] ?? [] as $sub) {
    // Create URL-friendly name
    $urlName = str_replace(
        [' & ', ' ', 'ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'],
        ['-', '-', 'ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'],
        $sub['name']
    );
    $urlName = preg_replace('/[^a-zA-Z0-9-]/', '', $urlName);
    $urlName = preg_replace('/-+/', '-', $urlName);

    $seoPath = 'Ausruestung/' . $urlName . '/';

    $seoData = [
        'id' => generateUuid(),
        'salesChannelId' => $salesChannelId,
        'languageId' => $languageId,
        'foreignKey' => $sub['id'],
        'routeName' => 'frontend.navigation.page',
        'pathInfo' => '/navigation/' . $sub['id'],
        'seoPathInfo' => $seoPath,
        'isCanonical' => true,
        'isModified' => true
    ];

    $updateResult = apiRequest('POST', '/seo-url', $seoData, $token, $config);
    $status = $updateResult['code'] < 300 ? "OK" : "FAIL";
    echo "   -> /$seoPath ($status)\n";
}

// Clear cache
echo "\n3. Clearing cache...\n";
$cacheResult = apiRequest('DELETE', '/_action/cache', null, $token, $config);
echo "   Cache cleared: " . ($cacheResult['code'] < 300 ? "OK" : "FAIL") . "\n";

echo "\n=== DONE ===\n";
echo "All SEO URLs updated to use /Ausruestung/ prefix\n";
