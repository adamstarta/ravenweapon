<?php
/**
 * Fix media association for Belt closure pack (5) -11 product
 */

$API_URL = 'https://ortak.ch';

$ch = curl_init($API_URL . '/api/oauth/token');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['grant_type' => 'client_credentials', 'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG', 'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'])]);
$token = json_decode(curl_exec($ch), true)['access_token'];
curl_close($ch);

echo "═══════════════════════════════════════════════════════════\n";
echo "     FIX BELT CLOSURE PACK MEDIA                           \n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Product ID and Media IDs
$productId = 'b66f279c6b3d60172ca00b90e3cf7017';
$mediaIds = [
    '2da4cad7194c2461d987059121120104', // DEFAULT image first (cover)
    '1d601c5446120273b88358cef94c6757',
    '9b65fb884615760cd278c55a92679f72'
];

echo "Product ID: $productId\n";
echo "Media IDs: " . count($mediaIds) . " images\n\n";

// Create product_media associations with unique IDs
$productMedia = [];
$firstProductMediaId = null;

foreach ($mediaIds as $idx => $mediaId) {
    $productMediaId = bin2hex(random_bytes(16));
    if ($idx === 0) {
        $firstProductMediaId = $productMediaId;
    }
    $productMedia[] = [
        'id' => $productMediaId,
        'mediaId' => $mediaId,
        'position' => $idx
    ];
    echo "  [$idx] Media: " . substr($mediaId, 0, 8) . "... → ProductMedia: " . substr($productMediaId, 0, 8) . "...\n";
}

echo "\nUpdating product with media and cover...\n";

// Update product
$ch = curl_init($API_URL . '/api/product/' . $productId);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'media' => $productMedia,
        'coverId' => $firstProductMediaId
    ])
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 204 || $httpCode == 200) {
    echo "\n✓ SUCCESS! Cover ID set to: " . substr($firstProductMediaId, 0, 8) . "...\n";
    echo "✓ Associated " . count($productMedia) . " images with product\n";
} else {
    echo "\n✗ Failed: HTTP $httpCode\n";
    $data = json_decode($response, true);
    if (!empty($data['errors'])) {
        foreach ($data['errors'] as $err) {
            echo "  " . ($err['detail'] ?? json_encode($err)) . "\n";
        }
    }
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "                        COMPLETE!                          \n";
echo "═══════════════════════════════════════════════════════════\n";
