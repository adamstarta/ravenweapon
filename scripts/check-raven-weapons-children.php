<?php
/**
 * Check children of Raven Weapons category
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

function apiPost($config, $token, $endpoint, $data) {
    $ch = curl_init($config['shopware_url'] . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$token = getAccessToken($config);

echo "=== Checking Raven Weapons Children ===\n\n";

// Raven Weapons ID
$ravenWeaponsId = 'a61f19c9cb4b11f0b4074aca3d279c31';

// Get direct children of Raven Weapons
$result = apiPost($config, $token, 'search/category', [
    'filter' => [
        ['type' => 'equals', 'field' => 'parentId', 'value' => $ravenWeaponsId]
    ],
    'limit' => 20
]);

echo "Direct children of Raven Weapons:\n";
if (!empty($result['data'])) {
    foreach ($result['data'] as $cat) {
        $name = $cat['attributes']['name'] ?? 'Unknown';
        $id = $cat['id'];
        $active = $cat['attributes']['active'] ? 'Yes' : 'No';
        $visible = $cat['attributes']['visible'] ? 'Yes' : 'No';
        $childCount = $cat['attributes']['childCount'] ?? 0;
        echo "- $name\n";
        echo "  ID: $id\n";
        echo "  Active: $active, Visible: $visible\n";
        echo "  Children: $childCount\n\n";
    }
} else {
    echo "No children found\n";
}

// Also search for "Sturmgewehre"
echo "\n=== Searching for Sturmgewehre ===\n";
$result = apiPost($config, $token, 'search/category', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'Sturmgewehr']
    ],
    'limit' => 10
]);

if (!empty($result['data'])) {
    foreach ($result['data'] as $cat) {
        $name = $cat['attributes']['name'] ?? 'Unknown';
        $id = $cat['id'];
        $parentId = $cat['attributes']['parentId'] ?? 'none';
        $path = $cat['attributes']['path'] ?? '';
        echo "- $name\n";
        echo "  ID: $id\n";
        echo "  Parent ID: $parentId\n";
        echo "  Path: $path\n\n";
    }
} else {
    echo "No Sturmgewehre category found\n";
}

echo "\n=== Done ===\n";
