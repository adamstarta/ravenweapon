<?php
/**
 * Update all document configurations to use "Raven Weapon AG" as company name
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

// Get all document base configs
$result = apiRequest($API_URL, $token, 'POST', 'search/document-base-config', [
    'limit' => 100
]);

echo "Found " . count($result['data'] ?? []) . " document configurations\n\n";

foreach ($result['data'] ?? [] as $config) {
    $id = $config['id'];
    $name = $config['name'] ?? 'unknown';
    $currentCompanyName = $config['config']['companyName'] ?? 'not set';

    echo "Document: $name (ID: $id)\n";
    echo "  Current company name: $currentCompanyName\n";

    // Update the company name
    $updateResult = apiRequest($API_URL, $token, 'PATCH', 'document-base-config/' . $id, [
        'config' => [
            'companyName' => 'Raven Weapon AG'
        ]
    ]);

    if (isset($updateResult['errors'])) {
        echo "  ERROR: " . json_encode($updateResult['errors']) . "\n";
    } else {
        echo "  Updated to: Raven Weapon AG\n";
    }
    echo "\n";
}

// Also check for system config that might have account holder
echo "Checking system config for bank account holder...\n";
$sysConfig = apiRequest($API_URL, $token, 'POST', 'search/system-config', [
    'limit' => 500
]);

foreach ($sysConfig['data'] ?? [] as $config) {
    $key = $config['configurationKey'] ?? '';
    $value = $config['configurationValue'] ?? '';

    // Look for anything with "Nikola" or "Mitrovic" or bank-related settings
    if (stripos($key, 'bank') !== false || stripos($key, 'account') !== false ||
        stripos(json_encode($value), 'Nikola') !== false ||
        stripos(json_encode($value), 'Mitrovic') !== false) {
        echo "  Found: $key = " . json_encode($value) . "\n";
    }
}

echo "\nDone!\n";
