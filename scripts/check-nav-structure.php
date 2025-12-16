<?php
/**
 * Check current navigation structure
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

echo "=== Current Navigation Structure ===\n\n";

$catalogueRootId = '0191c12ccf00712e8c0cf733425fe315';

// Get top-level categories (direct children of Catalogue Root)
echo "TOP-LEVEL Categories (same level as Raven Weapons):\n";
$result = apiPost($config, $token, 'search/category', [
    'filter' => [
        ['type' => 'equals', 'field' => 'parentId', 'value' => $catalogueRootId]
    ],
    'limit' => 20
]);

$topLevelIds = [];
foreach ($result['data'] ?? [] as $cat) {
    $name = $cat['attributes']['name'] ?? 'Unknown';
    $id = $cat['id'];
    $topLevelIds[$id] = $name;
    echo "- $name (ID: $id)\n";
}

// Now check children of each top-level category
echo "\n\n=== Children of each top-level category ===\n";
foreach ($topLevelIds as $parentId => $parentName) {
    $result = apiPost($config, $token, 'search/category', [
        'filter' => [
            ['type' => 'equals', 'field' => 'parentId', 'value' => $parentId]
        ],
        'limit' => 20
    ]);

    echo "\n$parentName:\n";
    if (empty($result['data'])) {
        echo "  (no children)\n";
    } else {
        foreach ($result['data'] as $child) {
            $childName = $child['attributes']['name'] ?? 'Unknown';
            $childId = $child['id'];
            echo "  ↳ $childName (ID: $childId)\n";

            // Check grandchildren
            $grandResult = apiPost($config, $token, 'search/category', [
                'filter' => [
                    ['type' => 'equals', 'field' => 'parentId', 'value' => $childId]
                ],
                'limit' => 20
            ]);

            foreach ($grandResult['data'] ?? [] as $grandChild) {
                $grandChildName = $grandChild['attributes']['name'] ?? 'Unknown';
                echo "      • $grandChildName\n";
            }
        }
    }
}
