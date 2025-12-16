<?php
/**
 * Fix Ausrüstung SEO URLs on ortak.ch
 * Uses ortak.ch API endpoint
 */

$config = [
    'base_url' => 'https://ortak.ch',
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
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "CURL Error: $error\n";
        return null;
    }

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
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
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

function makeUrlFriendly($name) {
    $urlName = str_replace(
        [' & ', ' ', 'ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'],
        ['-', '-', 'ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'],
        $name
    );
    $urlName = preg_replace('/[^a-zA-Z0-9-]/', '', $urlName);
    $urlName = preg_replace('/-+/', '-', $urlName);
    return $urlName;
}

echo "=== Fixing SEO URLs on ortak.ch ===\n\n";

$token = getAccessToken($config);
if (!$token) {
    die("Failed to get token\n");
}
echo "Got token\n\n";

// Get sales channel
$scResult = apiRequest('POST', '/search/sales-channel', [
    'limit' => 10,
    'includes' => ['sales_channel' => ['id', 'name']]
], $token, $config);

echo "Sales Channels:\n";
foreach ($scResult['body']['data'] ?? [] as $sc) {
    echo "  - " . $sc['name'] . " (" . $sc['id'] . ")\n";
}
$salesChannelId = $scResult['body']['data'][0]['id'] ?? null;
echo "\nUsing: $salesChannelId\n\n";

// Get languages
$langResult = apiRequest('POST', '/search/language', [
    'limit' => 10,
    'includes' => ['language' => ['id', 'name']]
], $token, $config);

echo "Languages:\n";
$languageId = null;
foreach ($langResult['body']['data'] ?? [] as $lang) {
    echo "  - " . $lang['name'] . " (" . $lang['id'] . ")\n";
    // Use English - the sales channel default language!
    if (strpos($lang['name'], 'English') !== false || $lang['name'] === 'English') {
        $languageId = $lang['id'];
    }
}
if (!$languageId && isset($langResult['body']['data'][0])) {
    $languageId = $langResult['body']['data'][0]['id'];
}
echo "\nUsing ENGLISH: $languageId\n\n";

// Find Ausrüstung category
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Ausrüstung']],
    'includes' => ['category' => ['id', 'name']]
], $token, $config);

if (!isset($result['body']['data'][0])) {
    die("Ausrüstung category not found\n");
}

$ausruestung = $result['body']['data'][0];
echo "Found Ausrüstung: " . $ausruestung['id'] . "\n\n";

// Check existing SEO URLs
echo "=== Current SEO URLs ===\n";
$seoResult = apiRequest('POST', '/search/seo-url', [
    'filter' => [
        ['type' => 'equals', 'field' => 'foreignKey', 'value' => $ausruestung['id']]
    ],
    'includes' => ['seo_url' => ['id', 'seoPathInfo', 'isCanonical', 'salesChannelId', 'languageId']],
    'limit' => 20
], $token, $config);

foreach ($seoResult['body']['data'] ?? [] as $seo) {
    echo "  /" . $seo['seoPathInfo'];
    echo " | SC: " . ($seo['salesChannelId'] ?? 'null');
    echo " | Lang: " . ($seo['languageId'] ?? 'null');
    echo ($seo['isCanonical'] ? ' (canonical)' : '') . "\n";
}

// Delete all old SEO URLs for Ausrüstung
echo "\n=== Deleting old SEO URLs ===\n";
foreach ($seoResult['body']['data'] ?? [] as $seo) {
    echo "Deleting /" . $seo['seoPathInfo'] . "... ";
    $delResult = apiRequest('DELETE', '/seo-url/' . $seo['id'], null, $token, $config);
    echo ($delResult['code'] < 300 ? "OK" : "FAIL " . $delResult['code']) . "\n";
}

// Create new SEO URL
echo "\n=== Creating new /Ausruestung/ SEO URL ===\n";
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
$createResult = apiRequest('POST', '/seo-url', $seoData, $token, $config);
echo "Creating /Ausruestung/: " . ($createResult['code'] < 300 ? "OK" : "FAIL " . json_encode($createResult['body'])) . "\n";

// Run indexer
echo "\n=== Running SEO indexer ===\n";
$indexResult = apiRequest('POST', '/_action/indexing/seo_url.indexer', [], $token, $config);
echo "Indexer: " . ($indexResult['code'] < 300 ? "OK" : "Code " . $indexResult['code']) . "\n";

// Clear cache
echo "\n=== Clearing cache ===\n";
$cacheResult = apiRequest('DELETE', '/_action/cache', null, $token, $config);
echo "Cache: " . ($cacheResult['code'] < 300 ? "OK" : "FAIL") . "\n";

echo "\n=== DONE ===\n";
echo "Test: https://ortak.ch/Ausruestung/\n";
