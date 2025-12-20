<?php
/**
 * Remove extra images from Belt closure pack (5) -11, keep only 1
 * Product: SN-belt-closure-pack-5-11 (ID: b28ab7288ab348791c4bbb87d5debde3)
 */

$API_URL = 'https://ortak.ch';

$ch = curl_init($API_URL . '/api/oauth/token');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['grant_type' => 'client_credentials', 'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG', 'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'])]);
$token = json_decode(curl_exec($ch), true)['access_token'];
curl_close($ch);

echo "═══════════════════════════════════════════════════════════\n";
echo "     REMOVE EXTRA IMAGES - KEEP ONLY 1                     \n";
echo "═══════════════════════════════════════════════════════════\n\n";

$productId = 'b28ab7288ab348791c4bbb87d5debde3';

// Get product with media associations
$ch = curl_init($API_URL . '/api/search/product');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode([
    'ids' => [$productId],
    'associations' => ['media' => []]
])]);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);

$product = $result['data'][0] ?? null;
if (!$product) {
    die("Product not found!\n");
}

$attrs = $product['attributes'] ?? $product;
$coverId = $attrs['coverId'] ?? null;

echo "Product: " . ($attrs['name'] ?? 'Unknown') . "\n";
echo "Current Cover ID: $coverId\n";

// Get product_media entries
$mediaRelations = $product['relationships']['media']['data'] ?? [];
echo "Current media count: " . count($mediaRelations) . "\n\n";

if (count($mediaRelations) <= 1) {
    echo "Already has 1 or fewer images. Nothing to do.\n";
    exit;
}

// Get full product_media details to find which ones to delete
$ch = curl_init($API_URL . '/api/search/product-media');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode([
    'filter' => [['type' => 'equals', 'field' => 'productId', 'value' => $productId]],
    'sort' => [['field' => 'position', 'order' => 'ASC']]
])]);
$pmResult = json_decode(curl_exec($ch), true);
curl_close($ch);

$productMedia = $pmResult['data'] ?? [];
echo "Product media entries:\n";

$keepId = null;
$deleteIds = [];

foreach ($productMedia as $idx => $pm) {
    $pmId = $pm['id'];
    $pmAttrs = $pm['attributes'] ?? $pm;
    $position = $pmAttrs['position'] ?? $idx;

    if ($idx === 0) {
        // Keep the first one (position 0)
        $keepId = $pmId;
        echo "  ✓ KEEP: $pmId (position $position)\n";
    } else {
        // Delete the rest
        $deleteIds[] = $pmId;
        echo "  ✗ DELETE: $pmId (position $position)\n";
    }
}

echo "\nDeleting " . count($deleteIds) . " product_media entries...\n";

// Delete each product_media entry
foreach ($deleteIds as $pmId) {
    $ch = curl_init($API_URL . '/api/product-media/' . $pmId);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 204) {
        echo "  ✓ Deleted: " . substr($pmId, 0, 8) . "...\n";
    } else {
        echo "  ✗ Failed to delete $pmId: HTTP $httpCode\n";
    }
}

// Update cover to the kept image if needed
if ($keepId && $coverId !== $keepId) {
    echo "\nUpdating cover to kept image...\n";
    $ch = curl_init($API_URL . '/api/product/' . $productId);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['coverId' => $keepId])
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 204) {
        echo "  ✓ Cover updated\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "  DONE! Product now has 1 image only.                      \n";
echo "═══════════════════════════════════════════════════════════\n";
