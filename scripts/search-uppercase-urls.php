<?php
/**
 * Search for any uppercase SEO URLs in the system
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

$uppercasePaths = [
    'Alle-Produkte/', 'Waffen/', 'Ausruestung/', 'Zubehoer/',
    'Munition/', 'Raven-Caliber-Kit/', 'Zielhilfen-Optik-Zubehoer/'
];

echo "=== Searching for UPPERCASE SEO URLs ===\n\n";

foreach ($uppercasePaths as $path) {
    $response = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
        'filter' => [
            ['type' => 'equals', 'field' => 'seoPathInfo', 'value' => $path],
            ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
        ],
        'limit' => 10
    ]);

    if (isset($response['data']) && count($response['data']) > 0) {
        echo "FOUND: /$path\n";
        foreach ($response['data'] as $url) {
            $attrs = $url['attributes'] ?? $url;
            $canonical = $attrs['isCanonical'] ?? false;
            $deleted = $attrs['isDeleted'] ?? false;

            $markers = [];
            if ($canonical) $markers[] = 'CANONICAL';
            if ($deleted) $markers[] = 'DELETED';
            $status = $markers ? ' [' . implode(', ', $markers) . ']' : '';

            echo "  ID: {$url['id']}$status\n";
        }
        echo "\n";
    }
}

echo "\n=== Count of all navigation page SEO URLs ===\n";

$countResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
    'filter' => [
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
    ],
    'aggregations' => [
        ['type' => 'count', 'name' => 'count', 'field' => 'id']
    ],
    'limit' => 1
]);

if (isset($countResponse['aggregations']['count'])) {
    echo "Total category SEO URLs: " . $countResponse['aggregations']['count']['count'] . "\n";
}
