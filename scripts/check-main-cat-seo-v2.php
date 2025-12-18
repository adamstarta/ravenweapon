<?php
/**
 * Check main category SEO URLs - detailed
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

// Search for specific paths
$paths = ['alle-produkte/', 'waffen/', 'ausruestung/', 'zubehoer/', 'munition/'];

echo "=== Main Category SEO URL Check (by path) ===\n\n";

foreach ($paths as $path) {
    $seoResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
        'filter' => [
            ['type' => 'equals', 'field' => 'seoPathInfo', 'value' => $path]
        ],
        'includes' => [
            'seo_url' => ['id', 'seoPathInfo', 'foreignKey', 'isCanonical', 'isDeleted', 'salesChannelId']
        ]
    ]);

    echo "Path: /$path\n";

    if (isset($seoResponse['data']) && count($seoResponse['data']) > 0) {
        foreach ($seoResponse['data'] as $url) {
            $attrs = $url['attributes'] ?? $url;
            $canonical = $attrs['isCanonical'] ?? false;
            $deleted = $attrs['isDeleted'] ?? false;
            $foreignKey = $attrs['foreignKey'] ?? '';
            echo "  ID: {$url['id']}\n";
            echo "  Category: $foreignKey\n";
            echo "  Canonical: " . ($canonical ? 'YES' : 'no') . "\n";
            echo "  Deleted: " . ($deleted ? 'YES' : 'no') . "\n";
        }
    } else {
        echo "  NOT FOUND!\n";
    }
    echo "\n";
}
