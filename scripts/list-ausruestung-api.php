<?php
/**
 * List Ausrüstung subcategories via Shopware Admin API
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
    curl_close($ch);
    return json_decode($response, true);
}

$token = getAccessToken($config);
if (!$token) {
    die("Failed to get token\n");
}
echo "Got token\n\n";

// Find Ausrüstung category
$result = apiRequest('POST', '/search/category', [
    'filter' => [
        ['type' => 'multi', 'operator' => 'OR', 'queries' => [
            ['type' => 'equals', 'field' => 'name', 'value' => 'Ausrüstung'],
            ['type' => 'equals', 'field' => 'name', 'value' => 'Snigel']
        ]]
    ],
    'includes' => ['category' => ['id', 'name', 'level']]
], $token, $config);

if (isset($result['data'][0])) {
    $ausruestung = $result['data'][0];
    echo "Found: " . $ausruestung['name'] . " (Level: " . $ausruestung['level'] . ")\n";
    echo "ID: " . $ausruestung['id'] . "\n\n";

    // Get subcategories
    $subResult = apiRequest('POST', '/search/category', [
        'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $ausruestung['id']]],
        'includes' => ['category' => ['id', 'name']],
        'limit' => 50
    ], $token, $config);

    if (isset($subResult['data'])) {
        echo "Found " . count($subResult['data']) . " subcategories:\n\n";
        foreach ($subResult['data'] as $i => $sub) {
            echo ($i + 1) . ". " . $sub['name'] . "\n";
        }
    }
} else {
    echo "Ausrüstung not found\n";
    // List all level 2 categories
    $result = apiRequest('POST', '/search/category', [
        'filter' => [['type' => 'equals', 'field' => 'level', 'value' => 2]],
        'includes' => ['category' => ['id', 'name']],
        'limit' => 50
    ], $token, $config);
    echo "\nLevel 2 categories:\n";
    foreach ($result['data'] ?? [] as $cat) {
        echo "- " . $cat['name'] . "\n";
    }
}
