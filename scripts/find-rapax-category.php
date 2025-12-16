<?php
/**
 * Find the correct RAPAX category structure
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

echo "=== Finding Category Structure ===\n\n";

// Find "Raven Weapons" category
echo "1. Looking for 'Raven Weapons' category...\n";
$result = apiRequest($config, $token, 'POST', 'search/category', [
    'filter' => [
        ['type' => 'equals', 'field' => 'name', 'value' => 'Raven Weapons']
    ],
    'associations' => [
        'children' => []
    ]
]);

if (!empty($result['data'])) {
    foreach ($result['data'] as $cat) {
        echo "\n  Raven Weapons: {$cat['id']}\n";
        if (!empty($cat['children'])) {
            echo "  Children:\n";
            foreach ($cat['children'] as $child) {
                echo "    - {$child['name']}: {$child['id']}\n";
            }
        }
    }
}

// Find all RAPAX categories
echo "\n\n2. All categories containing 'RAPAX'...\n";
$result = apiRequest($config, $token, 'POST', 'search/category', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'RAPAX']
    ],
    'associations' => [
        'parent' => [],
        'children' => []
    ]
]);

if (!empty($result['data'])) {
    foreach ($result['data'] as $cat) {
        $parentName = $cat['parent']['name'] ?? 'ROOT';
        $parentId = $cat['parentId'] ?? 'none';
        echo "\n  {$cat['name']}\n";
        echo "    ID: {$cat['id']}\n";
        echo "    Parent: $parentName ($parentId)\n";
        if (!empty($cat['children'])) {
            echo "    Children:\n";
            foreach ($cat['children'] as $child) {
                echo "      - {$child['name']}: {$child['id']}\n";
            }
        }
    }
}

// Find "Weapons" category (the wrong one)
echo "\n\n3. Looking for 'Weapons' category (wrong one to delete)...\n";
$result = apiRequest($config, $token, 'POST', 'search/category', [
    'filter' => [
        ['type' => 'equals', 'field' => 'name', 'value' => 'Weapons']
    ],
    'associations' => [
        'children' => [
            'associations' => [
                'children' => []
            ]
        ]
    ]
]);

if (!empty($result['data'])) {
    foreach ($result['data'] as $cat) {
        echo "\n  Weapons: {$cat['id']}\n";
        if (!empty($cat['children'])) {
            echo "  Children:\n";
            foreach ($cat['children'] as $child) {
                echo "    - {$child['name']}: {$child['id']}\n";
                if (!empty($child['children'])) {
                    foreach ($child['children'] as $grandchild) {
                        echo "      - {$grandchild['name']}: {$grandchild['id']}\n";
                    }
                }
            }
        }
    }
}
