<?php
/**
 * Raw category check
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

// Get first 5 categories raw
$ch = curl_init($config['shopware_url'] . '/api/category?limit=5');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
]);
$response = curl_exec($ch);
curl_close($ch);

echo "=== Raw Category Response (first 5) ===\n\n";
$result = json_decode($response, true);
print_r($result);

// Also get the known Raven Weapons ID directly
echo "\n\n=== Raven Weapons Category (known ID) ===\n\n";
$ravenId = 'a61f19c9cb4b11f0b4074aca3d279c31';

$ch = curl_init($config['shopware_url'] . "/api/category/$ravenId");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
print_r($result);
