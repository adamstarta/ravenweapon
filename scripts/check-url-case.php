<?php
/**
 * Check URL case consistency
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

echo "=== Category SEO URLs ===\n\n";

$catSeoResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
    'limit' => 100,
    'filter' => [
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page'],
        ['type' => 'equals', 'field' => 'isCanonical', 'value' => true],
        ['type' => 'equals', 'field' => 'isDeleted', 'value' => false]
    ],
    'includes' => [
        'seo_url' => ['id', 'seoPathInfo']
    ]
]);

if (isset($catSeoResponse['data'])) {
    foreach ($catSeoResponse['data'] as $url) {
        $attrs = $url['attributes'] ?? $url;
        $path = $attrs['seoPathInfo'] ?? '';
        echo "/$path\n";
    }
}

echo "\n=== Sample Product SEO URLs ===\n\n";

$prodSeoResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
    'limit' => 10,
    'filter' => [
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.detail.page'],
        ['type' => 'equals', 'field' => 'isCanonical', 'value' => true],
        ['type' => 'equals', 'field' => 'isDeleted', 'value' => false]
    ],
    'includes' => [
        'seo_url' => ['id', 'seoPathInfo']
    ]
]);

if (isset($prodSeoResponse['data'])) {
    foreach ($prodSeoResponse['data'] as $url) {
        $attrs = $url['attributes'] ?? $url;
        $path = $attrs['seoPathInfo'] ?? '';
        echo "/$path\n";
    }
}
