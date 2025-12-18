<?php
/**
 * Check detailed SEO URL info including sales channel
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

// Get one specific SEO URL with ALL details
$urlId = '019b2cda98aa72178cf8b4995135fc0f'; // alle-produkte

echo "=== Detailed SEO URL Info ===\n\n";

$response = apiRequest($baseUrl, $token, 'GET', '/seo-url/' . $urlId);

if (isset($response['data'])) {
    $url = $response['data'];
    $attrs = $url['attributes'] ?? $url;

    echo "URL ID: {$url['id']}\n";
    echo "seoPathInfo: " . ($attrs['seoPathInfo'] ?? 'N/A') . "\n";
    echo "pathInfo: " . ($attrs['pathInfo'] ?? 'N/A') . "\n";
    echo "routeName: " . ($attrs['routeName'] ?? 'N/A') . "\n";
    echo "foreignKey: " . ($attrs['foreignKey'] ?? 'N/A') . "\n";
    echo "salesChannelId: " . ($attrs['salesChannelId'] ?? 'N/A') . "\n";
    echo "languageId: " . ($attrs['languageId'] ?? 'N/A') . "\n";
    echo "isCanonical: " . (($attrs['isCanonical'] ?? false) ? 'YES' : 'NO') . "\n";
    echo "isDeleted: " . (($attrs['isDeleted'] ?? false) ? 'YES' : 'NO') . "\n";
    echo "isModified: " . (($attrs['isModified'] ?? false) ? 'YES' : 'NO') . "\n";
} else {
    echo "Error fetching URL\n";
    print_r($response);
}

echo "\n=== Sales Channel Info ===\n";

$scResponse = apiRequest($baseUrl, $token, 'POST', '/search/sales-channel', [
    'includes' => [
        'sales_channel' => ['id', 'name', 'active', 'languageId']
    ],
    'limit' => 10
]);

if (isset($scResponse['data'])) {
    foreach ($scResponse['data'] as $sc) {
        $attrs = $sc['attributes'] ?? $sc;
        echo "\nSales Channel: " . ($attrs['name'] ?? 'N/A') . "\n";
        echo "  ID: {$sc['id']}\n";
        echo "  Active: " . (($attrs['active'] ?? false) ? 'YES' : 'NO') . "\n";
        echo "  Language ID: " . ($attrs['languageId'] ?? 'N/A') . "\n";
    }
}

echo "\n\n=== Language Info ===\n";

$langResponse = apiRequest($baseUrl, $token, 'POST', '/search/language', [
    'includes' => [
        'language' => ['id', 'name', 'localeId']
    ],
    'limit' => 10
]);

if (isset($langResponse['data'])) {
    foreach ($langResponse['data'] as $lang) {
        $attrs = $lang['attributes'] ?? $lang;
        echo "Language: " . ($attrs['name'] ?? 'N/A') . " (ID: {$lang['id']})\n";
    }
}
