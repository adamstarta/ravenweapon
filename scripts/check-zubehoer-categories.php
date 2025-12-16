<?php
/**
 * Check Zubehör category structure
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
    curl_close($ch);
    return json_decode($response, true);
}

$token = getAccessToken($config);

echo "=== Checking Category Structure ===\n\n";

// Get all top-level categories
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'level', 'value' => '2']],
    'includes' => ['category' => ['id', 'name', 'parentId']],
    'limit' => 50
], $token, $config);

echo "TOP-LEVEL CATEGORIES:\n";
foreach ($result['data'] ?? [] as $cat) {
    echo "  - {$cat['name']} ({$cat['id']})\n";
}

// Find Zubehör
$zubehoerResult = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Zubehör']],
    'includes' => ['category' => ['id', 'name']]
], $token, $config);

$zubehoer = $zubehoerResult['data'][0] ?? null;

if ($zubehoer) {
    echo "\n\nZUBEHÖR SUBCATEGORIES:\n";

    $subResult = apiRequest('POST', '/search/category', [
        'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $zubehoer['id']]],
        'includes' => ['category' => ['id', 'name']],
        'limit' => 50
    ], $token, $config);

    foreach ($subResult['data'] ?? [] as $cat) {
        echo "  - {$cat['name']} ({$cat['id']})\n";

        // Check for sub-subcategories
        $subSubResult = apiRequest('POST', '/search/category', [
            'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $cat['id']]],
            'includes' => ['category' => ['id', 'name']],
            'limit' => 50
        ], $token, $config);

        foreach ($subSubResult['data'] ?? [] as $subCat) {
            echo "      - {$subCat['name']} ({$subCat['id']})\n";
        }
    }
}

// Also check "Zielhilfen, Optik & Zubehör"
$waffenResult = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'contains', 'field' => 'name', 'value' => 'Zielhilfen']],
    'includes' => ['category' => ['id', 'name']]
], $token, $config);

$waffenzubehoer = $waffenResult['data'][0] ?? null;

if ($waffenzubehoer) {
    echo "\n\nZIELHILFEN, OPTIK & ZUBEHÖR SUBCATEGORIES:\n";

    $subResult = apiRequest('POST', '/search/category', [
        'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $waffenzubehoer['id']]],
        'includes' => ['category' => ['id', 'name']],
        'limit' => 50
    ], $token, $config);

    foreach ($subResult['data'] ?? [] as $cat) {
        echo "  - {$cat['name']} ({$cat['id']})\n";
    }
}

echo "\n=== Done ===\n";
