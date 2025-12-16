<?php
/**
 * Create German SEO URLs for Ausrüstung subcategories
 */

$config = [
    'base_url' => 'https://ortak.ch',
    'client_id' => 'SWIARAVEN03399CEA2C931269',
    'client_secret' => 'RavenNavbarUpdate2025!'
];

// German names to URL-friendly paths
$urlMappings = [
    'Verwaltungsausrüstung' => 'Verwaltungsausruestung',
    'Taschen & Rucksäcke' => 'Taschen-Rucksaecke',
    'Ballistischer Schutz' => 'Ballistischer-Schutz',
    'Gürtel' => 'Guertel',
    'Verdeckte Ausrüstung' => 'Verdeckte-Ausruestung',
    'Dienstausrüstung' => 'Dienstausruestung',
    'Warnschutz' => 'Warnschutz',
    'Halter & Taschen' => 'Halter-Taschen',
    'K9 Ausrüstung' => 'K9-Ausruestung',
    'Beinpaneele' => 'Beinpaneele',
    'Medizinische Ausrüstung' => 'Medizinische-Ausruestung',
    'Verschiedenes' => 'Verschiedenes',
    'Multicam' => 'Multicam',
    'Patches' => 'Patches',
    'Polizeiausrüstung' => 'Polizeiausruestung',
    'Tragegurte & Holster' => 'Tragegurte-Holster',
    'Scharfschützen-Ausrüstung' => 'Scharfschuetzen-Ausruestung',
    'Source Hydration' => 'Source-Hydration',
    'Taktische Bekleidung' => 'Taktische-Bekleidung',
    'Taktische Ausrüstung' => 'Taktische-Ausruestung',
    'Westen & Chest Rigs' => 'Westen-Chest-Rigs'
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

echo "=== Creating German SEO URLs ===\n\n";

$token = getAccessToken($config);
if (!$token) {
    die("Failed to get token\n");
}

// Sales channel and language IDs (English - the default!)
$salesChannelId = '0191c12dd4b970949e9aeec40433be3e';
$languageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';

// Find Ausrüstung category
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Ausrüstung']],
    'includes' => ['category' => ['id', 'name']]
], $token, $config);

$ausruestung = $result['body']['data'][0] ?? null;
if (!$ausruestung) {
    die("Ausrüstung not found\n");
}

// Get all subcategories (now with German names)
$subResult = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $ausruestung['id']]],
    'includes' => ['category' => ['id', 'name']],
    'limit' => 50
], $token, $config);

$created = 0;
$updated = 0;

foreach ($subResult['body']['data'] ?? [] as $cat) {
    $name = $cat['name'];
    $catId = $cat['id'];

    if (!isset($urlMappings[$name])) {
        echo "NO MAPPING for: $name\n";
        continue;
    }

    $urlPath = $urlMappings[$name];
    $seoPath = "Ausruestung/$urlPath/";

    echo "Processing $name -> /$seoPath ... ";

    // Check for existing SEO URL with this path
    $existingResult = apiRequest('POST', '/search/seo-url', [
        'filter' => [
            ['type' => 'equals', 'field' => 'foreignKey', 'value' => $catId],
            ['type' => 'equals', 'field' => 'salesChannelId', 'value' => $salesChannelId],
            ['type' => 'equals', 'field' => 'languageId', 'value' => $languageId],
            ['type' => 'equals', 'field' => 'isCanonical', 'value' => true]
        ],
        'includes' => ['seo_url' => ['id', 'seoPathInfo']]
    ], $token, $config);

    $existingUrl = $existingResult['body']['data'][0] ?? null;

    if ($existingUrl) {
        // Update existing URL
        $updateResult = apiRequest('PATCH', '/seo-url/' . $existingUrl['id'], [
            'seoPathInfo' => $seoPath,
            'isModified' => true
        ], $token, $config);

        if ($updateResult['code'] < 300) {
            echo "UPDATED\n";
            $updated++;
        } else {
            echo "UPDATE FAIL\n";
        }
    } else {
        // Create new URL
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
            echo "CREATED\n";
            $created++;
        } else {
            echo "CREATE FAIL (" . $createResult['code'] . ")\n";
        }
    }
}

echo "\n=== Summary ===\n";
echo "Created: $created\n";
echo "Updated: $updated\n";

// Clear cache
echo "\nClearing cache... ";
$cacheResult = apiRequest('DELETE', '/_action/cache', null, $token, $config);
echo ($cacheResult['code'] < 300 ? "OK" : "FAIL") . "\n";

echo "\n=== DONE ===\n";
