<?php
/**
 * Restore correct image for Belt closure pack (5) -11
 */

$API_URL = 'https://ortak.ch';

$ch = curl_init($API_URL . '/api/oauth/token');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['grant_type' => 'client_credentials', 'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG', 'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'])]);
$token = json_decode(curl_exec($ch), true)['access_token'];
curl_close($ch);

echo "═══════════════════════════════════════════════════════════\n";
echo "     RESTORE BELT CLOSURE PACK IMAGE                       \n";
echo "═══════════════════════════════════════════════════════════\n\n";

$productId = 'b28ab7288ab348791c4bbb87d5debde3';

// Search for the correct media (13-00110)
$ch = curl_init($API_URL . '/api/search/media');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode([
    'limit' => 10,
    'filter' => [['type' => 'contains', 'field' => 'fileName', 'value' => '13-00110']]
])]);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);

echo "Found media for 13-00110:\n";
$correctMediaId = null;
foreach ($result['data'] ?? [] as $m) {
    $attrs = $m['attributes'] ?? $m;
    $fileName = $attrs['fileName'] ?? 'unknown';
    echo "  - $fileName (ID: " . $m['id'] . ")\n";

    // Prefer the DEFAULT image as the main one
    if (strpos($fileName, 'DEFAULT') !== false) {
        $correctMediaId = $m['id'];
    } elseif (!$correctMediaId) {
        $correctMediaId = $m['id'];
    }
}

if (!$correctMediaId) {
    echo "\n✗ No correct media found! Need to re-upload.\n";
    exit;
}

echo "\nUsing media ID: $correctMediaId\n\n";

// First, delete the current wrong product_media
echo "Removing current wrong image association...\n";
$ch = curl_init($API_URL . '/api/search/product-media');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode([
    'filter' => [['type' => 'equals', 'field' => 'productId', 'value' => $productId]]
])]);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);

foreach ($result['data'] ?? [] as $pm) {
    $pmId = $pm['id'];
    $ch = curl_init($API_URL . '/api/product-media/' . $pmId);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token]
    ]);
    curl_exec($ch);
    curl_close($ch);
    echo "  Deleted: $pmId\n";
}

// Now create new product_media with correct image
echo "\nAssociating correct image...\n";
$productMediaId = bin2hex(random_bytes(16));

$ch = curl_init($API_URL . '/api/product/' . $productId);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'media' => [[
            'id' => $productMediaId,
            'mediaId' => $correctMediaId,
            'position' => 0
        ]],
        'coverId' => $productMediaId
    ])
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 204 || $httpCode == 200) {
    echo "✓ SUCCESS! Correct image associated.\n";
} else {
    echo "✗ Failed: HTTP $httpCode\n";
    $data = json_decode($response, true);
    print_r($data);
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "                        COMPLETE!                          \n";
echo "═══════════════════════════════════════════════════════════\n";
