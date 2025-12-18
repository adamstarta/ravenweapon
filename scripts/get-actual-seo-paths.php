<?php
/**
 * Get the ACTUAL stored seoPathInfo values
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

$token = getAccessToken($baseUrl, $clientId, $clientSecret);

// Direct GET requests to get exact stored values
$urlIds = [
    '019b2cda98aa72178cf8b4995135fc0f' => 'Alle Produkte',
    '019b2cda849b72d29bbbe32ea076639a' => 'Waffen',
    '019b2cda8a057210806bdcc7a1cfb70b' => 'Raven Caliber Kit',
    '019b2cda8f5a7198b4c6c8894da29e90' => 'Zielhilfen',
    '019b2cdafdca71118af30a300d206fee' => 'Munition',
    '019b2cdb16007056b168f55326656c5d' => 'Zubehoer',
    '019b2cda9ddb71b280692d3e9a72e46d' => 'Ausruestung'
];

echo "=== ACTUAL Stored seoPathInfo Values ===\n\n";

foreach ($urlIds as $urlId => $catName) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/seo-url/' . $urlId);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (isset($data['data'])) {
        $attrs = $data['data']['attributes'] ?? $data['data'];
        $path = $attrs['seoPathInfo'] ?? 'N/A';
        $canonical = $attrs['isCanonical'] ?? false;
        $deleted = $attrs['isDeleted'] ?? false;

        // Check for uppercase letters
        $hasUpper = preg_match('/[A-Z]/', $path) ? 'HAS UPPERCASE' : 'all lowercase';

        echo "$catName:\n";
        echo "  Exact path: \"$path\"\n";
        echo "  Case: $hasUpper\n";
        echo "  Canonical: " . ($canonical ? 'YES' : 'NO') . "\n";
        echo "  Deleted: " . ($deleted ? 'YES' : 'NO') . "\n";
        echo "\n";
    }
}
