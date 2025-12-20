<?php
/**
 * Fix Belt closure pack image - find the real product image
 */

$API_URL = 'https://ortak.ch';

$ch = curl_init($API_URL . '/api/oauth/token');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['grant_type' => 'client_credentials', 'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG', 'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'])]);
$token = json_decode(curl_exec($ch), true)['access_token'];
curl_close($ch);

echo "═══════════════════════════════════════════════════════════\n";
echo "     FIX BELT CLOSURE PACK IMAGE                           \n";
echo "═══════════════════════════════════════════════════════════\n\n";

$productId = 'b28ab7288ab348791c4bbb87d5debde3';

// Search for media with "belt" or "closure" or the product SKU pattern
$patterns = ['belt', 'closure', '13-00102', 'SN-belt'];

foreach ($patterns as $pattern) {
    echo "Searching media for '$pattern'...\n";
    $ch = curl_init($API_URL . '/api/search/media');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode([
        'limit' => 20,
        'filter' => [['type' => 'contains', 'field' => 'fileName', 'value' => $pattern]]
    ])]);
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!empty($result['data'])) {
        echo "  Found " . count($result['data']) . " media:\n";
        foreach ($result['data'] as $m) {
            $attrs = $m['attributes'] ?? $m;
            $fileName = $attrs['fileName'] ?? 'unknown';
            $url = $attrs['url'] ?? '';
            echo "    - $fileName (ID: " . substr($m['id'], 0, 8) . "...)\n";
        }
    } else {
        echo "  No results\n";
    }
    echo "\n";
}

// Also check what's currently on the product
echo "Current product media:\n";
$ch = curl_init($API_URL . '/api/search/product-media');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode([
    'filter' => [['type' => 'equals', 'field' => 'productId', 'value' => $productId]],
    'associations' => ['media' => []]
])]);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!empty($result['data'])) {
    foreach ($result['data'] as $pm) {
        $pmAttrs = $pm['attributes'] ?? $pm;
        $mediaId = $pmAttrs['mediaId'] ?? 'N/A';
        echo "  ProductMedia: " . $pm['id'] . "\n";
        echo "    MediaId: $mediaId\n";

        // Get media details
        if ($pm['relationships']['media']['data'] ?? null) {
            $mId = $pm['relationships']['media']['data']['id'];
            echo "    Related Media ID: $mId\n";
        }
    }
} else {
    echo "  No product_media found\n";
}
