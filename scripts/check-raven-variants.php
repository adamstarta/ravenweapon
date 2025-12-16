<?php
/**
 * Check if RAVEN weapons have configurator settings
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

// Get all products with their configurator settings
$result = apiRequest($API_URL, $token, 'POST', 'search/product', [
    'limit' => 500,
    'filter' => [
        ['type' => 'equals', 'field' => 'parentId', 'value' => null]
    ],
    'associations' => [
        'configuratorSettings' => [],
        'manufacturer' => [],
        'media' => []
    ]
]);

echo "=== ALL PRODUCTS WITH CONFIGURATOR SETTINGS ===\n\n";

$withConfig = 0;
foreach ($result['data'] ?? [] as $p) {
    $cs = $p['configuratorSettings'] ?? [];
    $media = $p['media'] ?? [];
    $manufacturer = $p['manufacturer']['name'] ?? 'Unknown';
    $sku = $p['productNumber'] ?? '';
    $name = $p['name'] ?? '';

    if (count($cs) > 0) {
        $withConfig++;
        echo "SKU: $sku\n";
        echo "Name: $name\n";
        echo "Manufacturer: $manufacturer\n";
        echo "Configurator Settings: " . count($cs) . "\n";
        echo "Media: " . count($media) . "\n\n";
    }
}

echo "Total products with configurator settings: $withConfig\n\n";

echo "=== PRODUCTS WITH MULTIPLE MEDIA (no configurator) ===\n\n";

foreach ($result['data'] ?? [] as $p) {
    $cs = $p['configuratorSettings'] ?? [];
    $media = $p['media'] ?? [];
    $manufacturer = $p['manufacturer']['name'] ?? 'Unknown';
    $sku = $p['productNumber'] ?? '';
    $name = $p['name'] ?? '';

    if (count($cs) == 0 && count($media) > 1 && strpos($manufacturer, 'Snigel') === false) {
        echo "SKU: $sku - $name ($manufacturer) - " . count($media) . " media\n";
    }
}
