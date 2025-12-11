<?php
/**
 * Shopware Image Uploader
 * Uploads images to existing products
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
    'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg',
    'json_input' => __DIR__ . '/snigel-merged-products.json',
    'images_dir' => __DIR__ . '/snigel-data/images',
];

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║       SHOPWARE IMAGE UPLOADER FOR SNIGEL PRODUCTS          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Load products
$products = json_decode(file_get_contents($config['json_input']), true);
echo "Loaded " . count($products) . " products\n\n";

// Token management
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
            'grant_type' => 'client_credentials',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
        ]),
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $GLOBALS['token_data']['token'] = $data['access_token'] ?? null;
    $GLOBALS['token_data']['expires_at'] = time() + ($data['expires_in'] ?? 600);

    return $GLOBALS['token_data']['token'];
}

function apiRequest($method, $endpoint, $data, $config) {
    $token = getAccessToken($config);
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . '/api/' . ltrim($endpoint, '/'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    if ($data && in_array($method, ['POST', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

function findProduct($productNumber, $config) {
    $result = apiRequest('POST', '/search/product', [
        'filter' => [['type' => 'equals', 'field' => 'productNumber', 'value' => $productNumber]],
        'associations' => ['media' => []]
    ], $config);

    return $result['body']['data'][0] ?? null;
}

function uploadAndAttachImage($imagePath, $productId, $position, $config) {
    $token = getAccessToken($config);
    $mediaId = bin2hex(random_bytes(16));

    // Create media entity
    $result = apiRequest('POST', '/media', ['id' => $mediaId], $config);
    if ($result['code'] !== 204 && $result['code'] !== 200) {
        return null;
    }

    // Upload the file
    if (!file_exists($imagePath)) {
        return null;
    }

    $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
    $filename = pathinfo($imagePath, PATHINFO_FILENAME);

    // Clean filename
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);

    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];
    $contentType = $mimeTypes[$extension] ?? 'image/jpeg';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . "/api/_action/media/$mediaId/upload?extension=$extension&fileName=" . urlencode($filename),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: ' . $contentType,
        ],
        CURLOPT_POSTFIELDS => file_get_contents($imagePath),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 204 || $httpCode === 200) {
        return $mediaId;
    }

    return null;
}

// Get token
echo "Authenticating...\n";
$token = getAccessToken($config);
if (!$token) {
    die("Failed to authenticate\n");
}
echo "✓ Authenticated\n\n";

// Process products
$count = 0;
$total = count($products);
$uploaded = 0;
$skipped = 0;
$errors = 0;

foreach ($products as $product) {
    $count++;
    $productNumber = !empty($product['article_no']) ? $product['article_no'] : 'SN-' . $product['slug'];

    echo "[$count/$total] {$product['name']}... ";

    // Check if product has local images
    if (empty($product['local_images'])) {
        echo "NO IMAGES\n";
        $skipped++;
        continue;
    }

    // Find product in Shopware
    $shopwareProduct = findProduct($productNumber, $config);
    if (!$shopwareProduct) {
        echo "NOT FOUND\n";
        $errors++;
        continue;
    }

    $productId = $shopwareProduct['id'];

    // Check if product already has images
    $existingMedia = $shopwareProduct['media'] ?? [];
    if (count($existingMedia) > 0) {
        echo "HAS IMAGES (" . count($existingMedia) . ")\n";
        $skipped++;
        continue;
    }

    // Upload images (max 5)
    $mediaIds = [];
    $imageFiles = array_slice($product['local_images'], 0, 5);

    foreach ($imageFiles as $i => $imageFile) {
        $imagePath = $config['images_dir'] . '/' . $imageFile;

        if (!file_exists($imagePath)) {
            continue;
        }

        $mediaId = uploadAndAttachImage($imagePath, $productId, $i, $config);
        if ($mediaId) {
            $mediaIds[] = [
                'mediaId' => $mediaId,
                'position' => $i,
            ];
        }
    }

    if (empty($mediaIds)) {
        echo "UPLOAD FAILED\n";
        $errors++;
        continue;
    }

    // Attach media to product
    $result = apiRequest('PATCH', "/product/$productId", [
        'coverId' => $mediaIds[0]['mediaId'],
        'media' => $mediaIds,
    ], $config);

    if ($result['code'] === 204 || $result['code'] === 200) {
        echo "UPLOADED " . count($mediaIds) . " images\n";
        $uploaded++;
    } else {
        echo "ATTACH FAILED\n";
        $errors++;
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                    UPLOAD COMPLETE                         ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
echo "║  Products with images uploaded: " . str_pad($uploaded, 25) . "║\n";
echo "║  Products skipped (has images): " . str_pad($skipped, 25) . "║\n";
echo "║  Errors:                        " . str_pad($errors, 25) . "║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";
