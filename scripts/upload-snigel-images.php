<?php
/**
 * Upload Snigel product images to Shopware CHF site
 *
 * This script:
 * 1. Reads the local products.json with image mappings
 * 2. Gets Snigel products from Shopware
 * 3. Uploads images and assigns them to products
 */

$API_URL = 'http://77.42.19.154';
$IMAGES_DIR = __DIR__ . '/snigel-data/images/';
$PRODUCTS_JSON = __DIR__ . '/snigel-data/products.json';

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

function uploadMedia($baseUrl, $token, $imagePath, $fileName) {
    // Create media entry first
    $mediaId = bin2hex(random_bytes(16));

    $createResult = apiPost($baseUrl, $token, '_action/media/' . $mediaId . '/upload', []);

    // Upload using multipart
    $ch = curl_init($baseUrl . '/api/_action/media/' . $mediaId . '/upload?extension=' . pathinfo($fileName, PATHINFO_EXTENSION) . '&fileName=' . urlencode(pathinfo($fileName, PATHINFO_FILENAME)));

    $fileData = file_get_contents($imagePath);
    $mimeType = mime_content_type($imagePath);

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
echo "     UPLOAD SNIGEL PRODUCT IMAGES TO SHOPWARE              \n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Load products.json
if (!file_exists($PRODUCTS_JSON)) {
    die("Error: products.json not found at $PRODUCTS_JSON\n");
}

$productsData = json_decode(file_get_contents($PRODUCTS_JSON), true);
echo "Loaded " . count($productsData) . " products from JSON\n";

// Get API token
$token = getToken($API_URL);
if (!$token) {
    die("Error: Failed to get API token\n");
}
echo "Got API token\n\n";

// Get Snigel products from Shopware (manufacturer = Snigel)
echo "Fetching Snigel products from Shopware...\n";

// First find Snigel manufacturer ID
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

// Get all Snigel products
$snigelProducts = apiPost($API_URL, $token, 'search/product', [
    'limit' => 500,
    'filter' => [
        ['type' => 'equals', 'field' => 'manufacturerId', 'value' => $snigelManufacturerId]
    ],
    'includes' => [
        'product' => ['id', 'productNumber', 'name', 'coverId']
    ]
]);

$shopwareProducts = [];
foreach ($snigelProducts['data']['data'] ?? [] as $p) {
    $sku = $p['productNumber'] ?? $p['attributes']['productNumber'] ?? '';
    $shopwareProducts[$sku] = [
        'id' => $p['id'],
        'name' => $p['name'] ?? $p['attributes']['name'] ?? '',
        'coverId' => $p['coverId'] ?? $p['attributes']['coverId'] ?? null
    ];
}

echo "Found " . count($shopwareProducts) . " Snigel products in Shopware\n\n";

// Build mapping from slug to local images
$imageMapping = [];
foreach ($productsData as $product) {
    $slug = $product['slug'] ?? '';
    $localImages = $product['local_images'] ?? [];

    // Filter out icon images
    $validImages = array_filter($localImages, function($img) {
        return strpos($img, 'icon') === false && strpos($img, 'cropped') === false;
    });

    if (!empty($validImages)) {
        $imageMapping[$slug] = array_values($validImages);
    }
}

echo "Built image mapping for " . count($imageMapping) . " products\n\n";

// Match Shopware products with images and upload
$uploaded = 0;
$skipped = 0;
$errors = 0;

foreach ($shopwareProducts as $sku => $product) {
    // Skip if already has cover image
    if ($product['coverId']) {
        $skipped++;
        continue;
    }

    // Extract slug from SKU (e.g., SN-featherweight-stretcher-09 -> featherweight-stretcher-09)
    $slug = preg_replace('/^SN-/i', '', $sku);
    $slug = strtolower($slug);

    // Try to find matching images
    $images = $imageMapping[$slug] ?? null;

    if (!$images) {
        // Try alternative matching
        $slugVariants = [
            $slug,
            str_replace('-', ' ', $slug),
            str_replace('_', '-', $slug),
        ];

        foreach ($imageMapping as $mapSlug => $mapImages) {
            foreach ($slugVariants as $variant) {
                if (stripos($mapSlug, $variant) !== false || stripos($variant, $mapSlug) !== false) {
                    $images = $mapImages;
                    break 2;
                }
            }
        }
    }

    if (!$images) {
        echo "  No images found for: $sku ({$product['name']})\n";
        $errors++;
        continue;
    }

    // Find first valid image file
    $imagePath = null;
    foreach ($images as $imgFile) {
        $testPath = $IMAGES_DIR . $imgFile;
        if (file_exists($testPath)) {
            $imagePath = $testPath;
            break;
        }
    }

    if (!$imagePath) {
        echo "  Image file not found for: $sku\n";
        $errors++;
        continue;
    }

    echo "Uploading image for: {$product['name']} ($sku)\n";
    echo "  Image: " . basename($imagePath) . "\n";

    // Upload image
    $mediaId = uploadMedia($API_URL, $token, $imagePath, basename($imagePath));

    if ($mediaId) {
        // Create product-media association
        $productMediaId = bin2hex(random_bytes(16));
        $assocResult = apiPost($API_URL, $token, 'product-media', [
            'id' => $productMediaId,
            'productId' => $product['id'],
            'mediaId' => $mediaId,
            'position' => 1
        ]);

        // Set as cover
        $updateResult = apiPatch($API_URL, $token, 'product/' . $product['id'], [
            'coverId' => $productMediaId
        ]);

        if ($updateResult['code'] >= 200 && $updateResult['code'] < 300) {
            echo "  ✓ Uploaded and set as cover\n";
            $uploaded++;
        } else {
            echo "  ✗ Failed to set cover: " . json_encode($updateResult['data']) . "\n";
            $errors++;
        }
    } else {
        echo "  ✗ Failed to upload image\n";
        $errors++;
    }

    // Small delay to avoid rate limiting
    usleep(100000); // 100ms
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "SUMMARY:\n";
echo "  Uploaded: $uploaded\n";
echo "  Skipped (already has image): $skipped\n";
echo "  Errors: $errors\n";
echo "═══════════════════════════════════════════════════════════\n";
