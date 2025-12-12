<?php
/**
 * List categories with their media status
 */

$config = [
    'shopware_url' => 'http://localhost',
    'api_user' => 'admin',
    'api_password' => 'shopware',
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
curl_close($ch);
$data = json_decode($response, true);
$token = $data['access_token'] ?? null;

// Get categories
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/search/category',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'limit' => 200,
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);
$categories = $result['data'] ?? [];

echo "Categories with mediaId:\n";
echo str_repeat("=", 60) . "\n";

foreach ($categories as $cat) {
    $name = $cat['translated']['name'] ?? $cat['name'] ?? 'UNNAMED';
    $mediaId = $cat['mediaId'] ?? null;

    if ($mediaId) {
        echo "[$name] -> mediaId: $mediaId\n";
    }
}

echo "\n\nAll main navigation categories:\n";
echo str_repeat("=", 60) . "\n";

// Find main nav categories (those that are active and level 2)
foreach ($categories as $cat) {
    $name = $cat['translated']['name'] ?? $cat['name'] ?? 'UNNAMED';
    $active = $cat['active'] ?? false;
    $level = $cat['level'] ?? 0;
    $mediaId = $cat['mediaId'] ?? 'NONE';

    if ($active && $level == 2) {
        echo "[$name] level=$level mediaId=$mediaId\n";
    }
}
