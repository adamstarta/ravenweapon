<?php
/**
 * Fix RAPAX subcategories navigation visibility
 * Make all subcategories visible in navigation menu
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

$token = getAccessToken($config);

echo "=== Fixing RAPAX Navigation Visibility ===\n\n";

// Category IDs
$categories = [
    'RAPAX Sub' => '95a7cf1575ddc0219d8f11484ab0cbeb',
    'RX Sport' => '34c00eca0b38ba3aa4ae483722859b4e',
    'RX Tactical' => 'fa470225519fd7d666f28d89caf25c8d',
    'RX Compact' => 'ea2e04075bc0d5c50cfb0a4b52930401',
    'Caracal Lynx' => '2b3fdb3f3dcc00eacf9c9683d5d22c6a',
    'LYNX SPORT' => '66ed5338a8574c803e01da3cb9e1f2d4',
    'LYNX OPEN' => '7048c95bf71dd4802adb7846617b4503',
    'LYNX COMPACT' => 'da98c38ad3e48c6965ff0e93769115d4',
];

// Update all categories to be visible in navigation
$payload = [];
foreach ($categories as $name => $id) {
    $payload[] = [
        'id' => $id,
        'visible' => true,
        'active' => true,
    ];
    echo "Adding $name to navigation...\n";
}

echo "\nUpdating categories...\n";

$result = apiPost($config, $token, '_action/sync', [
    [
        'action' => 'upsert',
        'entity' => 'category',
        'payload' => $payload
    ]
]);

echo "Sync API: HTTP {$result['code']}\n";

if ($result['code'] == 200) {
    echo "Success!\n";
} else {
    echo "Error: " . json_encode($result['data']) . "\n";
}

// Clear cache
echo "\nClearing cache...\n";
$ch = curl_init($config['shopware_url'] . '/api/_action/cache');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
]);
curl_exec($ch);
curl_close($ch);
echo "Done\n";

echo "\n=== Complete! ===\n";
echo "Note: Navigation depth may also need to be configured in theme settings.\n";
