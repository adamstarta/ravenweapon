<?php
/**
 * Upload Snigel product images to Shopware CHF site (v4 - Re-upload to existing media or create new)
 *
 * This version handles products that have cover IDs but no actual media files uploaded.
 */

$API_URL = 'http://localhost';
$IMAGES_DIR = '/tmp/snigel-images/';
$PRODUCTS_JSON = '/tmp/products.json';

// API Helper Functions
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

function apiDelete($baseUrl, $token, $endpoint) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

function uploadMediaFile($baseUrl, $token, $mediaId, $imagePath, $fileName) {
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $baseName = pathinfo($fileName, PATHINFO_FILENAME);
    $uniqueName = $baseName . '-' . substr(bin2hex(random_bytes(4)), 0, 8);

    $fileData = file_get_contents($imagePath);

    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    ];
    $mimeType = $mimeTypes[$extension] ?? 'image/jpeg';

    $ch = curl_init($baseUrl . '/api/_action/media/' . $mediaId . '/upload?extension=' . $extension . '&fileName=' . urlencode($uniqueName));
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: ' . $mimeType
        ],
        CURLOPT_POSTFIELDS => $fileData
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}

function createAndUploadMedia($baseUrl, $token, $imagePath, $fileName, $mediaFolderId = null) {
    $mediaId = bin2hex(random_bytes(16));
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $baseName = pathinfo($fileName, PATHINFO_FILENAME);
    $uniqueName = $baseName . '-' . substr($mediaId, 0, 8);

    // Create media entry
    $createData = ['id' => $mediaId];
    if ($mediaFolderId) {
        $createData['mediaFolderId'] = $mediaFolderId;
    }

    $createResult = apiPost($baseUrl, $token, 'media', $createData);
    if ($createResult['code'] >= 300 && $createResult['code'] != 204) {
        return null;
    }

    // Upload file
    $fileData = file_get_contents($imagePath);
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    ];
    $mimeType = $mimeTypes[$extension] ?? 'image/jpeg';

    $ch = curl_init($baseUrl . '/api/_action/media/' . $mediaId . '/upload?extension=' . $extension . '&fileName=' . urlencode($uniqueName));
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: ' . $mimeType
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
echo "     UPLOAD SNIGEL PRODUCT IMAGES TO SHOPWARE (v4)        \n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Check paths
if (!is_dir($IMAGES_DIR)) {
    die("Error: Images directory not found at $IMAGES_DIR\n");
}

if (!file_exists($PRODUCTS_JSON)) {
    die("Error: products.json not found at $PRODUCTS_JSON\n");
}

// Load products.json
$productsData = json_decode(file_get_contents($PRODUCTS_JSON), true);
echo "Loaded " . count($productsData) . " products from JSON\n";

// Get API token
$token = getToken($API_URL);
if (!$token) {
    die("Error: Failed to get API token\n");
}
echo "Got API token\n\n";

// Get product media folder
$folderResult = apiPost($API_URL, $token, 'search/media-default-folder', [
    'filter' => [
        ['type' => 'equals', 'field' => 'entity', 'value' => 'product']
    ],
    'associations' => ['folder' => []]
]);

$mediaFolderId = null;
if (!empty($folderResult['data']['data'])) {
    $defaultFolder = $folderResult['data']['data'][0];
    $mediaFolderId = $defaultFolder['folder']['id'] ?? $defaultFolder['attributes']['folder']['id'] ?? null;
    echo "Product media folder ID: $mediaFolderId\n";
}

// Find Snigel manufacturer ID
echo "Finding Snigel manufacturer...\n";
$manufacturers = apiPost($API_URL, $token, 'search/product-manufacturer', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'Snigel']
    ]
]);

$snigelManufacturerId = null;
foreach ($manufacturers['data']['data'] ?? [] as $m) {
    $name = $m['name'] ?? $m['attributes']['name'] ?? '';
    if (stripos($name, 'snigel') !== false) {
        $snigelManufacturerId = $m['id'];
        echo "Found Snigel manufacturer ID: $snigelManufacturerId\n";
        break;
    }
}

if (!$snigelManufacturerId) {
    die("Error: Snigel manufacturer not found\n");
}

// Get ALL Snigel products with cover media info
echo "Fetching ALL Snigel products with media info...\n";
$snigelProducts = apiPost($API_URL, $token, 'search/product', [
    'limit' => 500,
    'filter' => [
        ['type' => 'equals', 'field' => 'manufacturerId', 'value' => $snigelManufacturerId]
    ],
    'associations' => [
        'cover' => [
            'associations' => ['media' => []]
        ]
    ]
]);

