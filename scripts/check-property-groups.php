<?php
/**
 * Check property groups in Shopware
 */

$API_URL = 'http://localhost';

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

// Get all property groups
$result = apiRequest($API_URL, $token, 'POST', 'search/property-group', [
    'limit' => 100,
    'associations' => ['options' => []]
]);

echo "=== ALL PROPERTY GROUPS ===\n\n";

foreach ($result['data'] ?? [] as $group) {
    $name = $group['name'] ?? $group['attributes']['name'] ?? '';
    $id = $group['id'];
    $options = $group['options'] ?? $group['attributes']['options'] ?? [];
    echo "[$name] ID: $id\n";
    echo "  Options: " . count($options) . "\n";
    foreach (array_slice($options, 0, 5) as $opt) {
        $optName = $opt['name'] ?? $opt['attributes']['name'] ?? '';
        echo "    - $optName\n";
    }
    if (count($options) > 5) {
        echo "    ... and " . (count($options) - 5) . " more\n";
    }
    echo "\n";
}
