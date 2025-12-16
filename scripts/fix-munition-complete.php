<?php
/**
 * Fix Munition Category - Complete Fix
 * 1. Fix SEO URL for Munition category
 * 2. Check and fix product images
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
    'images_dir' => __DIR__ . '/ammunition-data/images',
];

echo "\n";
echo "======================================================================\n";
echo "     FIX MUNITION CATEGORY - COMPLETE\n";
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

    if ($httpCode !== 200) {
        return null;
    }

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

function uploadMediaFromUrl($imageUrl, $productName, $config) {
    $token = getAccessToken($config);
    if (!$token) return null;

    // Create media entry
    $mediaId = bin2hex(random_bytes(16));

    $result = apiRequest('POST', '/media', [
        'id' => $mediaId,
    ], $config);

    if ($result['code'] !== 204 && $result['code'] !== 200) {
        echo "    Failed to create media entry\n";
        return null;
    }

    // Download image from URL
    $imageData = @file_get_contents($imageUrl);
    if (!$imageData) {
        echo "    Failed to download image from URL\n";
        return null;
    }

    // Determine extension
    $ext = 'jpg';
    if (strpos($imageUrl, '.png') !== false) $ext = 'png';
    if (strpos($imageUrl, '.webp') !== false) $ext = 'webp';

    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];
    $mimeType = $mimeTypes[$ext] ?? 'image/jpeg';

    // Generate safe filename
    $fileName = preg_replace('/[^a-zA-Z0-9-]/', '-', substr($productName, 0, 50));

    // Upload the file
    $ch = curl_init();
    $url = $config['shopware_url'] . "/api/_action/media/$mediaId/upload?extension=$ext&fileName=" . urlencode($fileName);

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: ' . $mimeType,
        ],
        CURLOPT_POSTFIELDS => $imageData,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 204 || $httpCode === 200) {
        return $mediaId;
    }

    echo "    Failed to upload media: HTTP $httpCode\n";
    return null;
}

// Step 1: Authenticate
echo "Step 1: Authenticating...\n";
$token = getAccessToken($config);
if (!$token) {
    die("  ERROR: Failed to authenticate!\n");
}
echo "  OK\n\n";

// Step 2: Get ammunition products
echo "Step 2: Finding ammunition products...\n";

$result = apiRequest('POST', '/search/product', [
    'filter' => [
        ['type' => 'contains', 'field' => 'productNumber', 'value' => 'AMMO-']
    ],
    'associations' => [
        'cover' => [],
        'media' => []
    ]
], $config);

$products = $result['body']['data'] ?? [];
echo "  Found " . count($products) . " ammunition products\n\n";

// Step 3: Check and fix product images
echo "Step 3: Checking product images...\n\n";

// Load scraped data to get image URLs
$scrapedDataFile = __DIR__ . '/ammunition-data/ammunition-products.json';
$scrapedProducts = [];
if (file_exists($scrapedDataFile)) {
    $data = json_decode(file_get_contents($scrapedDataFile), true);
    foreach ($data['products'] ?? [] as $p) {
        $scrapedProducts[$p['name']] = $p;
    }
}

foreach ($products as $product) {
    $productId = $product['id'];
    $productName = $product['name'];
    $coverId = $product['coverId'] ?? null;

    echo "  Product: $productName\n";
    echo "    Product ID: $productId\n";
    echo "    Cover ID: " . ($coverId ?: 'NONE') . "\n";

    // Check if cover media exists and has a valid URL
    $hasValidCover = false;
    if ($coverId) {
        $mediaResult = apiRequest('GET', "/media/$coverId", null, $config);
        if ($mediaResult['code'] === 200 && !empty($mediaResult['body']['data']['url'])) {
            $hasValidCover = true;
            echo "    Cover URL: " . $mediaResult['body']['data']['url'] . "\n";
        }
    }

    if (!$hasValidCover) {
        echo "    Status: NO VALID IMAGE - uploading...\n";

        // Find image URL from scraped data
        $scrapedProduct = $scrapedProducts[$productName] ?? null;
        $imageUrl = $scrapedProduct['images'][0] ?? null;

        if ($imageUrl) {
            echo "    Source URL: $imageUrl\n";

            $mediaId = uploadMediaFromUrl($imageUrl, $productName, $config);

            if ($mediaId) {
                // Create product-media association
                $assocResult = apiRequest('POST', '/product-media', [
                    'productId' => $productId,
                    'mediaId' => $mediaId,
                    'position' => 0,
                ], $config);

                // Update product cover
                $updateResult = apiRequest('PATCH', "/product/$productId", [
                    'coverId' => $mediaId,
                ], $config);

                if ($updateResult['code'] === 204 || $updateResult['code'] === 200) {
                    echo "    SUCCESS: Image uploaded and set as cover\n";
                } else {
                    echo "    PARTIAL: Image uploaded but cover update failed\n";
                }
            } else {
                echo "    FAILED: Could not upload image\n";
            }
        } else {
            echo "    FAILED: No source image URL found\n";
        }
    } else {
        echo "    Status: OK (has valid image)\n";
    }

    echo "\n";
}

// Step 4: Fix SEO URL via database-like approach
echo "Step 4: Triggering SEO URL index...\n";

// Try to trigger SEO URL generation via API
$result = apiRequest('POST', '/_action/indexing', [
    'skip' => []
], $config);

if ($result['code'] === 200 || $result['code'] === 204) {
    echo "  Indexing triggered successfully\n";
} else {
    echo "  Note: Manual indexing may be required\n";
}

echo "\n======================================================================\n";
echo "     COMPLETE!\n";
echo "======================================================================\n";
echo "\nAccess the category via:\n";
echo "  https://ortak.ch/navigation/2f40311624aea6de289c770f0bfd0ff9\n\n";
echo "If /Munition/ still doesn't work, the SEO URL needs to be fixed in Admin:\n";
echo "  1. Go to https://ortak.ch/admin\n";
echo "  2. Navigate to Catalogues > Categories > Munition\n";
echo "  3. Check the SEO URLs tab and ensure 'Munition' is set\n\n";
