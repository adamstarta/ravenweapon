<?php
/**
 * Rename Ausrüstung subcategories from English to German
 */

$config = [
    'base_url' => 'https://ortak.ch',
    'client_id' => 'SWIARAVEN03399CEA2C931269',
    'client_secret' => 'RavenNavbarUpdate2025!'
];

// English to German translations
$translations = [
    'Admin Gear' => 'Verwaltungsausrüstung',
    'Bags & Backpacks' => 'Taschen & Rucksäcke',
    'Ballistic Protection' => 'Ballistischer Schutz',
    'Belts' => 'Gürtel',
    'Covert Gear' => 'Verdeckte Ausrüstung',
    'Duty Gear' => 'Dienstausrüstung',
    'HighVis' => 'Warnschutz',
    'Holders & Pouches' => 'Halter & Taschen',
    'K9 Gear' => 'K9 Ausrüstung',
    'Leg Panels' => 'Beinpaneele',
    'Medical Gear' => 'Medizinische Ausrüstung',
    'Miscellaneous' => 'Verschiedenes',
    'Multicam' => 'Multicam',  // Brand name - keep as is
    'Patches' => 'Patches',    // Commonly used in German
    'Police Gear' => 'Polizeiausrüstung',
    'Slings & Holsters' => 'Tragegurte & Holster',
    'Sniper Gear' => 'Scharfschützen-Ausrüstung',
    'Source Hydration' => 'Source Hydration',  // Brand name
    'Tactical Clothing' => 'Taktische Bekleidung',
    'Tactical Gear' => 'Taktische Ausrüstung',
    'Vests & Chest Rigs' => 'Westen & Chest Rigs'
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

echo "=== Renaming Ausrüstung Subcategories to German ===\n\n";

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

$ausruestung = $result['body']['data'][0] ?? null;
if (!$ausruestung) {
    die("Ausrüstung not found\n");
}

echo "Found Ausrüstung: " . $ausruestung['id'] . "\n\n";

// Get all subcategories
$subResult = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $ausruestung['id']]],
    'includes' => ['category' => ['id', 'name']],
    'limit' => 50
], $token, $config);

$renamed = 0;
$skipped = 0;

foreach ($subResult['body']['data'] ?? [] as $cat) {
    $currentName = $cat['name'];
    $catId = $cat['id'];

    if (isset($translations[$currentName])) {
        $newName = $translations[$currentName];

        if ($currentName === $newName) {
            echo "SKIP: $currentName (already correct)\n";
            $skipped++;
            continue;
        }

        echo "Renaming: $currentName -> $newName... ";

        $updateResult = apiRequest('PATCH', '/category/' . $catId, [
            'name' => $newName
        ], $token, $config);

        if ($updateResult['code'] < 300) {
            echo "OK\n";
            $renamed++;
        } else {
            echo "FAIL (" . $updateResult['code'] . ")\n";
            if (isset($updateResult['body']['errors'])) {
                echo "  Error: " . json_encode($updateResult['body']['errors'][0]['detail'] ?? 'Unknown') . "\n";
            }
        }
    } else {
        echo "NO TRANSLATION: $currentName\n";
        $skipped++;
    }
}

echo "\n=== Summary ===\n";
echo "Renamed: $renamed\n";
echo "Skipped: $skipped\n";

// Clear cache
echo "\nClearing cache... ";
$cacheResult = apiRequest('DELETE', '/_action/cache', null, $token, $config);
echo ($cacheResult['code'] < 300 ? "OK" : "FAIL") . "\n";

echo "\n=== DONE ===\n";
