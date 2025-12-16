<?php
/**
 * Find where "Kontoinhaber" / "Nikola Mitrovic" is stored
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

echo "Searching for 'Kontoinhaber' or 'Nikola Mitrovic'...\n\n";

// Check payment methods
echo "=== Payment Methods ===\n";
$payments = apiRequest($API_URL, $token, 'POST', 'search/payment-method', ['limit' => 50]);
foreach ($payments['data'] ?? [] as $pm) {
    $name = $pm['name'] ?? $pm['translated']['name'] ?? 'unknown';
    echo "Payment: $name\n";
    $jsonData = json_encode($pm);
    if (stripos($jsonData, 'nikola') !== false || stripos($jsonData, 'mitrovic') !== false) {
        echo "  FOUND! Contains Nikola/Mitrovic\n";
        echo "  Data: " . substr($jsonData, 0, 500) . "...\n";
    }
}

// Check sales channel settings
echo "\n=== Sales Channels ===\n";
$channels = apiRequest($API_URL, $token, 'POST', 'search/sales-channel', ['limit' => 10]);
foreach ($channels['data'] ?? [] as $ch) {
    $name = $ch['name'] ?? $ch['translated']['name'] ?? 'unknown';
    echo "Channel: $name\n";
    $jsonData = json_encode($ch);
    if (stripos($jsonData, 'nikola') !== false || stripos($jsonData, 'mitrovic') !== false) {
        echo "  FOUND! Contains Nikola/Mitrovic\n";
    }
}

// Check document base config in detail
echo "\n=== Document Base Config (Full) ===\n";
$docs = apiRequest($API_URL, $token, 'POST', 'search/document-base-config', ['limit' => 10]);
foreach ($docs['data'] ?? [] as $doc) {
    $name = $doc['name'] ?? 'unknown';
    $config = $doc['config'] ?? [];
    echo "Doc: $name\n";
    echo "  Config: " . json_encode($config) . "\n";
    $jsonData = json_encode($doc);
    if (stripos($jsonData, 'nikola') !== false || stripos($jsonData, 'mitrovic') !== false) {
        echo "  FOUND! Contains Nikola/Mitrovic\n";
    }
}

// Check basic settings
echo "\n=== Basic Settings (Shop Owner) ===\n";
$settings = apiRequest($API_URL, $token, 'POST', 'search/system-config', [
    'filter' => [
        ['type' => 'contains', 'field' => 'configurationKey', 'value' => 'shopName']
    ],
    'limit' => 50
]);
foreach ($settings['data'] ?? [] as $setting) {
    echo $setting['configurationKey'] . " = " . json_encode($setting['configurationValue']) . "\n";
}

// Check for any field with bank or account
echo "\n=== System Config (Bank-related) ===\n";
$allSettings = apiRequest($API_URL, $token, 'POST', 'search/system-config', ['limit' => 1000]);
foreach ($allSettings['data'] ?? [] as $setting) {
    $key = $setting['configurationKey'] ?? '';
    $value = json_encode($setting['configurationValue'] ?? '');

    if (stripos($key, 'bank') !== false ||
        stripos($key, 'iban') !== false ||
        stripos($key, 'bic') !== false ||
        stripos($key, 'holder') !== false ||
        stripos($key, 'inhaber') !== false ||
        stripos($value, 'nikola') !== false ||
        stripos($value, 'mitrovic') !== false) {
        echo "$key = $value\n";
    }
}

echo "\nDone!\n";
