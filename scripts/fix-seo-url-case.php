<?php
/**
 * Fix SEO URL case - update to lowercase
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

$token = getAccessToken($baseUrl, $clientId, $clientSecret);

echo "=== Fixing Main Category SEO URL Case ===\n\n";

// Map of SEO URL IDs to correct lowercase paths
$urlsToFix = [
    '019b2cda98aa72178cf8b4995135fc0f' => 'alle-produkte/',
    '019b2cda849b72d29bbbe32ea076639a' => 'waffen/',
    '019b2cda8a057210806bdcc7a1cfb70b' => 'raven-caliber-kit/',
    '019b2cda8f5a7198b4c6c8894da29e90' => 'zielhilfen-optik-zubehoer/',
    '019b2cdafdca71118af30a300d206fee' => 'munition/',
    '019b2cdb16007056b168f55326656c5d' => 'zubehoer/',
    '019b2cda9ddb71b280692d3e9a72e46d' => 'ausruestung/'
];

$fixed = 0;
$errors = 0;

foreach ($urlsToFix as $urlId => $newPath) {
    $response = apiRequest($baseUrl, $token, 'PATCH', '/seo-url/' . $urlId, [
        'seoPathInfo' => $newPath
    ]);

    if ($response['code'] >= 200 && $response['code'] < 300) {
        echo "✓ Updated: /$newPath\n";
        $fixed++;
    } else {
        $error = $response['body']['errors'][0]['detail'] ?? 'Unknown error';
        echo "✗ Error /$newPath: " . substr($error, 0, 80) . "\n";
        $errors++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Fixed: $fixed\n";
echo "Errors: $errors\n\n";

echo "Clear cache:\n";
echo "docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'\n";
