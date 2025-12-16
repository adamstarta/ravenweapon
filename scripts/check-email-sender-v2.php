<?php
/**
 * Check email sender configuration in Shopware - v2 with better response handling
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

// Debug: Get raw system config dump
echo "=== ALL SYSTEM CONFIG (first 50) ===\n\n";

$result = apiRequest($API_URL, $token, 'POST', 'search/system-config', [
    'limit' => 50
]);

echo "Response structure:\n";
print_r(array_keys($result ?? []));
echo "\n";

if (!empty($result['data'])) {
    foreach ($result['data'] as $item) {
        // Handle both flat and nested attribute structures
        $key = $item['configurationKey'] ?? $item['attributes']['configurationKey'] ?? 'unknown';
        $value = $item['configurationValue'] ?? $item['attributes']['configurationValue'] ?? null;

        // Filter for email-related settings
        if (stripos($key, 'email') !== false || stripos($key, 'sender') !== false || stripos($key, 'mailer') !== false || stripos($key, 'basicInformation') !== false) {
            echo "Key: $key\n";
            echo "Value: " . json_encode($value) . "\n";
            echo "---\n";
        }
    }
}

echo "\n=== SEARCHING FOR EMAIL SETTINGS ===\n\n";

// Get all config for email
$emailConfigs = apiRequest($API_URL, $token, 'POST', 'search/system-config', [
    'limit' => 500
]);

foreach ($emailConfigs['data'] ?? [] as $item) {
    $key = $item['configurationKey'] ?? $item['attributes']['configurationKey'] ?? '';
    $value = $item['configurationValue'] ?? $item['attributes']['configurationValue'] ?? null;

    // Show all config keys that contain certain words
    if (stripos($key, 'sender') !== false ||
        stripos($key, 'email') !== false ||
        stripos($key, 'mail') !== false ||
        stripos($key, 'basic') !== false) {
        echo "$key = " . json_encode($value) . "\n";
    }
}

echo "\n=== ALL CONFIG KEYS (for reference) ===\n\n";

// List all keys
$allKeys = [];
foreach ($emailConfigs['data'] ?? [] as $item) {
    $key = $item['configurationKey'] ?? $item['attributes']['configurationKey'] ?? '';
    if ($key) $allKeys[] = $key;
}
sort($allKeys);
foreach ($allKeys as $key) {
    echo "$key\n";
}

echo "\nDone!\n";
