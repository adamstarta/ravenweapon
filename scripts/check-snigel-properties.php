<?php
/**
 * Check Snigel products with Farbe property
 */

$API_URL = 'http://localhost';

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => 'admin',
            'password' => 'shopware'
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true)['access_token'] ?? null;
}

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$token = getToken($API_URL);

// Get Snigel manufacturer
$result = apiRequest($API_URL, $token, 'POST', 'search/product-manufacturer', [
    'filter' => [['type' => 'contains', 'field' => 'name', 'value' => 'Snigel']]
]);
$snigelManufacturerId = $result['data'][0]['id'] ?? null;

// Get all Snigel products with properties
$result = apiRequest($API_URL, $token, 'POST', 'search/product', [
    'limit' => 500,
    'filter' => [
        ['type' => 'equals', 'field' => 'manufacturerId', 'value' => $snigelManufacturerId]
    ],
    'associations' => [
        'properties' => [
            'associations' => ['group' => []]
        ],
        'media' => []
    ]
]);

echo "=== SNIGEL PRODUCTS WITH FARBE PROPERTY ===\n\n";

$count = 0;
$withMedia = 0;
foreach ($result['data'] ?? [] as $p) {
    $properties = $p['properties'] ?? $p['attributes']['properties'] ?? [];
    $media = $p['media'] ?? $p['attributes']['media'] ?? [];
    $name = $p['name'] ?? $p['attributes']['name'] ?? '';
    $sku = $p['productNumber'] ?? $p['attributes']['productNumber'] ?? '';

    $farbeProps = [];
    foreach ($properties as $prop) {
        $groupName = $prop['group']['name'] ?? $prop['attributes']['group']['name'] ?? '';
        if ($groupName === 'Farbe') {
            $propName = $prop['name'] ?? $prop['attributes']['name'] ?? '';
            $farbeProps[] = $propName;
        }
    }

    if (!empty($farbeProps)) {
        $count++;
        echo "$sku - " . mb_substr($name, 0, 40) . "\n";
        echo "  Farbe: " . implode(', ', $farbeProps) . "\n";
        echo "  Media: " . count($media) . " images\n\n";

        if (count($media) > 1) $withMedia++;
    }
}

echo "Total with Farbe property: $count\n";
echo "With multiple media: $withMedia\n";
