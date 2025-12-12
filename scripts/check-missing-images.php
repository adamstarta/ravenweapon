<?php
/**
 * Check if missing products have images in OLD site
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

function apiPost($baseUrl, $token, $endpoint, $data) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$token = getToken($OLD_URL);
if (!$token) {
    die("❌ Failed to get token\n");
}

$missingSKUs = ['ZRT-ZF-VGC-000-XX-00105', 'SN-belt-closure-pack-5-11'];

echo "Checking OLD site for missing product images:\n\n";

foreach ($missingSKUs as $sku) {
    $response = apiPost($OLD_URL, $token, 'search/product', [
        'filter' => [
            ['type' => 'equals', 'field' => 'productNumber', 'value' => $sku]
        ],
        'associations' => [
            'cover' => [
                'associations' => ['media' => []]
            ]
        ]
    ]);

    if (!empty($response['data'])) {
        $product = $response['data'][0];
        $name = $product['name'] ?? $product['attributes']['name'] ?? 'Unknown';
        $hasCover = !empty($product['coverId']) || !empty($product['attributes']['coverId']);
        echo "SKU: {$sku}\n";
        echo "Name: {$name}\n";
        echo "Has cover in OLD: " . ($hasCover ? "YES" : "NO") . "\n";
        if (isset($product['attributes']['coverId'])) {
            echo "CoverId: " . $product['attributes']['coverId'] . "\n";
        }
        echo "\n";
    } else {
        echo "SKU: {$sku} - NOT FOUND in OLD site\n\n";
    }
}
