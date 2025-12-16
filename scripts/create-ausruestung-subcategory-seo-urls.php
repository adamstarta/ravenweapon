<?php
/**
 * Create SEO URLs for all Ausrüstung subcategories
 * Uses ENGLISH language ID (sales channel default)
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

echo "=== Creating SEO URLs for Ausrüstung Subcategories ===\n\n";

$token = getAccessToken($config);
if (!$token) {
    die("Failed to get token\n");
}
echo "Got token\n\n";

// Sales channel and language IDs (English!)
$salesChannelId = '0191c12dd4b970949e9aeec40433be3e';
$languageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b'; // ENGLISH - the default!

// Find Ausrüstung category
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Ausrüstung']],
    'includes' => ['category' => ['id', 'name']]
], $token, $config);

$ausruestung = $result['body']['data'][0] ?? null;
if (!$ausruestung) {
    die("Ausrüstung not found\n");
}

echo "Found Ausrüstung: " . $ausruestung['id'] . "\n\n";

// Get all subcategories
$subResult = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $ausruestung['id']]],
    'includes' => ['category' => ['id', 'name']],
    'sort' => [['field' => 'name', 'order' => 'ASC']],
    'limit' => 50
], $token, $config);

$created = 0;
$failed = 0;

foreach ($subResult['body']['data'] ?? [] as $cat) {
    $name = $cat['name'];
    $catId = $cat['id'];
    $urlName = makeUrlFriendly($name);
    $seoPath = "Ausruestung/$urlName/";

    echo "Creating SEO URL for $name... ";

    // First check if URL already exists
    $existingResult = apiRequest('POST', '/search/seo-url', [
        'filter' => [
            ['type' => 'equals', 'field' => 'seoPathInfo', 'value' => $seoPath],
            ['type' => 'equals', 'field' => 'salesChannelId', 'value' => $salesChannelId],
            ['type' => 'equals', 'field' => 'languageId', 'value' => $languageId]
        ],
        'includes' => ['seo_url' => ['id']]
    ], $token, $config);

    if (!empty($existingResult['body']['data'])) {
        echo "EXISTS\n";
        continue;
    }

    // Create new SEO URL
    $seoData = [
        'id' => generateUuid(),
        'salesChannelId' => $salesChannelId,
        'languageId' => $languageId,
        'foreignKey' => $catId,
        'routeName' => 'frontend.navigation.page',
        'pathInfo' => '/navigation/' . $catId,
        'seoPathInfo' => $seoPath,
        'isCanonical' => true,
        'isModified' => true
    ];

    $createResult = apiRequest('POST', '/seo-url', $seoData, $token, $config);

    if ($createResult['code'] < 300) {
        echo "OK -> /$seoPath\n";
        $created++;
    } else {
        echo "FAIL (" . $createResult['code'] . ")\n";
        if (isset($createResult['body']['errors'])) {
            echo "  Error: " . json_encode($createResult['body']['errors'][0]['detail'] ?? 'Unknown') . "\n";
        }
        $failed++;
    }
}

echo "\n=== Summary ===\n";
echo "Created: $created\n";
echo "Failed: $failed\n";

// Clear cache
echo "\nClearing cache... ";
$cacheResult = apiRequest('DELETE', '/_action/cache', null, $token, $config);
echo ($cacheResult['code'] < 300 ? "OK" : "FAIL") . "\n";

echo "\n=== DONE ===\n";