$productsToProcess = [];
foreach ($snigelProducts['data']['data'] ?? [] as $p) {
    $sku = $p['productNumber'] ?? $p['attributes']['productNumber'] ?? '';
    $name = $p['name'] ?? $p['attributes']['name'] ?? '';
    $cover = $p['cover'] ?? $p['attributes']['cover'] ?? null;
    $media = $cover['media'] ?? $cover['attributes']['media'] ?? null;
    $mediaUrl = $media['url'] ?? $media['attributes']['url'] ?? null;
    $mediaPath = $media['path'] ?? $media['attributes']['path'] ?? null;

    // Check if media file is actually uploaded (has URL or path)
    $hasMediaFile = !empty($mediaUrl) || !empty($mediaPath);

    if (!$hasMediaFile) {
        $productsToProcess[$sku] = [
            'id' => $p['id'],
            'name' => $name,
            'coverId' => $p['coverId'] ?? $p['attributes']['coverId'] ?? null,
            'mediaId' => $cover['mediaId'] ?? $cover['attributes']['mediaId'] ?? null
        ];
    }
}

echo "Found " . count($productsToProcess) . " products without actual media files\n\n";

if (empty($productsToProcess)) {
    echo "All Snigel products have proper images!\n";
    exit(0);
}

// Build mapping from slug to local images
$imageMapping = [];
foreach ($productsData as $product) {
    $slug = $product['slug'] ?? '';
    $localImages = $product['local_images'] ?? [];

    // Filter out icon images
    $validImages = array_filter($localImages, function($img) {
        return strpos($img, 'icon') === false &&
               strpos($img, 'cropped') === false &&
               !empty($img);
    });

    if (!empty($validImages)) {
        $imageMapping[strtolower($slug)] = array_values($validImages);
    }
}

echo "Built image mapping for " . count($imageMapping) . " products\n\n";

// Process products
$uploaded = 0;
$noMatch = 0;
$errors = 0;
$total = count($productsToProcess);
$current = 0;

foreach ($productsToProcess as $sku => $product) {
    $current++;

    // Extract slug from SKU
    $slug = preg_replace('/^SN-/i', '', $sku);
    $slug = strtolower($slug);

    // Try to find matching images
    $images = $imageMapping[$slug] ?? null;

    // Try alternative matching
    if (!$images) {
        foreach ($imageMapping as $mapSlug => $mapImages) {
            $normalizedSlug = str_replace(['-', '_'], '', $slug);
            $normalizedMapSlug = str_replace(['-', '_'], '', $mapSlug);

            if ($normalizedSlug === $normalizedMapSlug ||
                strpos($normalizedMapSlug, $normalizedSlug) !== false ||
                strpos($normalizedSlug, $normalizedMapSlug) !== false) {
                $images = $mapImages;
                break;
            }
        }
    }

    if (!$images) {
        echo "[$current/$total] No images for: $sku\n";
        $noMatch++;
        continue;
    }

    // Find first valid image file
    $imagePath = null;
    $imageFile = null;
    foreach ($images as $imgFile) {
        $testPath = $IMAGES_DIR . $imgFile;
        if (file_exists($testPath) && filesize($testPath) > 0) {
            $imagePath = $testPath;
            $imageFile = $imgFile;
            break;
        }
    }

    if (!$imagePath) {
        echo "[$current/$total] Image file not found for: $sku\n";
        $noMatch++;
        continue;
    }

    echo "[$current/$total] Uploading: $sku -> $imageFile\n";

    // Strategy: Create new media and update product cover
    // (Old media without files can be left orphaned - Shopware cleanup can handle them)

    $mediaId = createAndUploadMedia($API_URL, $token, $imagePath, $imageFile, $mediaFolderId);

    if (!$mediaId) {
        echo "  Failed to upload media\n";
        $errors++;
        continue;
    }

    // Create new product-media association and set as cover
    $productMediaId = bin2hex(random_bytes(16));

    $updateResult = apiPatch($API_URL, $token, 'product/' . $product['id'], [
        'media' => [
            [
                'id' => $productMediaId,
                'mediaId' => $mediaId,
                'position' => 1
            ]
        ],
        'coverId' => $productMediaId
    ]);

    if ($updateResult['code'] >= 200 && $updateResult['code'] < 300) {
        echo "  OK\n";
        $uploaded++;
    } else {
        $error = $updateResult['data']['errors'][0]['detail'] ?? json_encode($updateResult['data']);
        echo "  Failed: " . substr($error, 0, 100) . "\n";
        $errors++;
    }

    usleep(100000); // 100ms delay
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "SUMMARY:\n";
echo "  Total processed: $total\n";
echo "  Uploaded: $uploaded\n";
echo "  No matching image: $noMatch\n";
echo "  Errors: $errors\n";
echo "═══════════════════════════════════════════════════════════\n";
