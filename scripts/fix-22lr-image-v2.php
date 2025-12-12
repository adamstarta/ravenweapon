<?php
/**
 * Fix .22 LR RAVEN product image
 * Find existing media and set as product cover
 */

$config = [
    'shopware_url' => 'http://localhost',
    'api_user' => 'admin',
    'api_password' => 'shopware',
];

$productId = '019ac3a702a2730b94c384fc75eab389';

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║     FIX .22 LR RAVEN PRODUCT IMAGE (v2)                   ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

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

// Step 2: Find existing 22_RAVEN media
echo "Step 2: Finding existing 22_RAVEN media...\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/search/media',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'filter' => [['type' => 'contains', 'field' => 'fileName', 'value' => '22_RAVEN']],
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);

$media = $result['data'] ?? [];
echo "  Found " . count($media) . " matching media:\n";
foreach ($media as $m) {
    echo "    - ID: {$m['id']}\n";
    echo "      Filename: " . ($m['fileName'] ?? 'N/A') . "\n";
    echo "      URL: " . ($m['url'] ?? 'N/A') . "\n";
}

if (empty($media)) {
    die("\n  ERROR: No 22_RAVEN media found!\n");
}

$mediaId = $media[0]['id'];
echo "\n  Using media ID: $mediaId\n\n";

// Step 3: Attach to product and set as cover
echo "Step 3: Attaching to product and setting as cover...\n";
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
