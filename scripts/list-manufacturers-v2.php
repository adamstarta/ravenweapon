<?php
/**
 * List all manufacturers with translations
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

// Get OAuth token
$ch = curl_init($config['shopware_url'] . '/api/oauth/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'grant_type' => 'password',
        'client_id' => 'administration',
        'username' => $config['api_user'],
        'password' => $config['api_password'],
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
]);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);
$token = $data['access_token'];

// Get all manufacturers with translations
$ch = curl_init($config['shopware_url'] . '/api/search/product-manufacturer');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'includes' => [
            'product_manufacturer' => ['id', 'name', 'translated']
        ],
        'limit' => 100
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

echo "=== All Manufacturers ===\n\n";

if (!empty($result['data'])) {
    foreach ($result['data'] as $mfr) {
        $name = $mfr['translated']['name'] ?? $mfr['name'] ?? 'No name';
        echo "Name: $name\n";
        echo "  ID: {$mfr['id']}\n\n";
    }
    echo "Total: " . count($result['data']) . " manufacturers\n";
} else {
    echo "No manufacturers found\n";
    print_r($result);
}
