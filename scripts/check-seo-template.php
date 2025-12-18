<?php
/**
 * Check SEO URL template settings
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

echo "=== SEO URL Template Settings ===\n\n";

// Check seo_url_template entity
$response = apiRequest($baseUrl, $token, 'POST', '/search/seo-url-template', [
    'filter' => [
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
    ],
    'includes' => [
        'seo_url_template' => ['id', 'routeName', 'template', 'isValid']
    ]
]);

if (isset($response['data']) && count($response['data']) > 0) {
    foreach ($response['data'] as $template) {
        $attrs = $template['attributes'] ?? $template;
        echo "Route: " . ($attrs['routeName'] ?? '') . "\n";
        echo "Template: " . ($attrs['template'] ?? '') . "\n";
        echo "Valid: " . (($attrs['isValid'] ?? false) ? 'YES' : 'NO') . "\n\n";
    }
} else {
    echo "No template found for frontend.navigation.page\n";
}

// Also check the actual current SEO URL for one category to see what's stored
echo "\n=== Checking actual stored path for Alle Produkte ===\n";
$response = apiRequest($baseUrl, $token, 'GET', '/seo-url/019b2cda98aa72178cf8b4995135fc0f');
if (isset($response['data'])) {
    $attrs = $response['data']['attributes'] ?? $response['data'];
    echo "ID: 019b2cda98aa72178cf8b4995135fc0f\n";
    echo "Stored seoPathInfo: " . ($attrs['seoPathInfo'] ?? 'N/A') . "\n";
    echo "isCanonical: " . (($attrs['isCanonical'] ?? false) ? 'YES' : 'NO') . "\n";
    echo "isModified: " . (($attrs['isModified'] ?? false) ? 'YES' : 'NO') . "\n";
}
