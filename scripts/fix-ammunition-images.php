<?php
/**
 * Fix Ammunition Product Images
 * Properly links existing media to products
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

echo "\n======================================================================\n";
echo "     FIX AMMUNITION PRODUCT IMAGES\n";
echo "======================================================================\n\n";

$GLOBALS['token_data'] = ['token' => null, 'expires_at' => 0];

function getAccessToken($config, $forceRefresh = false) {
    if (!$forceRefresh && $GLOBALS['token_data']['token'] && $GLOBALS['token_data']['expires_at'] > time() + 60) {
        return $GLOBALS['token_data']['token'];
    }
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) return null;
    $data = json_decode($response, true);
    $GLOBALS['token_data']['token'] = $data['access_token'] ?? null;
    $GLOBALS['token_data']['expires_at'] = time() + ($data['expires_in'] ?? 600);
    return $GLOBALS['token_data']['token'];
}

function apiRequest($method, $endpoint, $data, $config, $retry = true) {
    $token = getAccessToken($config);
    if (!$token) return ['code' => 0, 'body' => null];
    $ch = curl_init();
    $url = $config['shopware_url'] . '/api/' . ltrim($endpoint, '/');
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    if ($data && in_array($method, ['POST', 'PATCH', 'PUT'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 401 && $retry) {
        $GLOBALS['token_data']['token'] = null;
        return apiRequest($method, $endpoint, $data, $config, false);
    }
    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

// Authenticate
$token = getAccessToken($config);
if (!$token) die("ERROR: Failed to authenticate!\n");
echo "Authenticated OK\n\n";

// Get ammunition products
$result = apiRequest('POST', '/search/product', [
    'filter' => [['type' => 'contains', 'field' => 'productNumber', 'value' => 'AMMO-']],
    'associations' => ['cover' => [], 'media' => []]
], $config);

$products = $result['body']['data'] ?? [];
echo "Found " . count($products) . " ammunition products\n\n";

// Load scraped data for image URLs
$scrapedData = json_decode(file_get_contents(__DIR__ . '/ammunition-data/ammunition-products.json'), true);
$scrapedByName = [];
foreach ($scrapedData['products'] ?? [] as $p) {
    $scrapedByName[$p['name']] = $p;
}

foreach ($products as $product) {
    $productId = $product['id'];
    $productName = $product['name'];
    echo "Processing: $productName\n";
    echo "  Product ID: $productId\n";

    // Get scraped image URL
    $scraped = $scrapedByName[$productName] ?? null;
    if (!$scraped || empty($scraped['images'])) {
        echo "  SKIP: No scraped image found\n\n";
        continue;
    }

    $imageUrl = $scraped['images'][0];
    echo "  Image URL: $imageUrl\n";

    // Download image
    $imageData = @file_get_contents($imageUrl);
    if (!$imageData) {
        echo "  ERROR: Failed to download image\n\n";
        continue;
    }

    // Create media folder for products if not exists
    $folderResult = apiRequest('POST', '/search/media-folder', [
        'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Product Media']]
    ], $config);
    $folderId = $folderResult['body']['data'][0]['id'] ?? null;

    // Create media entry
    $mediaId = bin2hex(random_bytes(16));
    $result = apiRequest('POST', '/media', [
        'id' => $mediaId,
        'mediaFolderId' => $folderId,
    ], $config);

    if ($result['code'] !== 204 && $result['code'] !== 200) {
        echo "  ERROR: Failed to create media entry\n\n";
        continue;
    }

    // Upload the image
    $ext = 'jpg';
    if (strpos($imageUrl, '.png') !== false) $ext = 'png';
    $fileName = preg_replace('/[^a-zA-Z0-9-]/', '-', substr($productName, 0, 40)) . '-' . substr($mediaId, 0, 8);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . "/api/_action/media/$mediaId/upload?extension=$ext&fileName=" . urlencode($fileName),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . getAccessToken($config),
            'Content-Type: image/' . ($ext === 'jpg' ? 'jpeg' : $ext),
        ],
        CURLOPT_POSTFIELDS => $imageData,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 204 && $httpCode !== 200) {
        echo "  ERROR: Failed to upload image (HTTP $httpCode)\n\n";
        continue;
    }
    echo "  Media uploaded: $mediaId\n";

    // Create product-media association
    $productMediaId = bin2hex(random_bytes(16));
    $result = apiRequest('POST', '/product-media', [
        'id' => $productMediaId,
        'productId' => $productId,
        'mediaId' => $mediaId,
        'position' => 0,
    ], $config);

    if ($result['code'] !== 204 && $result['code'] !== 200) {
        echo "  ERROR: Failed to create product-media link: " . json_encode($result['body']) . "\n\n";
        continue;
    }
    echo "  Product-media linked\n";

    // Update product with cover
    $result = apiRequest('PATCH', "/product/$productId", [
        'coverId' => $productMediaId,
    ], $config);

    if ($result['code'] === 204 || $result['code'] === 200) {
        echo "  SUCCESS: Cover set!\n\n";
    } else {
        echo "  ERROR setting cover: " . json_encode($result['body']) . "\n\n";
    }
}

echo "======================================================================\n";
echo "Done! Clearing cache...\n";

$result = apiRequest('DELETE', '/_action/cache', null, $config);
echo "Cache cleared\n\n";

echo "Check: https://ortak.ch/navigation/2f40311624aea6de289c770f0bfd0ff9\n";
