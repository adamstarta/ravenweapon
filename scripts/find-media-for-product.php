<?php
/**
 * Find media files for product 13-00110
 */

$ch = curl_init('https://ortak.ch/api/oauth/token');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['grant_type' => 'client_credentials', 'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG', 'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'])]);
$token = json_decode(curl_exec($ch), true)['access_token'];
curl_close($ch);

// Search for media with 13-00110 pattern
$ch = curl_init('https://ortak.ch/api/search/media');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode([
    'limit' => 20,
    'filter' => [
        ['type' => 'contains', 'field' => 'fileName', 'value' => '13-00110']
    ]
])]);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);

echo "Media files matching '13-00110':\n\n";
if (empty($result['data'])) {
    echo "  No media found!\n";
} else {
    foreach ($result['data'] as $m) {
        $attrs = $m['attributes'] ?? $m;
        echo "  - " . ($attrs['fileName'] ?? 'unknown') . "." . ($attrs['fileExtension'] ?? '') . "\n";
        echo "    ID: " . $m['id'] . "\n";
    }
}

// Also check for "belt closure" in media
echo "\n\nMedia files matching 'belt closure':\n\n";
$ch = curl_init('https://ortak.ch/api/search/media');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode([
    'limit' => 20,
    'filter' => [
        ['type' => 'contains', 'field' => 'fileName', 'value' => 'belt']
    ]
])]);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($result['data'])) {
    echo "  No media found!\n";
} else {
    foreach ($result['data'] as $m) {
        $attrs = $m['attributes'] ?? $m;
        echo "  - " . ($attrs['fileName'] ?? 'unknown') . "." . ($attrs['fileExtension'] ?? '') . "\n";
    }
}
