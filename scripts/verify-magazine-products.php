<?php
/**
 * Verify Magazine category products
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

$magazineCategoryId = '00a19869155b4c0d9508dfcfeeaf93d7';

// Get products in Magazine category
$result = apiRequest('POST', '/search/product', [
    'filter' => [
        ['type' => 'equals', 'field' => 'categories.id', 'value' => $magazineCategoryId]
    ],
    'includes' => ['product' => ['id', 'name', 'active', 'categories']],
    'limit' => 50
], $token, $config);

echo "=== Products in Magazine Category ===\n\n";
echo "Found: " . ($result['total'] ?? 0) . " products\n\n";

foreach ($result['data'] ?? [] as $product) {
    $active = $product['active'] ? 'ACTIVE' : 'INACTIVE';
    echo "  - {$product['name']} [{$active}]\n";
    echo "    Categories: ";
    foreach ($product['categories'] ?? [] as $cat) {
        echo $cat['id'] . " ";
    }
    echo "\n";
}

// Also check a specific PMAG product
echo "\n\n=== Checking PMAG 40 Product ===\n";
$pmag = apiRequest('POST', '/search/product', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'PMAG® 40']
    ],
    'includes' => ['product' => ['id', 'name', 'active', 'categories']],
    'associations' => ['categories' => []]
], $token, $config);

foreach ($pmag['data'] ?? [] as $product) {
    echo "Product: {$product['name']}\n";
    echo "Active: " . ($product['active'] ? 'Yes' : 'No') . "\n";
    echo "Categories:\n";
    foreach ($product['categories'] ?? [] as $cat) {
        echo "  - {$cat['name']} ({$cat['id']})\n";
    }
}
