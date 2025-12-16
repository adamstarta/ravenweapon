<?php
/**
 * Check which Snigel products have variants in Shopware
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

// Get all Snigel parent products with variant info
$result = apiRequest($API_URL, $token, 'POST', 'search/product', [
    'limit' => 500,
    'filter' => [
        ['type' => 'equals', 'field' => 'manufacturerId', 'value' => $snigelManufacturerId],
        ['type' => 'equals', 'field' => 'parentId', 'value' => null]
    ],
    'associations' => [
        'configuratorSettings' => [],
        'children' => []
    ]
]);

echo "=== SNIGEL PRODUCTS WITH VARIANTS IN SHOPWARE ===\n\n";

$count = 0;
foreach ($result['data'] ?? [] as $p) {
    $configuratorSettings = $p['configuratorSettings'] ?? $p['attributes']['configuratorSettings'] ?? [];
    $children = $p['children'] ?? $p['attributes']['children'] ?? [];

    if (!empty($configuratorSettings) || !empty($children)) {
        $count++;
        $name = $p['name'] ?? $p['attributes']['name'] ?? '';
        $sku = $p['productNumber'] ?? $p['attributes']['productNumber'] ?? '';
        echo "$sku - " . mb_substr($name, 0, 45) . " (variants: " . count($children) . ")\n";
    }
}

echo "\nTotal products with variants: $count\n";
