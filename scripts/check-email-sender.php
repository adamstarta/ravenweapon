<?php
/**
 * Check email sender configuration in Shopware
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

echo "=== SALES CHANNEL EMAIL CONFIGURATION ===\n\n";

// Get sales channels with their mail header/footer
$result = apiRequest($API_URL, $token, 'POST', 'search/sales-channel', [
    'limit' => 10,
    'associations' => [
        'mailHeaderFooter' => []
    ]
]);

foreach ($result['data'] ?? [] as $channel) {
    echo "Sales Channel: {$channel['name']}\n";
    echo "  ID: {$channel['id']}\n";
    echo "  Type ID: {$channel['typeId']}\n";
    echo "  Mail Header/Footer ID: " . ($channel['mailHeaderFooterId'] ?? 'none') . "\n";

    if (!empty($channel['mailHeaderFooter'])) {
        echo "  Header Name: " . ($channel['mailHeaderFooter']['name'] ?? 'none') . "\n";
    }
    echo "\n";
}

echo "=== SYSTEM CONFIG - EMAIL SETTINGS ===\n\n";

// Check system_config for email settings
$configResult = apiRequest($API_URL, $token, 'POST', 'search/system-config', [
    'limit' => 100,
    'filter' => [
        [
            'type' => 'contains',
            'field' => 'configurationKey',
            'value' => 'email'
        ]
    ]
]);

foreach ($configResult['data'] ?? [] as $config) {
    echo "Key: {$config['configurationKey']}\n";
    echo "Value: " . json_encode($config['configurationValue']) . "\n";
    echo "---\n";
}

// Also check for sender settings
echo "\n=== SENDER CONFIGURATION ===\n\n";

$senderConfig = apiRequest($API_URL, $token, 'POST', 'search/system-config', [
    'limit' => 100,
    'filter' => [
        [
            'type' => 'multi',
            'operator' => 'or',
            'queries' => [
                ['type' => 'contains', 'field' => 'configurationKey', 'value' => 'senderAddress'],
                ['type' => 'contains', 'field' => 'configurationKey', 'value' => 'senderName'],
                ['type' => 'contains', 'field' => 'configurationKey', 'value' => 'Sender'],
                ['type' => 'contains', 'field' => 'configurationKey', 'value' => 'mailer']
            ]
        ]
    ]
]);

foreach ($senderConfig['data'] ?? [] as $config) {
    echo "Key: {$config['configurationKey']}\n";
    echo "Value: " . json_encode($config['configurationValue']) . "\n";
    echo "---\n";
}

// Get all core.basicInformation settings
echo "\n=== BASIC INFORMATION SETTINGS ===\n\n";

$basicInfo = apiRequest($API_URL, $token, 'POST', 'search/system-config', [
    'limit' => 100,
    'filter' => [
        ['type' => 'contains', 'field' => 'configurationKey', 'value' => 'core.basicInformation']
    ]
]);

foreach ($basicInfo['data'] ?? [] as $config) {
    echo "Key: {$config['configurationKey']}\n";
    echo "Value: " . json_encode($config['configurationValue']) . "\n";
    echo "---\n";
}

// Get mailer settings
echo "\n=== MAILER SETTINGS ===\n\n";

$mailerConfig = apiRequest($API_URL, $token, 'POST', 'search/system-config', [
    'limit' => 100,
    'filter' => [
        ['type' => 'contains', 'field' => 'configurationKey', 'value' => 'core.mailer']
    ]
]);

foreach ($mailerConfig['data'] ?? [] as $config) {
    echo "Key: {$config['configurationKey']}\n";
    echo "Value: " . json_encode($config['configurationValue']) . "\n";
    echo "---\n";
}

echo "\nDone!\n";
