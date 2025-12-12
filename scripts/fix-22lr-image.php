<?php
/**
 * Fix .22 LR RAVEN product image
 * Upload image and set as product cover
 */

$config = [
    'shopware_url' => 'http://localhost',
    'api_user' => 'admin',
    'api_password' => 'shopware',
];

$imagePath = '/tmp/22_RAVEN.png';
$productId = '019ac3a702a2730b94c384fc75eab389';

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║     FIX .22 LR RAVEN PRODUCT IMAGE                        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Check image exists
if (!file_exists($imagePath)) {
    die("ERROR: Image not found at $imagePath\n");
}
echo "✓ Image found: $imagePath\n\n";

// Get token
echo "Step 1: Authenticating...\n";
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
    die("  ERROR: Failed to authenticate!\n");
}
echo "  ✓ Authenticated\n\n";

// Step 2: Get product media folder
echo "Step 2: Getting product media folder...\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/search/media-default-folder',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'filter' => [['type' => 'equals', 'field' => 'entity', 'value' => 'product']],
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);
$defaultFolderId = $result['data'][0]['id'] ?? null;

$folderId = null;
if ($defaultFolderId) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . '/api/search/media-folder',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'filter' => [['type' => 'equals', 'field' => 'defaultFolderId', 'value' => $defaultFolderId]],
        ]),
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);
    $folderId = $result['data'][0]['id'] ?? null;
}
echo "  Media folder: " . ($folderId ?? 'default') . "\n\n";

// Step 3: Create media entity
echo "Step 3: Creating media entity...\n";
$mediaId = bin2hex(random_bytes(16));

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/media',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'id' => $mediaId,
        'mediaFolderId' => $folderId,
    ]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 204 && $httpCode !== 200) {
    die("  ERROR: Failed to create media entity (HTTP $httpCode)\n");
}
echo "  ✓ Created media: $mediaId\n\n";

// Step 4: Upload image file
echo "Step 4: Uploading image...\n";
$extension = pathinfo($imagePath, PATHINFO_EXTENSION);
$fileName = pathinfo($imagePath, PATHINFO_FILENAME);
$mimeType = $extension === 'png' ? 'image/png' : 'image/jpeg';

$url = $config['shopware_url'] . "/api/_action/media/$mediaId/upload?extension=$extension&fileName=$fileName";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: ' . $mimeType,
    ],
    CURLOPT_POSTFIELDS => file_get_contents($imagePath),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 204 && $httpCode !== 200) {
    echo "  ERROR: Failed to upload (HTTP $httpCode)\n";
    echo "  Response: $response\n\n";
    die();
}
echo "  ✓ Uploaded image\n\n";

// Step 5: Create product-media association and set as cover
echo "Step 5: Attaching to product and setting as cover...\n";
$productMediaId = bin2hex(random_bytes(16));

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . "/api/product/$productId",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'coverId' => $productMediaId,
        'media' => [
            [
                'id' => $productMediaId,
                'mediaId' => $mediaId,
                'position' => 1,
            ]
        ],
    ]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 204 && $httpCode !== 200) {
    echo "  ERROR: Failed to attach media (HTTP $httpCode)\n";
    echo "  Response: $response\n\n";
    die();
}
echo "  ✓ Attached to product as cover!\n\n";

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                       COMPLETE!                            ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "  Product ID: $productId\n";
echo "  Media ID: $mediaId\n";
echo "  Product Media ID: $productMediaId\n";
echo "\n";
