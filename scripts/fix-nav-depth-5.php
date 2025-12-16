<?php
/**
 * Fix navigation depth to 5 and ensure all RAPAX categories are visible
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

function apiPatch($config, $token, $endpoint, $data) {
    $ch = curl_init($config['shopware_url'] . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

$token = getAccessToken($config);

echo "=== Fixing Navigation Settings ===\n\n";

// Sales channel ID
$salesChannelId = '0191c12dd4b970949e9aeec40433be3e';

// 1. Update navigation depth to 5
echo "1. Updating navigation depth to 5...\n";
$result = apiPatch($config, $token, "sales-channel/$salesChannelId", [
    'navigationCategoryDepth' => 5
]);
echo "   HTTP: {$result['code']}\n";

// 2. Update ALL RAPAX-related categories to be visible and active
echo "\n2. Making all RAPAX categories visible...\n";

$categories = [
    // Main RAPAX under Raven Weapons (L3)
    '1f36ebeb19da4fc6bc9cb3c3acfadafd' => 'RAPAX (main)',
    // RAPAX sub (L4)
    '95a7cf1575ddc0219d8f11484ab0cbeb' => 'RAPAX (sub)',
    // Caracal Lynx (L4)
    '2b3fdb3f3dcc00eacf9c9683d5d22c6a' => 'Caracal Lynx',
    // RX Sport (L5)
    '34c00eca0b38ba3aa4ae483722859b4e' => 'RX Sport',
    // RX Tactical (L5)
    'fa470225519fd7d666f28d89caf25c8d' => 'RX Tactical',
    // RX Compact (L5)
    'ea2e04075bc0d5c50cfb0a4b52930401' => 'RX Compact',
    // LYNX SPORT (L5)
    '66ed5338a8574c803e01da3cb9e1f2d4' => 'LYNX SPORT',
    // LYNX OPEN (L5)
    '7048c95bf71dd4802adb7846617b4503' => 'LYNX OPEN',
    // LYNX COMPACT (L5)
    'da98c38ad3e48c6965ff0e93769115d4' => 'LYNX COMPACT',
];

$payload = [];
foreach ($categories as $id => $name) {
    $payload[] = [
        'id' => $id,
        'active' => true,
        'visible' => true,
    ];
    echo "   - $name\n";
}

$result = apiPost($config, $token, '_action/sync', [
    [
        'action' => 'upsert',
        'entity' => 'category',
        'payload' => $payload
    ]
]);

echo "\n   Sync result: HTTP {$result['code']}\n";

// 3. Clear cache
echo "\n3. Clearing cache...\n";
$ch = curl_init($config['shopware_url'] . '/api/_action/cache');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
]);
curl_exec($ch);
curl_close($ch);
echo "   Done\n";

// 4. Index categories
echo "\n4. Re-indexing categories...\n";
$ch = curl_init($config['shopware_url'] . '/api/_action/index');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode(['indexer' => ['category.indexer']]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "   HTTP: $httpCode\n";

echo "\n=== Complete! ===\n";
echo "Navigation depth set to 5, all categories marked visible.\n";
echo "Check: https://ortak.ch and hover over Raven Weapons\n";
