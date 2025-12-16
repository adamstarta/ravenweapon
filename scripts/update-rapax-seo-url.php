<?php
/**
 * Update SEO URL for RAPAX category to Raven-Weapons/RAPAX
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

echo "=== Update RAPAX SEO URL ===\n\n";

// The working category ID (has products at /rapax/)
$rapaxCategoryId = '1f36ebeb19da4fc6bc9cb3c3acfadafd';

// Get sales channel ID
echo "Getting sales channel...\n";
$ch = curl_init($config['shopware_url'] . '/api/sales-channel?limit=1');
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
$salesChannelId = $result['data'][0]['id'] ?? null;
echo "Sales Channel ID: $salesChannelId\n";

// Create new SEO URL
echo "\nCreating SEO URL Raven-Weapons/RAPAX/...\n";

$seoUrlId = bin2hex(random_bytes(16));

$ch = curl_init($config['shopware_url'] . '/api/seo-url');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'id' => $seoUrlId,
        'salesChannelId' => $salesChannelId,
        'foreignKey' => $rapaxCategoryId,
        'routeName' => 'frontend.navigation.page',
        'pathInfo' => '/navigation/' . $rapaxCategoryId,
        'seoPathInfo' => 'Raven-Weapons/RAPAX/',
        'isCanonical' => true,
        'isModified' => true,
    ]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 204 || $httpCode === 200) {
    echo "✓ SEO URL created\n";
} else {
    echo "Response: HTTP $httpCode\n";
    echo $response . "\n";
}

// Clear cache again
echo "\nClearing cache...\n";
$ch = curl_init($config['shopware_url'] . '/api/_action/cache');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
    ],
]);
curl_exec($ch);
curl_close($ch);
echo "Cache cleared\n";

echo "\nCheck: https://ortak.ch/Raven-Weapons/RAPAX/\n";
