<?php
/**
 * Upload Category Images for Homepage
 *
 * Categories to fix:
 * - Raven Weapons (use .22 LR RAVEN image)
 * - Raven Caliber Kit (use caliber kit image)
 * - Waffenzubehör (use scope image)
 * - Alle Produkte (use hero/general image)
 * - Snigel (use Snigel product image)
 */

$config = [
    'shopware_url' => 'http://localhost',
    'api_user' => 'admin',
    'api_password' => 'shopware',
    'image_dir' => '/tmp/category-images',
];

// Category -> Image mapping
$categoryImages = [
    'Raven Weapons' => '22_RAVEN.png',
    'Raven Caliber Kit' => '223_CALIBER_KIT.png',
    'Waffenzubehör' => 'VENGEANCE_SCOPE.png',
    'Alle Produkte' => '300_AAC_RAVEN.png',
    'Snigel' => 'snigel-product.jpg',
];

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║     UPLOAD CATEGORY IMAGES FOR HOMEPAGE                   ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

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
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => $config['api_user'],
            'password' => $config['api_password'],
        ]),
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

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

function uploadMediaFile($filePath, $mediaId, $config) {
    $token = getAccessToken($config);
    if (!$token) return false;

    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];
    $mimeType = $mimeTypes[strtolower($extension)] ?? 'image/jpeg';

    $url = $config['shopware_url'] . "/api/_action/media/$mediaId/upload?extension=$extension&fileName=" . pathinfo($filePath, PATHINFO_FILENAME);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: ' . $mimeType,
        ],
        CURLOPT_POSTFIELDS => file_get_contents($filePath),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 204 || $httpCode === 200;
}

// Step 1: Authenticate
echo "Step 1: Authenticating...\n";
$token = getAccessToken($config);
if (!$token) {
    die("  ERROR: Failed to authenticate!\n");
}
echo "  ✓ Authenticated\n\n";

// Step 2: Get categories
echo "Step 2: Getting categories...\n";
$result = apiRequest('POST', '/search/category', [
    'limit' => 100,
    'associations' => ['media' => []],
], $config);

$categories = $result['body']['data'] ?? [];
echo "  Found " . count($categories) . " categories\n\n";

// Build category lookup by name
$categoryByName = [];
foreach ($categories as $cat) {
    $name = $cat['translated']['name'] ?? $cat['name'] ?? '';
    $categoryByName[$name] = $cat;
}

// Step 3: Get or create media folder for categories
echo "Step 3: Getting/creating media folder...\n";
$result = apiRequest('POST', '/search/media-folder', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Category Media']],
], $config);

$folderId = $result['body']['data'][0]['id'] ?? null;

if (!$folderId) {
    // Get default folder configuration
    $result = apiRequest('POST', '/search/media-default-folder', [
        'filter' => [['type' => 'equals', 'field' => 'entity', 'value' => 'category']],
    ], $config);

    $defaultFolderId = $result['body']['data'][0]['id'] ?? null;

    if ($defaultFolderId) {
        // Get the actual folder
        $result = apiRequest('POST', '/search/media-folder', [
            'filter' => [['type' => 'equals', 'field' => 'defaultFolderId', 'value' => $defaultFolderId]],
        ], $config);
        $folderId = $result['body']['data'][0]['id'] ?? null;
    }
}

if (!$folderId) {
    echo "  ! No category media folder found, using default\n";
} else {
    echo "  ✓ Media folder: $folderId\n";
}
echo "\n";

// Step 4: Check available images
echo "Step 4: Checking available images...\n";
$imageDir = $config['image_dir'];
if (!is_dir($imageDir)) {
    echo "  Image directory not found: $imageDir\n";
    echo "  Creating directory...\n";
    mkdir($imageDir, 0755, true);
}

$availableImages = [];
if (is_dir($imageDir)) {
    foreach (glob("$imageDir/*") as $file) {
        $availableImages[basename($file)] = $file;
    }
}
echo "  Found " . count($availableImages) . " images in $imageDir\n\n";

// Step 5: Process each category
echo "Step 5: Processing categories...\n\n";

foreach ($categoryImages as $categoryName => $imageName) {
    echo "Category: $categoryName\n";

    // Find category
    $category = $categoryByName[$categoryName] ?? null;
    if (!$category) {
        echo "  ✗ Category not found!\n\n";
        continue;
    }

    $categoryId = $category['id'];
    echo "  ID: $categoryId\n";

    // Check if already has media
    if (!empty($category['mediaId'])) {
        echo "  ! Already has media: {$category['mediaId']}\n\n";
        continue;
    }

    // Find image file
    $imagePath = $availableImages[$imageName] ?? null;
    if (!$imagePath || !file_exists($imagePath)) {
        echo "  ✗ Image not found: $imageName\n\n";
        continue;
    }

    echo "  Image: $imagePath\n";

    // Create media entity
    $mediaId = bin2hex(random_bytes(16));
    $createResult = apiRequest('POST', '/media', [
        'id' => $mediaId,
        'mediaFolderId' => $folderId,
    ], $config);

    if ($createResult['code'] !== 204 && $createResult['code'] !== 200) {
        echo "  ✗ Failed to create media entity (HTTP {$createResult['code']})\n\n";
        continue;
    }

    echo "  Created media: $mediaId\n";

    // Upload file
    $uploadSuccess = uploadMediaFile($imagePath, $mediaId, $config);
    if (!$uploadSuccess) {
        echo "  ✗ Failed to upload image\n\n";
        continue;
    }

    echo "  ✓ Uploaded image\n";

    // Assign media to category
    $updateResult = apiRequest('PATCH', "/category/$categoryId", [
        'mediaId' => $mediaId,
    ], $config);

    if ($updateResult['code'] === 204 || $updateResult['code'] === 200) {
        echo "  ✓ Assigned to category!\n\n";
    } else {
        echo "  ✗ Failed to assign (HTTP {$updateResult['code']})\n\n";
    }
}

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                       COMPLETE                             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";
