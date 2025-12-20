<?php
/**
 * Upload images for the 2 missing Snigel products to Shopware
 */

$API_URL = 'https://ortak.ch';
$TEMP_DIR = __DIR__ . '/snigel-import-data/temp-images/';
$DATA_FILE = __DIR__ . '/snigel-import-data/missing-products-scraped.json';

// Create temp dir
if (!is_dir($TEMP_DIR)) {
    mkdir($TEMP_DIR, 0777, true);
}

// API Helper Functions
function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'client_credentials',
            'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
            'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
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

function downloadImage($url, $localPath) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0'
    ]);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && strlen($data) > 1000) {
        file_put_contents($localPath, $data);
        return true;
    }
    return false;
}

function uploadMedia($baseUrl, $token, $imagePath, $fileName, $folderId = null) {
    // Generate media ID
    $mediaId = bin2hex(random_bytes(16));

    // Create media entry
    $createData = ['id' => $mediaId];
    if ($folderId) {
        $createData['mediaFolderId'] = $folderId;
    }

    $createResult = apiPost($baseUrl, $token, 'media', $createData);
    if ($createResult['code'] != 204 && $createResult['code'] != 200) {
        return null;
    }

    // Upload the file
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $baseName = pathinfo($fileName, PATHINFO_FILENAME);
    $uploadUrl = $baseUrl . '/api/_action/media/' . $mediaId . '/upload?extension=' . $extension . '&fileName=' . urlencode($baseName);

    $fileData = file_get_contents($imagePath);
    $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';

    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: ' . $mimeType,
            'Content-Length: ' . strlen($fileData)
        ],
        CURLOPT_POSTFIELDS => $fileData
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return $mediaId;
    }
    return null;
}

echo "═══════════════════════════════════════════════════════════\n";
echo "     UPLOAD IMAGES FOR MISSING SNIGEL PRODUCTS             \n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Load scraped data
if (!file_exists($DATA_FILE)) {
    die("Error: Scraped data not found at $DATA_FILE\n");
}

$products = json_decode(file_get_contents($DATA_FILE), true);
echo "Loaded " . count($products) . " products\n";

// Get API token
$token = getToken($API_URL);
if (!$token) {
    die("Error: Failed to get API token\n");
}
echo "✓ Got API token\n\n";

// Get product media folder
$folderResult = apiPost($API_URL, $token, 'search/media-default-folder', [
    'limit' => 10,
    'filter' => [['type' => 'equals', 'field' => 'entity', 'value' => 'product']],
    'associations' => ['folder' => []]
]);

$mediaFolderId = null;
if (!empty($folderResult['data']['data'])) {
    $mediaFolderId = $folderResult['data']['data'][0]['folder']['id'] ?? null;
    echo "✓ Found media folder\n\n";
}

// Process each product
foreach ($products as $product) {
    echo "═══════════════════════════════════════════════════════════\n";
    echo "  " . $product['name'] . "\n";
    echo "═══════════════════════════════════════════════════════════\n";

    if (empty($product['images'])) {
        echo "  ⚠ No images to upload\n";
        continue;
    }

    // Find product in Shopware
    $searchResult = apiPost($API_URL, $token, 'search/product', [
        'limit' => 1,
        'filter' => [['type' => 'contains', 'field' => 'name', 'value' => $product['name']]]
    ]);

    if (empty($searchResult['data']['data'])) {
        echo "  ✗ Product not found in Shopware\n";
        continue;
    }

    $productId = $searchResult['data']['data'][0]['id'];
    echo "  ✓ Found product: " . substr($productId, 0, 8) . "...\n";

    // Upload each image
    $mediaIds = [];
    $position = 0;

    foreach ($product['images'] as $imageUrl) {
        $position++;
        $fileName = basename(parse_url($imageUrl, PHP_URL_PATH));
        $localPath = $TEMP_DIR . $fileName;

        echo "\n  [$position/" . count($product['images']) . "] $fileName\n";

        // Download
        echo "    Downloading...";
        if (!downloadImage($imageUrl, $localPath)) {
            echo " ✗ Failed\n";
            continue;
        }
        echo " ✓\n";

        // Upload
        echo "    Uploading...";
        $mediaId = uploadMedia($API_URL, $token, $localPath, $fileName, $mediaFolderId);

        if ($mediaId) {
            echo " ✓\n";
            $mediaIds[] = ['mediaId' => $mediaId, 'position' => $position - 1];
        } else {
            echo " ✗ Failed\n";
        }

        // Cleanup
        @unlink($localPath);
        usleep(300000); // 300ms delay
    }

    // Associate media with product
    if (!empty($mediaIds)) {
        echo "\n  Associating " . count($mediaIds) . " images with product...\n";

        // Create product_media entries
        $productMedia = [];
        foreach ($mediaIds as $idx => $m) {
            $productMedia[] = [
                'mediaId' => $m['mediaId'],
                'position' => $idx
            ];
        }

        // Update product
        $updateResult = apiPatch($API_URL, $token, 'product/' . $productId, [
            'media' => $productMedia,
            'coverId' => $mediaIds[0]['mediaId']
        ]);

        if ($updateResult['code'] == 204 || $updateResult['code'] == 200) {
            echo "  ✓ Associated " . count($mediaIds) . " images!\n";
        } else {
            echo "  ✗ Failed to associate: " . $updateResult['code'] . "\n";
            if (!empty($updateResult['data']['errors'])) {
                foreach ($updateResult['data']['errors'] as $err) {
                    echo "    " . ($err['detail'] ?? $err['title'] ?? json_encode($err)) . "\n";
                }
            }
        }
    }
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "                        COMPLETE!                          \n";
echo "═══════════════════════════════════════════════════════════\n\n";
