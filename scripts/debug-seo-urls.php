<?php
$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

// Get token
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/oauth/token',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'grant_type' => 'password',
        'client_id' => 'administration',
        'username' => $config['api_user'],
        'password' => $config['api_password'],
    ]),
]);
$response = curl_exec($ch);
$data = json_decode($response, true);
$token = $data['access_token'] ?? null;
curl_close($ch);

if (!$token) {
    die("Failed to authenticate\n");
}

// Search for SEO URLs with Raven-Weapons
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/search/seo-url',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'filter' => [
            ['type' => 'contains', 'field' => 'seoPathInfo', 'value' => 'Raven-Weapons']
        ],
        'limit' => 5
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);

echo "Raw API Response:\n";
echo $response . "\n\n";

$data = json_decode($response, true);
echo "Parsed data:\n";
print_r($data);
