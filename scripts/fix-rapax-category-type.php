<?php
/**
 * Fix RAPAX category type to show products instead of CMS page
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

function getAccessToken($config) {
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
    return $data['access_token'];
}

function apiRequest($config, $token, $method, $endpoint, $data = null) {
    $ch = curl_init($config['shopware_url'] . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_CUSTOMREQUEST => $method,
    ]);
    if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

$token = getAccessToken($config);
echo "=== Fix RAPAX Category Type ===\n\n";

$rapaxCategoryId = '1f36ebeb19da4fc6bc9cb3c3acfadafd';

// Get current category details
echo "Getting current category settings...\n";
$result = apiRequest($config, $token, 'GET', "category/$rapaxCategoryId");

if (!empty($result['body']['data'])) {
    $cat = $result['body']['data'];
    echo "  Name: " . ($cat['translated']['name'] ?? $cat['name'] ?? 'Unknown') . "\n";
    echo "  Type: " . ($cat['type'] ?? 'Unknown') . "\n";
    echo "  CMS Page ID: " . ($cat['cmsPageId'] ?? 'None') . "\n";
    echo "  Product Assignment Type: " . ($cat['productAssignmentType'] ?? 'Unknown') . "\n";
    echo "  Display Nested Products: " . ($cat['displayNestedProducts'] ?? 'Unknown') . "\n";
}

// Update category to show products
echo "\nUpdating category to show products...\n";

$result = apiRequest($config, $token, 'PATCH', "category/$rapaxCategoryId", [
    'type' => 'page',  // page type with products
    'cmsPageId' => null,  // Remove custom CMS page to use default listing
    'productAssignmentType' => 'product',
    'displayNestedProducts' => true,
    'active' => true,
]);

if ($result['code'] === 204 || $result['code'] === 200) {
    echo "✓ Category updated successfully\n";
} else {
    echo "Error: HTTP {$result['code']}\n";
    print_r($result['body']);
}

// Verify the update
echo "\nVerifying...\n";
$result = apiRequest($config, $token, 'GET', "category/$rapaxCategoryId");
if (!empty($result['body']['data'])) {
    $cat = $result['body']['data'];
    echo "  Type: " . ($cat['type'] ?? 'Unknown') . "\n";
    echo "  CMS Page ID: " . ($cat['cmsPageId'] ?? 'None (will use default)') . "\n";
}

echo "\nCheck: https://ortak.ch/Raven-Weapons/RAPAX/\n";
