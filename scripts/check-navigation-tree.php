<?php
/**
 * Check navigation tree structure
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

echo "=== Checking Navigation Tree ===\n\n";

// RAPAX main category ID
$rapaxMainId = '1f36ebeb19da4fc6bc9cb3c3acfadafd';

// Get RAPAX and all its children
$result = apiPost($config, $token, 'search/category', [
    'filter' => [
        [
            'type' => 'multi',
            'operator' => 'OR',
            'queries' => [
                ['type' => 'equals', 'field' => 'id', 'value' => $rapaxMainId],
                ['type' => 'equals', 'field' => 'parentId', 'value' => $rapaxMainId],
            ]
        ]
    ],
    'includes' => [
        'category' => ['id', 'name', 'parentId', 'level', 'active', 'visible', 'childCount']
    ],
    'limit' => 50
]);

echo "Categories under RAPAX:\n\n";

if (!empty($result['data'])) {
    foreach ($result['data'] as $cat) {
        $name = $cat['name'] ?? $cat['translated']['name'] ?? '';
        $id = $cat['id'];
        $parentId = $cat['parentId'] ?? 'none';
        $level = $cat['level'] ?? '?';
        $active = $cat['active'] ? 'Yes' : 'No';
        $visible = $cat['visible'] ? 'Yes' : 'No';
        $childCount = $cat['childCount'] ?? 0;

        echo "Name: $name\n";
        echo "  ID: $id\n";
        echo "  Parent: $parentId\n";
        echo "  Level: $level\n";
        echo "  Active: $active\n";
        echo "  Visible: $visible\n";
        echo "  Children: $childCount\n\n";
    }
}

// Now check children of RAPAX sub-category
$rapaxSubId = '95a7cf1575ddc0219d8f11484ab0cbeb';
$caracalLynxId = '2b3fdb3f3dcc00eacf9c9683d5d22c6a';

echo "\n=== Children of RAPAX Subcategory ===\n";

$result = apiPost($config, $token, 'search/category', [
    'filter' => [
        ['type' => 'equals', 'field' => 'parentId', 'value' => $rapaxSubId]
    ],
    'includes' => [
        'category' => ['id', 'name', 'parentId', 'level', 'active', 'visible']
    ],
    'limit' => 20
]);

if (!empty($result['data'])) {
    foreach ($result['data'] as $cat) {
        $name = $cat['name'] ?? '';
        $active = $cat['active'] ? 'Yes' : 'No';
        $visible = $cat['visible'] ? 'Yes' : 'No';
        echo "- $name (Active: $active, Visible: $visible)\n";
    }
}

echo "\n=== Children of Caracal Lynx ===\n";

$result = apiPost($config, $token, 'search/category', [
    'filter' => [
        ['type' => 'equals', 'field' => 'parentId', 'value' => $caracalLynxId]
    ],
    'includes' => [
        'category' => ['id', 'name', 'parentId', 'level', 'active', 'visible']
    ],
    'limit' => 20
]);

if (!empty($result['data'])) {
    foreach ($result['data'] as $cat) {
        $name = $cat['name'] ?? '';
        $active = $cat['active'] ? 'Yes' : 'No';
        $visible = $cat['visible'] ? 'Yes' : 'No';
        echo "- $name (Active: $active, Visible: $visible)\n";
    }
}

echo "\n=== Done ===\n";
