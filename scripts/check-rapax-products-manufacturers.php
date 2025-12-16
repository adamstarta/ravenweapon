<?php
/**
 * Check RAPAX/Caracal products for manufacturer assignments
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

// Search for RAPAX and CARACAL products
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
                'type' => 'multi',
                'operator' => 'OR',
                'queries' => [
                    ['type' => 'contains', 'field' => 'name', 'value' => 'RAPAX'],
                    ['type' => 'contains', 'field' => 'name', 'value' => 'CARACAL'],
                ]
            ]
        ],
        'associations' => [
            'manufacturer' => []
        ],
        'limit' => 50
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

echo "=== RAPAX/CARACAL Products with Manufacturer Info ===\n\n";

if (!empty($result['data'])) {
    $withMfr = 0;
    $withoutMfr = 0;

    foreach ($result['data'] as $product) {
        $name = $product['translated']['name'] ?? $product['name'] ?? 'Unknown';
        $mfrId = $product['manufacturerId'] ?? null;
        $mfrName = $product['manufacturer']['translated']['name'] ?? $product['manufacturer']['name'] ?? null;

        if ($mfrId) {
            $withMfr++;
            echo "Product: $name\n";
            echo "  Manufacturer ID: $mfrId\n";
            echo "  Manufacturer Name: " . ($mfrName ?? 'No name') . "\n\n";
        } else {
            $withoutMfr++;
        }
    }

    echo "---\n";
    echo "Products with manufacturer: $withMfr\n";
    echo "Products without manufacturer: $withoutMfr\n";
    echo "Total: " . count($result['data']) . " products\n";
} else {
    echo "No products found\n";
}
