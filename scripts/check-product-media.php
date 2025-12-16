<?php
/**
 * Check product media for 30L Mission backpack
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

// Find the 30L Mission backpack product
$result = apiRequest($API_URL, $token, 'POST', 'search/product', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => '30L Mission backpack']
    ],
    'associations' => [
        'media' => [
            'associations' => ['media' => []]
        ],
        'cover' => [
            'associations' => ['media' => []]
        ],
        'customFields' => []
    ]
]);

echo "=== 30L MISSION BACKPACK MEDIA CHECK ===\n\n";

foreach ($result['data'] ?? [] as $p) {
    $name = $p['name'] ?? $p['attributes']['name'] ?? '';
    $sku = $p['productNumber'] ?? $p['attributes']['productNumber'] ?? '';
    $customFields = $p['customFields'] ?? $p['attributes']['customFields'] ?? [];

    echo "Product: $name\n";
    echo "SKU: $sku\n";
    echo "Custom Fields: " . json_encode($customFields, JSON_PRETTY_PRINT) . "\n\n";

    $media = $p['media'] ?? $p['attributes']['media'] ?? [];
    echo "Media (" . count($media) . " images):\n";

    foreach ($media as $i => $m) {
        $mediaItem = $m['media'] ?? $m['attributes']['media'] ?? [];
        $fileName = $mediaItem['fileName'] ?? $mediaItem['attributes']['fileName'] ?? 'unknown';
        $alt = $mediaItem['alt'] ?? $mediaItem['attributes']['alt'] ?? '';
        $title = $mediaItem['title'] ?? $mediaItem['attributes']['title'] ?? '';
        echo "  [$i] $fileName\n";
        if ($alt) echo "      Alt: $alt\n";
        if ($title) echo "      Title: $title\n";
    }
}
