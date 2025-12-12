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

// Category IDs to clear (from API query)
$categoriesToClear = [
    'Raven Weapons' => 'a61f19c9cb4b11f0b4074aca3d279c31',
    'Raven Caliber Kit' => 'a61f1ec3cb4b11f0b4074aca3d279c31',
    'Waffenzubehör' => '019adeff65f97225927586968691dc02',
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

// Step 2: Remove images from each category
echo "Step 2: Removing images from categories...\n\n";

foreach ($categoriesToClear as $name => $categoryId) {
    echo "Category: $name\n";
    echo "  ID: $categoryId\n";

    // Remove the media association by setting mediaId to null
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . "/api/category/$categoryId",
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
        echo "  ✗ Failed (HTTP $httpCode)\n";
        echo "  Response: $response\n\n";
    }
}

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                       COMPLETE!                            ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";
