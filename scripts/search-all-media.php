<?php
/**
 * Search all media to find belt closure pack images
 */

$API_URL = 'https://ortak.ch';

$ch = curl_init($API_URL . '/api/oauth/token');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['grant_type' => 'client_credentials', 'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG', 'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'])]);
$token = json_decode(curl_exec($ch), true)['access_token'];
curl_close($ch);

// Search for 13-00102 pattern (belt closure pack article number)
$patterns = ['13-00102', '13-00103', '13-00104', '13-00105'];

foreach ($patterns as $pattern) {
    $ch = curl_init($API_URL . '/api/search/media');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode([
        'limit' => 10,
        'filter' => [['type' => 'contains', 'field' => 'fileName', 'value' => $pattern]]
    ])]);
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!empty($result['data'])) {
        echo "Pattern '$pattern':\n";
        foreach ($result['data'] as $m) {
            $attrs = $m['attributes'] ?? $m;
            echo "  - " . ($attrs['fileName'] ?? 'unknown') . " (ID: " . $m['id'] . ")\n";
        }
        echo "\n";
    }
}

// Check what media ID a87eb9f4a11605820d041afd7dab7a9a is
echo "Current media a87eb9f4a11605820d041afd7dab7a9a details:\n";
$ch = curl_init($API_URL . '/api/media/a87eb9f4a11605820d041afd7dab7a9a');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json']]);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);

if ($result['data'] ?? null) {
    $attrs = $result['data']['attributes'] ?? $result['data'];
    echo "  fileName: " . ($attrs['fileName'] ?? 'N/A') . "\n";
    echo "  url: " . ($attrs['url'] ?? 'N/A') . "\n";
}

// Let's also check the OTHER product (13-00110-01-000) to see what media it has
echo "\n\nChecking other Belt closure product (13-00110-01-000 / b66f279c6b3d60172ca00b90e3cf7017):\n";
$ch = curl_init($API_URL . '/api/search/product-media');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode([
    'filter' => [['type' => 'equals', 'field' => 'productId', 'value' => 'b66f279c6b3d60172ca00b90e3cf7017']],
    'associations' => ['media' => []]
])]);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!empty($result['data'])) {
    foreach ($result['data'] as $pm) {
        $mediaRel = $pm['relationships']['media']['data'] ?? null;
        if ($mediaRel) {
            $mediaId = $mediaRel['id'];
            echo "  Media ID: $mediaId\n";

            // Get media details
            $ch = curl_init($API_URL . '/api/media/' . $mediaId);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token]]);
            $mResult = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if ($mResult['data'] ?? null) {
                $mAttrs = $mResult['data']['attributes'] ?? $mResult['data'];
                echo "    fileName: " . ($mAttrs['fileName'] ?? 'N/A') . "\n";
            }
        }
    }
}
