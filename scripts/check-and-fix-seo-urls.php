<?php
/**
 * Check current SEO URLs for Ausrüstung and regenerate if needed
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

$token = getAccessToken($config);
if (!$token) {
    die("Failed to get token\n");
}
echo "Got token\n\n";

// Find Ausrüstung category
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Ausrüstung']],
    'includes' => ['category' => ['id', 'name']]
], $token, $config);

if (!isset($result['body']['data'][0])) {
    die("Ausrüstung not found\n");
}

$ausruestung = $result['body']['data'][0];
echo "Found Ausrüstung: " . $ausruestung['id'] . "\n\n";

// Get subcategories
$subResult = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $ausruestung['id']]],
    'includes' => ['category' => ['id', 'name']],
    'limit' => 50
], $token, $config);

$categories = array_merge([$ausruestung], $subResult['body']['data'] ?? []);

echo "=== CURRENT SEO URLs ===\n\n";

$allHaveUrls = true;
foreach ($categories as $cat) {
    $seoResult = apiRequest('POST', '/search/seo-url', [
        'filter' => [
            ['type' => 'equals', 'field' => 'foreignKey', 'value' => $cat['id']],
            ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
        ],
        'includes' => ['seo_url' => ['id', 'seoPathInfo', 'isCanonical']],
        'limit' => 10
    ], $token, $config);

    echo $cat['name'] . ":\n";
    if (empty($seoResult['body']['data'])) {
        echo "  NO SEO URL!\n";
        $allHaveUrls = false;
    } else {
        foreach ($seoResult['body']['data'] as $seo) {
            $canonical = $seo['isCanonical'] ? ' (CANONICAL)' : '';
            echo "  /" . $seo['seoPathInfo'] . $canonical . "\n";
        }
    }
}

if (!$allHaveUrls) {
    echo "\n=== RUNNING SEO URL INDEXER ===\n";
    $indexResult = apiRequest('POST', '/_action/indexing/seo_url.indexer', [], $token, $config);
    echo "Indexer result: " . ($indexResult['code'] < 300 ? "OK" : "Code " . $indexResult['code']) . "\n";

    echo "\nClearing cache...\n";
    $cacheResult = apiRequest('DELETE', '/_action/cache', null, $token, $config);
    echo "Cache: " . ($cacheResult['code'] < 300 ? "OK" : "FAIL") . "\n";
}

echo "\n=== DONE ===\n";
