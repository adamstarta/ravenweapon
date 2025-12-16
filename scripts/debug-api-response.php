<?php
$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

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

// Search for RAVEN products
$ch = curl_init($config['shopware_url'] . '/api/search/product');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'filter' => [
            [
                'type' => 'prefix',
                'field' => 'productNumber',
                'value' => 'RAVEN-'
            ]
        ],
        'limit' => 10
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);

echo "Total products: " . ($result['meta']['total'] ?? 'unknown') . "\n\n";

echo "First product raw structure:\n";
if (!empty($result['data'][0])) {
    echo "Keys at root: " . implode(', ', array_keys($result['data'][0])) . "\n";
    $first = $result['data'][0];
    echo "ID field: " . ($first['id'] ?? 'NOT FOUND') . "\n";
    echo "Name: " . ($first['attributes']['name'] ?? 'NOT FOUND') . "\n";
    echo "SKU: " . ($first['attributes']['productNumber'] ?? 'NOT FOUND') . "\n";

    // Show all RAVEN products
    echo "\nAll RAVEN products:\n";
    foreach ($result['data'] as $index => $product) {
        echo "$index: " . ($product['attributes']['name'] ?? 'NO NAME') . "\n";
        echo "   ID: " . ($product['id'] ?? 'NO ID') . "\n";
    }
}
