<?php
/**
 * Check if main category SEO URLs exist with lowercase
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

// Main categories we expect
$mainCategories = [
    '019aee0f487c79a1a8814377c46e0c10' => 'alle-produkte/',
    'a61f19c9cb4b11f0b4074aca3d279c31' => 'waffen/',
    'a61f1ec3cb4b11f0b4074aca3d279c31' => 'raven-caliber-kit/',
    '019adeff65f97225927586968691dc02' => 'zielhilfen-optik-zubehoer/',
    '2f40311624aea6de289c770f0bfd0ff9' => 'munition/',
    '604131c6ae1646c98623da4fe61a739b' => 'zubehoer/',
    '019b0857613474e6a799cfa07d143c76' => 'ausruestung/'
];

echo "=== Main Category SEO URL Check ===\n\n";

foreach ($mainCategories as $catId => $expectedPath) {
    $seoResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
        'filter' => [
            ['type' => 'equals', 'field' => 'foreignKey', 'value' => $catId],
            ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page'],
            ['type' => 'equals', 'field' => 'isDeleted', 'value' => false]
        ],
        'includes' => [
            'seo_url' => ['id', 'seoPathInfo', 'isCanonical']
        ]
    ]);

    echo "Category ID: $catId\n";
    echo "Expected: /$expectedPath\n";

    if (isset($seoResponse['data']) && count($seoResponse['data']) > 0) {
        foreach ($seoResponse['data'] as $url) {
            $attrs = $url['attributes'] ?? $url;
            $path = $attrs['seoPathInfo'] ?? '';
            $canonical = $attrs['isCanonical'] ?? false;
            $status = $canonical ? '✓ CANONICAL' : '  (non-canonical)';
            echo "Found: /$path $status\n";
        }
    } else {
        echo "MISSING! No SEO URL found\n";
    }
    echo "\n";
}
