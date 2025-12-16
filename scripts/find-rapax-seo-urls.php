<?php
/**
 * Find all SEO URLs containing RAPAX
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

function getAccessToken($config) {
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
    return $data['access_token'];
}

function apiRequest($config, $token, $method, $endpoint, $data = null) {
    $ch = curl_init($config['shopware_url'] . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_CUSTOMREQUEST => $method,
    ]);
    if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$token = getAccessToken($config);

echo "=== All SEO URLs containing 'RAPAX' ===\n\n";

$result = apiRequest($config, $token, 'POST', 'search/seo-url', [
    'filter' => [
        ['type' => 'contains', 'field' => 'seoPathInfo', 'value' => 'RAPAX']
    ],
    'limit' => 50
]);

if (!empty($result['data'])) {
    foreach ($result['data'] as $seo) {
        echo "Path: " . ($seo['seoPathInfo'] ?? 'N/A') . "\n";
        echo "  Foreign Key: " . ($seo['foreignKey'] ?? 'N/A') . "\n";
        echo "  Route: " . ($seo['routeName'] ?? 'N/A') . "\n";
        echo "  Is Canonical: " . (($seo['isCanonical'] ?? false) ? 'Yes' : 'No') . "\n";
        echo "  Is Deleted: " . (($seo['isDeleted'] ?? false) ? 'Yes' : 'No') . "\n";
        echo "\n";
    }
} else {
    echo "No SEO URLs found\n";
    print_r($result);
}

echo "\n=== All SEO URLs containing 'Raven-Weapons' ===\n\n";

$result = apiRequest($config, $token, 'POST', 'search/seo-url', [
    'filter' => [
        ['type' => 'contains', 'field' => 'seoPathInfo', 'value' => 'Raven-Weapons']
    ],
    'limit' => 50
]);

if (!empty($result['data'])) {
    foreach ($result['data'] as $seo) {
        echo "Path: " . ($seo['seoPathInfo'] ?? 'N/A') . "\n";
        echo "  Foreign Key: " . ($seo['foreignKey'] ?? 'N/A') . "\n";
        echo "  Route: " . ($seo['routeName'] ?? 'N/A') . "\n";
        echo "\n";
    }
}
