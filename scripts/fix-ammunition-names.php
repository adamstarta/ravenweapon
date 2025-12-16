<?php
/**
 * Fix Ammunition Product Names and Manufacturer
 * 1. Remove "Raven Weapon" prefix from product names
 * 2. Remove manufacturer (set to null)
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

echo "\n======================================================================\n";
echo "     FIX AMMUNITION PRODUCT NAMES & MANUFACTURER\n";
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

// Get ammunition products
echo "Finding ammunition products...\n";
$result = apiRequest('POST', '/search/product', [
    'filter' => [
        ['type' => 'contains', 'field' => 'productNumber', 'value' => 'AMMO-']
    ],
    'associations' => [
        'manufacturer' => []
    ]
], $config);

$products = $result['body']['data'] ?? [];
echo "Found " . count($products) . " ammunition products\n\n";

foreach ($products as $product) {
    $productId = $product['id'];
    $currentName = $product['name'];
    $manufacturerId = $product['manufacturerId'] ?? null;
    $manufacturerName = $product['manufacturer']['name'] ?? 'None';

    echo "Product: $currentName\n";
    echo "  ID: $productId\n";
    echo "  Manufacturer: $manufacturerName\n";

    // Remove "Raven Weapon " prefix from name if present
    $newName = $currentName;
    if (strpos($currentName, 'Raven Weapon ') === 0) {
        $newName = substr($currentName, strlen('Raven Weapon '));
    }

    $updateData = [];

    // Update name if changed
    if ($newName !== $currentName) {
        $updateData['name'] = $newName;
        echo "  New name: $newName\n";
    }

    // Remove manufacturer
    if ($manufacturerId) {
        $updateData['manufacturerId'] = null;
        echo "  Removing manufacturer\n";
    }

    if (!empty($updateData)) {
        $result = apiRequest('PATCH', "/product/$productId", $updateData, $config);

        if ($result['code'] === 204 || $result['code'] === 200) {
            echo "  SUCCESS: Updated\n";
        } else {
            echo "  ERROR: " . json_encode($result['body']) . "\n";
        }
    } else {
        echo "  No changes needed\n";
    }

    echo "\n";
}

// Clear cache
echo "Clearing cache...\n";
apiRequest('DELETE', '/_action/cache', null, $config);
echo "Cache cleared\n";

echo "\n======================================================================\n";
echo "     DONE!\n";
echo "======================================================================\n";
