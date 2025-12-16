<?php
/**
 * Check product visibility in sales channel
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

function apiRequest($endpoint, $data, $token, $config) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['base_url'] . '/api' . $endpoint,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$token = getAccessToken($config);

// Check Magazine category details
$magazineCategoryId = '00a19869155b4c0d9508dfcfeeaf93d7';

echo "=== Magazine Category Details ===\n\n";

$catResult = apiRequest('/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'id', 'value' => $magazineCategoryId]],
    'associations' => [
        'products' => ['limit' => 5]
    ]
], $token, $config);

$category = $catResult['data'][0] ?? null;
if ($category) {
    echo "Name: {$category['name']}\n";
    echo "Active: " . ($category['active'] ? 'Yes' : 'No') . "\n";
    echo "Visible: " . ($category['visible'] ?? 'N/A') . "\n";
    echo "Products count in response: " . count($category['products'] ?? []) . "\n";
}

// Check if products are properly associated using a direct store-api call
echo "\n\n=== Testing Store API ===\n";

// Get sales channel ID
$scResult = apiRequest('/search/sales-channel', [
    'limit' => 1
], $token, $config);

$salesChannelId = $scResult['data'][0]['id'] ?? null;
echo "Sales Channel ID: $salesChannelId\n";

// Check one PMAG product
echo "\n=== PMAG Product Full Details ===\n";
$pmag = apiRequest('/search/product', [
    'filter' => [['type' => 'contains', 'field' => 'name', 'value' => 'PMAG® 40']],
    'associations' => [
        'categories' => [],
        'visibilities' => []
    ]
], $token, $config);

$product = $pmag['data'][0] ?? null;
if ($product) {
    echo "Name: {$product['name']}\n";
    echo "Active: " . ($product['active'] ? 'Yes' : 'No') . "\n";
    echo "Categories:\n";
    foreach ($product['categories'] ?? [] as $cat) {
        echo "  - {$cat['name']} ({$cat['id']})\n";
    }
    echo "Visibilities:\n";
    foreach ($product['visibilities'] ?? [] as $vis) {
        echo "  - SC: {$vis['salesChannelId']} | Visibility: {$vis['visibility']}\n";
    }
}
