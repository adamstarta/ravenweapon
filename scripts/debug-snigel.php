<?php
/**
 * Debug missing Snigel image
 */

$OLD_URL = 'https://ortak.ch';

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
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

$token = getToken($OLD_URL);
$sku = 'SN-belt-closure-pack-5-11';

// Get full product with all associations
$ch = curl_init($OLD_URL . '/api/search/product');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'filter' => [
            ['type' => 'equals', 'field' => 'productNumber', 'value' => $sku]
        ],
        'associations' => [
            'cover' => ['associations' => ['media' => []]],
            'media' => []
        ]
    ])
]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

echo "=== Included data ===\n";
if (!empty($data['included'])) {
    foreach ($data['included'] as $item) {
        echo "Type: " . $item['type'] . " | ID: " . $item['id'] . "\n";
        if ($item['type'] === 'media') {
            echo "  URL: " . ($item['attributes']['url'] ?? 'N/A') . "\n";
        }
        if ($item['type'] === 'product_media') {
            echo "  MediaId: " . ($item['attributes']['mediaId'] ?? 'N/A') . "\n";
        }
    }
}

echo "\n=== Cover data ===\n";
if (!empty($data['data'][0]['attributes']['coverId'])) {
    echo "CoverId: " . $data['data'][0]['attributes']['coverId'] . "\n";
}

// Check relationships
if (!empty($data['data'][0]['relationships'])) {
    echo "\n=== Relationships ===\n";
    print_r($data['data'][0]['relationships']);
}
