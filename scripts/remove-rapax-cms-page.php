<?php
/**
 * Remove CMS page from RAPAX category to show products
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

// Get OAuth token
$ch = curl_init($config['shopware_url'] . '/api/oauth/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'grant_type' => 'password',
        'client_id' => 'administration',
        'username' => $config['api_user'],
        'password' => $config['api_password'],
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
]);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);
$token = $data['access_token'];

echo "=== Removing CMS Page from RAPAX Category ===\n\n";

$rapaxCategoryId = '1f36ebeb19da4fc6bc9cb3c3acfadafd';

// Update category to remove CMS page
$ch = curl_init($config['shopware_url'] . "/api/category/$rapaxCategoryId");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'cmsPageId' => null,  // Remove CMS page
        'type' => 'page',     // Keep as page type
        'displayNestedProducts' => true,
        'productAssignmentType' => 'product',
        'active' => true,
        'visible' => true,
    ]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 204) {
    echo "✓ CMS page removed from RAPAX category\n";
} else {
    echo "Error: HTTP $httpCode\n";
    echo $response . "\n";
}

// Verify the change
echo "\nVerifying...\n";
$ch = curl_init($config['shopware_url'] . "/api/category/$rapaxCategoryId");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
$cmsPageId = $result['data']['attributes']['cmsPageId'] ?? 'null';
echo "CMS Page ID: $cmsPageId\n";

echo "\nCheck: https://ortak.ch/Raven-Weapons/RAPAX/\n";
