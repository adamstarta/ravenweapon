<?php
/**
 * Remove category header images from:
 * - Raven Weapons
 * - Raven Caliber Kit
 * - Waffenzubehör
 */

$config = [
    'shopware_url' => 'http://localhost',
    'api_user' => 'admin',
    'api_password' => 'shopware',
];

$categoriesToClear = [
    'Raven Weapons',
    'Raven Caliber Kit',
    'Waffenzubehör',
];

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║     REMOVE CATEGORY HEADER IMAGES                         ║\n";
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

// Step 2: Get categories
echo "Step 2: Getting categories...\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/search/category',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'limit' => 100,
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);
$categories = $result['data'] ?? [];
echo "  Found " . count($categories) . " categories\n\n";

// Step 3: Find and clear media from target categories
echo "Step 3: Removing images from categories...\n\n";

foreach ($categories as $cat) {
    $name = $cat['translated']['name'] ?? $cat['name'] ?? '';

    if (in_array($name, $categoriesToClear)) {
        echo "Category: $name\n";
        echo "  ID: {$cat['id']}\n";
        echo "  Current mediaId: " . ($cat['mediaId'] ?? 'NONE') . "\n";

        if (!empty($cat['mediaId'])) {
            // Remove the media association
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $config['shopware_url'] . "/api/category/{$cat['id']}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'PATCH',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'mediaId' => null,
                ]),
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 204 || $httpCode === 200) {
                echo "  ✓ Removed image!\n\n";
            } else {
                echo "  ✗ Failed (HTTP $httpCode)\n\n";
            }
        } else {
            echo "  - No image to remove\n\n";
        }
    }
}

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                       COMPLETE!                            ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";
