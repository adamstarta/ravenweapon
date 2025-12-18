<?php
/**
 * Check for both uppercase and lowercase SEO URLs
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

// Search for both uppercase and lowercase versions
$searchPaths = [
    'Alle-Produkte/', 'alle-produkte/',
    'Waffen/', 'waffen/',
    'Ausruestung/', 'ausruestung/',
    'Munition/', 'munition/',
    'Zubehoer/', 'zubehoer/',
    'Raven-Caliber-Kit/', 'raven-caliber-kit/',
    'Zielhilfen-Optik-Zubehoer/', 'zielhilfen-optik-zubehoer/'
];

echo "=== Searching for all main category SEO URLs ===\n\n";

foreach ($searchPaths as $path) {
    $seoResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
        'filter' => [
            ['type' => 'equals', 'field' => 'seoPathInfo', 'value' => $path],
            ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
        ],
        'includes' => [
            'seo_url' => ['id', 'seoPathInfo', 'foreignKey', 'isCanonical', 'isDeleted']
        ]
    ]);

    if (isset($seoResponse['data']) && count($seoResponse['data']) > 0) {
        foreach ($seoResponse['data'] as $url) {
            $attrs = $url['attributes'] ?? $url;
            $canonical = $attrs['isCanonical'] ?? false;
            $deleted = $attrs['isDeleted'] ?? false;
            $foreignKey = $attrs['foreignKey'] ?? '';
            
            $markers = [];
            if ($canonical) $markers[] = 'CANONICAL';
            if ($deleted) $markers[] = 'DELETED';
            $status = $markers ? ' [' . implode(', ', $markers) . ']' : '';
            
            echo "/$path$status\n";
            echo "  ID: {$url['id']}\n";
            echo "  Category: $foreignKey\n\n";
        }
    }
}
