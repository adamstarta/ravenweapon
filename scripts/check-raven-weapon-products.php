<?php
/**
 * Check for products with "Raven Weapon" in name or as manufacturer
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

echo "\n======================================================================\n";
echo "     CHECK RAVEN WEAPON PRODUCTS\n";
echo "======================================================================\n\n";

$GLOBALS['token_data'] = ['token' => null, 'expires_at' => 0];

function getAccessToken($config, $forceRefresh = false) {
    if (!$forceRefresh && $GLOBALS['token_data']['token'] && $GLOBALS['token_data']['expires_at'] > time() + 60) {
        return $GLOBALS['token_data']['token'];
    }
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) return null;
    $data = json_decode($response, true);
    $GLOBALS['token_data']['token'] = $data['access_token'] ?? null;
    $GLOBALS['token_data']['expires_at'] = time() + ($data['expires_in'] ?? 600);
    return $GLOBALS['token_data']['token'];
}

function apiRequest($method, $endpoint, $data, $config, $retry = true) {
    $token = getAccessToken($config);
    if (!$token) return ['code' => 0, 'body' => null];
    $ch = curl_init();
    $url = $config['shopware_url'] . '/api/' . ltrim($endpoint, '/');
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    if ($data && in_array($method, ['POST', 'PATCH', 'PUT'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 401 && $retry) {
        $GLOBALS['token_data']['token'] = null;
        return apiRequest($method, $endpoint, $data, $config, false);
    }
    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

// Authenticate
$token = getAccessToken($config);
if (!$token) die("ERROR: Failed to authenticate!\n");
echo "Authenticated OK\n\n";

// Check for "Raven Weapon" manufacturer
echo "Checking for 'Raven Weapon' manufacturer...\n";
$result = apiRequest('POST', '/search/product-manufacturer', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'Raven']
    ]
], $config);

$manufacturers = $result['body']['data'] ?? [];
if (count($manufacturers) > 0) {
    echo "Found Raven Weapon manufacturer:\n";
    foreach ($manufacturers as $m) {
        echo "  - {$m['name']} (ID: {$m['id']})\n";
    }

    // Find products with this manufacturer
    foreach ($manufacturers as $m) {
        echo "\nProducts with manufacturer '{$m['name']}':\n";
        $result = apiRequest('POST', '/search/product', [
            'filter' => [
                ['type' => 'equals', 'field' => 'manufacturerId', 'value' => $m['id']]
            ]
        ], $config);

        $products = $result['body']['data'] ?? [];
        foreach ($products as $p) {
            echo "  - {$p['name']} (ID: {$p['id']})\n";
        }
        if (empty($products)) {
            echo "  No products found\n";
        }
    }
} else {
    echo "No 'Raven Weapon' manufacturer found\n";
}

echo "\n";

// Check for products with "Raven Weapon" in name
echo "Checking for products with 'Raven Weapon' in name...\n";
$result = apiRequest('POST', '/search/product', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'Raven Weapon']
    ],
    'associations' => [
        'manufacturer' => []
    ]
], $config);

$products = $result['body']['data'] ?? [];
if (count($products) > 0) {
    echo "Found " . count($products) . " products with 'Raven Weapon' in name:\n";
    foreach ($products as $p) {
        echo "  - {$p['name']}\n";
        echo "    ID: {$p['id']}\n";
        echo "    Manufacturer: " . ($p['manufacturer']['name'] ?? 'None') . "\n";
    }
} else {
    echo "No products with 'Raven Weapon' in name found\n";
}

echo "\n======================================================================\n";
