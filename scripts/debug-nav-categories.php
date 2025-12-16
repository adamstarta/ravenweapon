<?php
/**
 * Debug navigation categories - get full structure
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

echo "=== Full Category Structure Debug ===\n\n";

// First, get the main "Raven Weapons" category
echo "1. Finding Raven Weapons main category...\n";
$result = apiPost($config, $token, 'search/category', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'Raven']
    ],
    'limit' => 10
]);

if (!empty($result['data'])) {
    foreach ($result['data'] as $cat) {
        $name = $cat['attributes']['name'] ?? $cat['translated']['name'] ?? 'Unknown';
        $id = $cat['id'];
        $parentId = $cat['attributes']['parentId'] ?? 'root';
        $active = isset($cat['attributes']['active']) ? ($cat['attributes']['active'] ? 'Yes' : 'No') : '?';
        $visible = isset($cat['attributes']['visible']) ? ($cat['attributes']['visible'] ? 'Yes' : 'No') : '?';
        echo "   - $name (ID: $id, Parent: $parentId, Active: $active, Visible: $visible)\n";
    }
}

// Get RAPAX main category with its full tree
echo "\n2. Getting RAPAX category tree...\n";
$result = apiPost($config, $token, 'search/category', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'RAPAX']
    ],
    'limit' => 20
]);

echo "\nRaw RAPAX results:\n";
if (!empty($result['data'])) {
    foreach ($result['data'] as $cat) {
        echo json_encode($cat, JSON_PRETTY_PRINT) . "\n\n";
    }
} else {
    echo "No RAPAX categories found\n";
    echo "Full response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
}

// Get ALL categories to understand structure
echo "\n3. Getting ALL categories...\n";
$result = apiPost($config, $token, 'search/category', [
    'sort' => [['field' => 'level', 'order' => 'ASC']],
    'limit' => 100
]);

echo "\nCategory Tree:\n";
$categories = [];
if (!empty($result['data'])) {
    foreach ($result['data'] as $cat) {
        $id = $cat['id'];
        $name = $cat['attributes']['name'] ?? $cat['translated']['name'] ?? 'Unknown';
        $parentId = $cat['attributes']['parentId'] ?? null;
        $level = $cat['attributes']['level'] ?? 0;
        $active = $cat['attributes']['active'] ?? false;
        $visible = $cat['attributes']['visible'] ?? false;

        $categories[$id] = [
            'name' => $name,
            'parentId' => $parentId,
            'level' => $level,
            'active' => $active,
            'visible' => $visible
        ];
    }

    // Print tree structure
    foreach ($categories as $id => $cat) {
        $indent = str_repeat('  ', $cat['level']);
        $activeStr = $cat['active'] ? '✓' : '✗';
        $visibleStr = $cat['visible'] ? '👁' : '⊘';
        echo "$indent$activeStr$visibleStr {$cat['name']} (L{$cat['level']})\n";
    }
}

echo "\n=== Done ===\n";
