<?php
/**
 * Check .22 LR RAVEN product image status
 */

$config = [
    'shopware_url' => 'http://localhost',
    'api_user' => 'admin',
    'api_password' => 'shopware',
];

// Get token
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/oauth/token',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'grant_type' => 'password',
        'client_id' => 'administration',
        'username' => $config['api_user'],
        'password' => $config['api_password'],
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);
$token = $data['access_token'] ?? null;

if (!$token) {
    die("Failed to authenticate\n");
}

echo "Authenticated!\n\n";

// Search for .22 LR RAVEN product
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/search/product',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'filter' => [['type' => 'contains', 'field' => 'name', 'value' => '.22 LR RAVEN']],
        'associations' => [
            'media' => [],
            'cover' => ['associations' => ['media' => []]],
        ],
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);

$product = $result['data'][0] ?? null;

if (!$product) {
    die("Product not found!\n");
}

echo "Product: " . ($product['translated']['name'] ?? $product['name']) . "\n";
echo "Product ID: " . $product['id'] . "\n";
echo "Cover ID: " . ($product['coverId'] ?? 'NONE') . "\n\n";

// Check cover media
if (!empty($product['cover'])) {
    echo "Cover Media:\n";
    $cover = $product['cover'];
    echo "  Media ID: " . ($cover['mediaId'] ?? 'NONE') . "\n";
    if (!empty($cover['media'])) {
        echo "  URL: " . ($cover['media']['url'] ?? 'NONE') . "\n";
        echo "  Filename: " . ($cover['media']['fileName'] ?? 'NONE') . "\n";
    } else {
        echo "  Media object: MISSING\n";
    }
} else {
    echo "Cover: NONE\n";
}

echo "\n";

// Check all product media
echo "Product Media Collection:\n";
$media = $product['media'] ?? [];
if (empty($media)) {
    echo "  No media attached!\n";
} else {
    foreach ($media as $m) {
        echo "  - ID: " . $m['id'] . "\n";
        echo "    Media ID: " . ($m['mediaId'] ?? 'NONE') . "\n";
        echo "    Position: " . ($m['position'] ?? 'N/A') . "\n";
    }
}

echo "\n";

// Get media details separately
if (!empty($product['coverId'])) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . '/api/product-media/' . $product['coverId'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Cover Details (HTTP $httpCode):\n";
    $coverData = json_decode($response, true);
    print_r($coverData);
}
