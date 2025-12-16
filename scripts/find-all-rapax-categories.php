<?php
/**
 * Find all RAPAX categories and their details
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

$ravenWeaponsId = 'a61f19c9cb4b11f0b4074aca3d279c31';

echo "=== All categories under Raven Weapons ===\n\n";

$result = apiRequest($config, $token, 'POST', 'search/category', [
    'filter' => [
        ['type' => 'equals', 'field' => 'parentId', 'value' => $ravenWeaponsId]
    ],
    'includes' => [
        'category' => ['id', 'name', 'active', 'parentId', 'path', 'productAssignmentType']
    ]
]);

if (!empty($result['data'])) {
    foreach ($result['data'] as $cat) {
        echo "Category: " . ($cat['name'] ?? 'UNNAMED') . "\n";
        echo "  ID: {$cat['id']}\n";
        echo "  Active: " . ($cat['active'] ? 'Yes' : 'No') . "\n";
        echo "  Path: " . ($cat['path'] ?? 'none') . "\n";
        echo "\n";
    }
}

echo "\n=== Looking for SEO URLs for RAPAX ===\n";

$result = apiRequest($config, $token, 'POST', 'search/seo-url', [
    'filter' => [
        ['type' => 'contains', 'field' => 'seoPathInfo', 'value' => 'RAPAX']
    ],
    'limit' => 20
]);

if (!empty($result['data'])) {
    foreach ($result['data'] as $seo) {
        echo "SEO Path: {$seo['seoPathInfo']}\n";
        echo "  Foreign Key: {$seo['foreignKey']}\n";
        echo "  Route: {$seo['routeName']}\n";
        echo "  Is Canonical: " . ($seo['isCanonical'] ? 'Yes' : 'No') . "\n";
        echo "\n";
    }
}

// Check products assigned to the new category
echo "\n=== Products in new RAPAX category (019b24ef3599727ab066b6c3be6efdae) ===\n";

$result = apiRequest($config, $token, 'POST', 'search/product', [
    'filter' => [
        ['type' => 'equals', 'field' => 'categories.id', 'value' => '019b24ef3599727ab066b6c3be6efdae']
    ],
    'limit' => 30,
    'includes' => [
        'product' => ['id', 'name']
    ]
]);

if (!empty($result['data'])) {
    echo "Found " . count($result['data']) . " products:\n";
    foreach ($result['data'] as $p) {
        echo "  - " . ($p['name'] ?? 'UNNAMED') . "\n";
    }
} else {
    echo "No products found in this category\n";
}
