<?php
/**
 * Check SEO URLs by language for Alle Produkte category
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

$categoryId = '019aee0f487c79a1a8814377c46e0c10'; // Alle Produkte
$englishLanguageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
$germanLanguageId = '0191c12cc15e72189d57328fb3d2d987';

echo "=== SEO URLs for 'Alle Produkte' by Language ===\n\n";

// Search for all SEO URLs for this category regardless of language
$seoResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
    'filter' => [
        ['type' => 'equals', 'field' => 'foreignKey', 'value' => $categoryId],
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
    ],
    'limit' => 50
]);

if (isset($seoResponse['data']) && count($seoResponse['data']) > 0) {
    foreach ($seoResponse['data'] as $url) {
        $attrs = $url['attributes'] ?? $url;
        $path = $attrs['seoPathInfo'] ?? '';
        $langId = $attrs['languageId'] ?? '';
        $canonical = $attrs['isCanonical'] ?? false;
        $deleted = $attrs['isDeleted'] ?? false;

        $langName = 'Unknown';
        if ($langId === $englishLanguageId) $langName = 'English';
        if ($langId === $germanLanguageId) $langName = 'German';

        $markers = [];
        if ($canonical) $markers[] = 'CANONICAL';
        if ($deleted) $markers[] = 'DELETED';
        $status = $markers ? ' [' . implode(', ', $markers) . ']' : '';

        echo "/$path$status\n";
        echo "  Language: $langName ($langId)\n";
        echo "  URL ID: {$url['id']}\n\n";
    }
} else {
    echo "No SEO URLs found!\n";
}

echo "\n=== Checking Sales Channel Default Language ===\n";

$scResponse = apiRequest($baseUrl, $token, 'POST', '/search/sales-channel', [
    'filter' => [
        ['type' => 'equals', 'field' => 'id', 'value' => '0191c12dd4b970949e9aeec40433be3e']
    ],
    'associations' => [
        'language' => [],
        'languages' => []
    ]
]);

if (isset($scResponse['data'][0])) {
    $sc = $scResponse['data'][0];
    $attrs = $sc['attributes'] ?? $sc;
    echo "\nStorefront Sales Channel:\n";
    echo "  Default Language ID: " . ($attrs['languageId'] ?? 'N/A') . "\n";

    if (isset($sc['relationships']['languages']['data'])) {
        echo "  All Languages:\n";
        foreach ($sc['relationships']['languages']['data'] as $lang) {
            echo "    - " . $lang['id'] . "\n";
        }
    }
}
