<?php
/**
 * Upload RAPAX product images to Shopware
 *
 * This script:
 * 1. Reads rapax-products.json for image URLs
 * 2. Downloads real images (filters out placeholders and logos)
 * 3. Uploads to Shopware via API
 * 4. Associates images with products by matching product names
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

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
    curl_close($ch);

    $data = json_decode($response, true);
    if (isset($data['access_token'])) {
        $GLOBALS['token_data']['token'] = $data['access_token'];
        $GLOBALS['token_data']['expires_at'] = time() + ($data['expires_in'] ?? 600);
        return $data['access_token'];
    }
    return null;
}

function apiRequest($method, $endpoint, $data = null, $token = null, $config) {
    $url = $config['shopware_url'] . '/api' . $endpoint;

    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

function downloadImage($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($httpCode === 200 && $data) {
        return [
            'data' => $data,
            'contentType' => $contentType
        ];
    }
    return null;
}

function isRealImage($url) {
    // Filter out logos and placeholders
    $excludePatterns = [
        'raven-logo.png',
        'placeholder/',
        'variant.png',
        'product.png'
    ];

    foreach ($excludePatterns as $pattern) {
        if (stripos($url, $pattern) !== false) {
            return false;
        }
    }
    return true;
}

function generateUuid() {
    return sprintf('%04x%04x%04x%04x%04x%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

echo "=== RAPAX IMAGE UPLOAD SCRIPT ===\n\n";

$token = getAccessToken($config);
if (!$token) {
    die("Failed to get API token\n");
}
echo "Got API token\n\n";

// Load JSON data
$jsonPath = __DIR__ . '/rapax-data/rapax-products.json';
$jsonData = json_decode(file_get_contents($jsonPath), true);

if (!$jsonData) {
    die("Failed to load rapax-products.json\n");
}

echo "Loaded " . count($jsonData['products']) . " products from JSON\n\n";

// Get all RAPAX/CARACAL products from Shopware
echo "Fetching Shopware products...\n";

$shopwareProducts = [];

// Get ALL products and filter locally (JSON:API format handling)
$result = apiRequest('GET', '/product?limit=500', null, $token, $config);

if (isset($result['body']['data'])) {
    foreach ($result['body']['data'] as $product) {
        // Handle JSON:API format (attributes) vs regular format
        $attrs = $product['attributes'] ?? $product;
        $name = $attrs['translated']['name'] ?? $attrs['name'] ?? $product['translated']['name'] ?? $product['name'] ?? '';
        $id = $product['id'] ?? '';

        if (stripos($name, 'RAPAX') !== false || stripos($name, 'CARACAL') !== false || stripos($name, 'Lynx') !== false) {
            $shopwareProducts[strtolower($name)] = [
                'id' => $id,
                'name' => $name,
                'media' => $attrs['media'] ?? $product['media'] ?? []
            ];
        }
    }
}
echo "Found " . count($shopwareProducts) . " RAPAX/CARACAL products\n\n";

// Process each product from JSON
$uploadCount = 0;
$skipCount = 0;
$errorCount = 0;

foreach ($jsonData['products'] as $jsonProduct) {
    $productName = $jsonProduct['name'];
    $images = $jsonProduct['images'] ?? [];

    // Filter to only real images
    $realImages = array_filter($images, 'isRealImage');

    if (empty($realImages)) {
        echo "SKIP: $productName - no real images\n";
        $skipCount++;
        continue;
    }

    // Find matching Shopware product
    $shopwareProduct = null;
    $normalizedName = strtolower($productName);

    if (isset($shopwareProducts[$normalizedName])) {
        $shopwareProduct = $shopwareProducts[$normalizedName];
    } else {
        // Try partial match
        foreach ($shopwareProducts as $name => $product) {
            if (similar_text($normalizedName, $name) > strlen($normalizedName) * 0.7) {
                $shopwareProduct = $product;
                break;
            }
        }
    }

    if (!$shopwareProduct) {
        echo "NOT FOUND: $productName - not in Shopware\n";
        $errorCount++;
        continue;
    }

    // Check if product already has media
    $existingMediaCount = count($shopwareProduct['media'] ?? []);
    if ($existingMediaCount > 0) {
        echo "HAS MEDIA: $productName - already has $existingMediaCount images\n";
        $skipCount++;
        continue;
    }

    echo "\nProcessing: $productName\n";
    echo "  Found in Shopware: " . ($shopwareProduct['name'] ?? 'unknown') . "\n";
    echo "  Product ID: " . $shopwareProduct['id'] . "\n";
    echo "  Real images: " . count($realImages) . "\n";

    // Download and upload each image
    $position = 0;
    foreach ($realImages as $imageUrl) {
        echo "  Downloading: " . basename($imageUrl) . "... ";

        $imageData = downloadImage($imageUrl);
        if (!$imageData) {
            echo "FAILED\n";
            continue;
        }
        echo "OK\n";

        // Create media in Shopware
        $mediaId = generateUuid();
        $fileName = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_FILENAME);
        $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';

        // Create media entry
        $createResult = apiRequest('POST', '/media', [
            'id' => $mediaId,
        ], $token, $config);

        if ($createResult['code'] >= 300) {
            echo "  Failed to create media entry: " . json_encode($createResult['body']) . "\n";
            continue;
        }

        // Upload the actual file using _action/media endpoint
        $uploadUrl = $config['shopware_url'] . "/api/_action/media/$mediaId/upload?extension=$extension&fileName=" . urlencode($fileName);

        $ch = curl_init($uploadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $imageData['data']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: ' . ($imageData['contentType'] ?: 'image/jpeg'),
        ]);

        $uploadResponse = curl_exec($ch);
        $uploadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($uploadCode >= 300) {
            echo "  Failed to upload image: $uploadCode\n";
            continue;
        }

        echo "  Uploaded: $fileName.$extension (Media ID: $mediaId)\n";

        // Associate with product
        $productMediaId = generateUuid();
        $assocResult = apiRequest('POST', '/product-media', [
            'id' => $productMediaId,
            'productId' => $shopwareProduct['id'],
            'mediaId' => $mediaId,
            'position' => $position,
        ], $token, $config);

        if ($assocResult['code'] >= 300) {
            echo "  Failed to associate media: " . json_encode($assocResult['body']) . "\n";
            continue;
        }

        // Set as cover if first image
        if ($position === 0) {
            $coverResult = apiRequest('PATCH', '/product/' . $shopwareProduct['id'], [
                'coverId' => $productMediaId,
            ], $token, $config);

            if ($coverResult['code'] < 300) {
                echo "  Set as cover image\n";
            }
        }

        $position++;
        $uploadCount++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Uploaded: $uploadCount images\n";
echo "Skipped: $skipCount products\n";
echo "Errors: $errorCount products\n";
echo "\nRun cache clear: ssh root@77.42.19.154 \"docker exec shopware-chf bin/console cache:clear\"\n";
