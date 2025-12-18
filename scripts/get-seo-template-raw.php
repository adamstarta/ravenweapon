<?php
/**
 * Get the raw SEO URL template for categories
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

echo "=== ALL SEO URL Templates ===\n\n";

$response = apiRequest($baseUrl, $token, 'POST', '/search/seo-url-template', [
    'limit' => 50
]);

if (isset($response['data'])) {
    foreach ($response['data'] as $template) {
        $attrs = $template['attributes'] ?? $template;
        echo "Route: " . ($attrs['routeName'] ?? 'N/A') . "\n";
        echo "Entity: " . ($attrs['entityName'] ?? 'N/A') . "\n";
        echo "Template:\n";
        echo "---\n";
        echo ($attrs['template'] ?? 'N/A') . "\n";
        echo "---\n";
        echo "Is Valid: " . (($attrs['isValid'] ?? false) ? 'YES' : 'NO') . "\n";
        echo "Sales Channel ID: " . ($attrs['salesChannelId'] ?? 'NULL') . "\n";
        echo "ID: {$template['id']}\n";
        echo "\n" . str_repeat("=", 50) . "\n\n";
    }
}
