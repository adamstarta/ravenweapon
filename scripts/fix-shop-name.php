<?php
/**
 * Fix shop name from "Demostore" to "Raven Weapon AG"
 */

$API_URL = 'https://ortak.ch';

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => 'admin',
            'password' => 'shopware'
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true)['access_token'] ?? null;
}

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$token = getToken($API_URL);
if (!$token) {
    die("Failed to get token\n");
}

echo "Token obtained\n\n";

// First find the config ID for shopName
$result = apiRequest($API_URL, $token, 'POST', 'search/system-config', [
    'filter' => [
        ['type' => 'equals', 'field' => 'configurationKey', 'value' => 'core.basicInformation.shopName']
    ]
]);

if (!empty($result['data'])) {
    $configId = $result['data'][0]['id'];
    echo "Found shopName config ID: $configId\n";
    echo "Current value: " . ($result['data'][0]['configurationValue'] ?? 'unknown') . "\n\n";

    // Update to "Raven Weapon AG"
    $updateResult = apiRequest($API_URL, $token, 'PATCH', 'system-config/' . $configId, [
        'configurationValue' => 'Raven Weapon AG'
    ]);

    if (isset($updateResult['errors'])) {
        echo "Error updating:\n";
        print_r($updateResult['errors']);
    } else {
        echo "SUCCESS: Shop name updated to 'Raven Weapon AG'\n";
    }
} else {
    echo "shopName config not found, creating new entry...\n";

    // Create new config entry
    $createResult = apiRequest($API_URL, $token, 'POST', 'system-config', [
        'configurationKey' => 'core.basicInformation.shopName',
        'configurationValue' => 'Raven Weapon AG'
    ]);

    if (isset($createResult['errors'])) {
        echo "Error creating:\n";
        print_r($createResult['errors']);
    } else {
        echo "SUCCESS: Shop name created as 'Raven Weapon AG'\n";
    }
}

// Verify the change
echo "\n=== VERIFICATION ===\n";
$verify = apiRequest($API_URL, $token, 'POST', 'search/system-config', [
    'filter' => [
        ['type' => 'equals', 'field' => 'configurationKey', 'value' => 'core.basicInformation.shopName']
    ]
]);

if (!empty($verify['data'])) {
    echo "Shop name is now: " . ($verify['data'][0]['configurationValue'] ?? 'unknown') . "\n";
}

echo "\nDone!\n";
