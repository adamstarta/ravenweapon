<?php
/**
 * Upload Snigel product images to Shopware CHF site (Server-side version)
 *
 * Run this script ON THE SERVER after uploading images to /tmp/snigel-images/
 *
 * Steps:
 * 1. Upload images folder to server: scp -r snigel-data/images root@77.42.19.154:/tmp/snigel-images/
 * 2. Upload products.json to server: scp snigel-data/products.json root@77.42.19.154:/tmp/
 * 3. Upload this script to server: scp upload-snigel-images-v2.php root@77.42.19.154:/tmp/
 * 4. Run: docker exec -w /tmp shopware-chf php upload-snigel-images-v2.php
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

function uploadMedia($baseUrl, $token, $imagePath, $fileName) {
    // Create media ID
    $mediaId = bin2hex(random_bytes(16));
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $baseName = pathinfo($fileName, PATHINFO_FILENAME);

    // Make filename unique
    $uniqueName = $baseName . '-' . substr($mediaId, 0, 8);

    // Upload file
    $ch = curl_init($baseUrl . '/api/_action/media/' . $mediaId . '/upload?extension=' . $extension . '&fileName=' . urlencode($uniqueName));

    $fileData = file_get_contents($imagePath);

    // Determine mime type
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    ];
    $mimeType = $mimeTypes[$extension] ?? 'image/jpeg';

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

    // Debug
    echo "    Upload failed (HTTP $httpCode): $response\n";
    return null;
}

echo "═══════════════════════════════════════════════════════════\n";
echo "     UPLOAD SNIGEL PRODUCT IMAGES TO SHOPWARE              \n";
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

// Count images
$imageFiles = glob($IMAGES_DIR . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
echo "Found " . count($imageFiles) . " images in directory\n";

// Get API token
$token = getToken($API_URL);
if (!$token) {
    die("Error: Failed to get API token\n");
}
echo "Got API token\n\n";

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

// Get all Snigel products without cover images
echo "Fetching Snigel products without images...\n";
$snigelProducts = apiPost($API_URL, $token, 'search/product', [
    'limit' => 500,
    'filter' => [
        ['type' => 'equals', 'field' => 'manufacturerId', 'value' => $snigelManufacturerId],
        ['type' => 'equals', 'field' => 'coverId', 'value' => null]
    ],
    'includes' => [
        'product' => ['id', 'productNumber', 'name']
    ]
]);

$productsWithoutImages = [];
foreach ($snigelProducts['data']['data'] ?? [] as $p) {
    $sku = $p['productNumber'] ?? $p['attributes']['productNumber'] ?? '';
    $productsWithoutImages[$sku] = [
        'id' => $p['id'],
        'name' => $p['name'] ?? $p['attributes']['name'] ?? ''
    ];
}

echo "Found " . count($productsWithoutImages) . " products without cover images\n\n";

if (empty($productsWithoutImages)) {
    echo "All Snigel products already have images!\n";
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
$total = count($productsWithoutImages);
$current = 0;

foreach ($productsWithoutImages as $sku => $product) {
    $current++;

    // Extract slug from SKU (SN-featherweight-stretcher-09 -> featherweight-stretcher-09)
    $slug = preg_replace('/^SN-/i', '', $sku);
    $slug = strtolower($slug);

    // Try to find matching images
    $images = $imageMapping[$slug] ?? null;

    // Try alternative matching if not found
    if (!$images) {
        // Try partial matching
        foreach ($imageMapping as $mapSlug => $mapImages) {
            // Normalize both for comparison
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

    // Upload image
    $mediaId = uploadMedia($API_URL, $token, $imagePath, $imageFile);

    if (!$mediaId) {
        echo "  Failed to upload image\n";
        $errors++;
        continue;
    }

    // Create product-media association and set as cover
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

    // Small delay
    usleep(50000); // 50ms
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "SUMMARY:\n";
echo "  Total processed: $total\n";
echo "  Uploaded: $uploaded\n";
echo "  No matching image: $noMatch\n";
echo "  Errors: $errors\n";
echo "═══════════════════════════════════════════════════════════\n";
