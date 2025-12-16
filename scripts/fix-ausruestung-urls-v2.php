<?php
/**
 * Fix Ausrüstung SEO URLs - Delete old /snigel-kategorie/ URLs and create /Ausruestung/ URLs
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
echo "Sales Channel ID: $salesChannelId\n";

// Get language ID
$langResult = apiRequest('POST', '/search/language', [
    'limit' => 10,
    'includes' => ['language' => ['id', 'name']]
], $token, $config);
echo "Available languages:\n";
$languageId = null;
foreach ($langResult['body']['data'] ?? [] as $lang) {
    echo "  - " . $lang['name'] . " (" . $lang['id'] . ")\n";
    if ($lang['name'] === 'Deutsch' || $lang['name'] === 'German' || strpos($lang['name'], 'Deutsch') !== false) {
        $languageId = $lang['id'];
    }
}
if (!$languageId && isset($langResult['body']['data'][0])) {
    $languageId = $langResult['body']['data'][0]['id'];
}
echo "Using Language ID: $languageId\n\n";

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

// Get all subcategories
$subResult = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $ausruestung['id']]],
    'includes' => ['category' => ['id', 'name']],
    'limit' => 50
], $token, $config);

$categories = array_merge([$ausruestung], $subResult['body']['data'] ?? []);

echo "=== STEP 1: Delete old SEO URLs ===\n\n";

foreach ($categories as $cat) {
    // Find existing SEO URLs for this category
    $seoResult = apiRequest('POST', '/search/seo-url', [
        'filter' => [
            ['type' => 'equals', 'field' => 'foreignKey', 'value' => $cat['id']],
            ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
        ],
        'includes' => ['seo_url' => ['id', 'seoPathInfo', 'isCanonical']],
        'limit' => 20
    ], $token, $config);

    if (isset($seoResult['body']['data'])) {
        foreach ($seoResult['body']['data'] as $seo) {
            // Delete URLs containing 'snigel' or 'Snigel'
            if (stripos($seo['seoPathInfo'], 'snigel') !== false) {
                echo "Deleting: /" . $seo['seoPathInfo'] . " for " . $cat['name'] . "... ";
                $delResult = apiRequest('DELETE', '/seo-url/' . $seo['id'], null, $token, $config);
                echo ($delResult['code'] < 300 ? "OK" : "FAIL") . "\n";
            }
        }
    }
}

echo "\n=== STEP 2: Create new /Ausruestung/ URLs ===\n\n";

// Create URL for main category
echo "Creating: /Ausruestung/ for Ausrüstung... ";
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
echo ($createResult['code'] < 300 ? "OK" : "FAIL: " . json_encode($createResult['body'])) . "\n";

// Create URLs for subcategories
foreach ($subResult['body']['data'] ?? [] as $sub) {
    $urlName = makeUrlFriendly($sub['name']);
    $seoPath = 'Ausruestung/' . $urlName . '/';

    echo "Creating: /$seoPath for " . $sub['name'] . "... ";

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

    $createResult = apiRequest('POST', '/seo-url', $seoData, $token, $config);
    echo ($createResult['code'] < 300 ? "OK" : "FAIL") . "\n";
}

echo "\n=== STEP 3: Run SEO URL indexer ===\n";
$indexResult = apiRequest('POST', '/_action/index', ['entity' => ['seo_url']], $token, $config);
echo "Indexer: " . ($indexResult['code'] < 300 ? "OK" : "FAIL") . "\n";

echo "\n=== STEP 4: Clear cache ===\n";
$cacheResult = apiRequest('DELETE', '/_action/cache', null, $token, $config);
echo "Cache cleared: " . ($cacheResult['code'] < 300 ? "OK" : "FAIL") . "\n";

echo "\n=== DONE ===\n";
echo "SEO URLs updated. Test: https://ravenweapon.ch/Ausruestung/\n";
