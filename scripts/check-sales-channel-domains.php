<?php
/**
 * Check sales channel domain configuration
 */

$baseUrl = 'https://ortak.ch/api';
$clientId = 'SWIAC3HJVHFJMHQYRWRUM1E1SG';
$clientSecret = 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg';

function getAccessToken($baseUrl, $clientId, $clientSecret) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/oauth/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['access_token'];
}

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$token = getAccessToken($baseUrl, $clientId, $clientSecret);

echo "=== Sales Channel Domain Configuration ===\n\n";

$response = apiRequest($baseUrl, $token, 'POST', '/search/sales-channel-domain', [
    'associations' => [
        'language' => [],
        'salesChannel' => []
    ],
    'limit' => 50
]);

if (isset($response['data'])) {
    foreach ($response['data'] as $domain) {
        $attrs = $domain['attributes'] ?? $domain;
        echo "Domain: " . ($attrs['url'] ?? 'N/A') . "\n";
        echo "  ID: {$domain['id']}\n";
        echo "  Language ID: " . ($attrs['languageId'] ?? 'N/A') . "\n";
        echo "  Sales Channel ID: " . ($attrs['salesChannelId'] ?? 'N/A') . "\n";
        echo "  HreflangUseOnlyLocale: " . (($attrs['hreflangUseOnlyLocale'] ?? false) ? 'YES' : 'NO') . "\n";
        echo "\n";
    }
}

// Also check the language names
echo "\n=== Language IDs Reference ===\n";
echo "English: 2fbb5fe2e29a4d70aa5854ce7ce3e20b\n";
echo "German: 0191c12cc15e72189d57328fb3d2d987\n";
