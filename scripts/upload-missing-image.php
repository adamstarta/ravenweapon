<?php
/**
 * Upload the one missing Snigel image
 */

$OLD_URL = 'https://ortak.ch';
$NEW_URL = 'http://77.42.19.154:8080';

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => 'admin',
            'password' => 'shopware'
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true)['access_token'] ?? null;
}

function apiPost($baseUrl, $token, $endpoint, $data) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

function apiPatch($baseUrl, $token, $endpoint, $data) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

function apiGet($baseUrl, $token, $endpoint) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

echo "Uploading missing Snigel image...\n\n";

$oldToken = getToken($OLD_URL);
$newToken = getToken($NEW_URL);

if (!$oldToken || !$newToken) {
    die("❌ Failed to get tokens\n");
}

$sku = 'SN-belt-closure-pack-5-11';
// The coverId is actually the mediaId in this case
$mediaId = 'a87eb9f4a11605820d041afd7dab7a9a';

echo "Media ID: {$mediaId}\n";

$mediaResponse = apiGet($OLD_URL, $oldToken, 'media/' . $mediaId);
$imageUrl = $mediaResponse['data']['attributes']['url'] ?? null;

if (!$imageUrl) {
    die("❌ Could not find image URL\n");
}

echo "Image URL: {$imageUrl}\n\n";

// Get product ID in NEW site
$newProduct = apiPost($NEW_URL, $newToken, 'search/product', [
    'filter' => [
        ['type' => 'equals', 'field' => 'productNumber', 'value' => $sku]
    ]
]);

$productId = $newProduct['data']['data'][0]['id'] ?? null;

if (!$productId) {
    die("❌ Product not found in NEW site\n");
}

echo "Product ID: {$productId}\n";

// Upload the image
$pathInfo = pathinfo(parse_url($imageUrl, PHP_URL_PATH));
$extension = $pathInfo['extension'] ?? 'jpg';
$fileName = $pathInfo['filename'] ?? $sku;

// Create media entry
$newMediaId = bin2hex(random_bytes(16));
$result = apiPost($NEW_URL, $newToken, 'media', ['id' => $newMediaId]);

if ($result['code'] >= 300 && $result['code'] != 204) {
    die("❌ Failed to create media entry\n");
}

// Upload from URL
$uploadUrl = $NEW_URL . '/api/_action/media/' . $newMediaId . '/upload?extension=' . $extension . '&fileName=' . urlencode($fileName);

$ch = curl_init($uploadUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $newToken,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode(['url' => $imageUrl])
]);
$uploadResponse = curl_exec($ch);
$uploadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($uploadCode >= 300 && $uploadCode != 204) {
    die("❌ Failed to upload image (HTTP {$uploadCode})\n");
}

// Create product media relation
$productMediaId = bin2hex(random_bytes(16));
$result = apiPost($NEW_URL, $newToken, 'product-media', [
    'id' => $productMediaId,
    'productId' => $productId,
    'mediaId' => $newMediaId,
    'position' => 1
]);

if ($result['code'] >= 300 && $result['code'] != 204) {
    die("❌ Failed to link media to product\n");
}

// Set as cover
$result = apiPatch($NEW_URL, $newToken, 'product/' . $productId, [
    'coverId' => $productMediaId
]);

if ($result['code'] >= 200 && $result['code'] < 300 || $result['code'] == 204) {
    echo "✅ Image uploaded and set as cover!\n";
} else {
    echo "⚠️ Image uploaded but cover not set\n";
}
