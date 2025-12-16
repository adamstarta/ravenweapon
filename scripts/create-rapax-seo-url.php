<?php
/**
 * Create SEO URL for RAPAX category: Raven-Weapons/RAPAX/
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

echo "=== Creating SEO URL for RAPAX Category ===\n\n";

$rapaxId = '1f36ebeb19da4fc6bc9cb3c3acfadafd';

// Get sales channel ID
echo "1. Getting sales channel...\n";

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
echo "   Sales Channel ID: $salesChannelId\n";

// Check existing SEO URLs for RAPAX
echo "\n2. Checking existing SEO URLs for RAPAX...\n";

$ch = curl_init($config['shopware_url'] . '/api/search/seo-url');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'filter' => [
            ['type' => 'equals', 'field' => 'foreignKey', 'value' => $rapaxId]
        ],
        'limit' => 50
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if (!empty($result['data'])) {
    echo "   Found existing SEO URLs:\n";
    foreach ($result['data'] as $seo) {
        $canonical = $seo['isCanonical'] ? ' [CANONICAL]' : '';
        echo "   - {$seo['seoPathInfo']}$canonical (ID: {$seo['id']})\n";
    }
} else {
    echo "   No existing SEO URLs found\n";
}

// Delete existing SEO URLs for this category
echo "\n3. Deleting old SEO URLs...\n";

if (!empty($result['data'])) {
    foreach ($result['data'] as $seo) {
        $ch = curl_init($config['shopware_url'] . '/api/seo-url/' . $seo['id']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 204) {
            echo "   Deleted: {$seo['seoPathInfo']}\n";
        } else {
            echo "   Failed to delete {$seo['seoPathInfo']}: HTTP $httpCode\n";
        }
    }
}

// Create new SEO URL
echo "\n4. Creating new SEO URL: Raven-Weapons/RAPAX/\n";

$seoUrlId = bin2hex(random_bytes(16));

$ch = curl_init($config['shopware_url'] . '/api/seo-url');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'id' => $seoUrlId,
        'salesChannelId' => $salesChannelId,
        'foreignKey' => $rapaxId,
        'routeName' => 'frontend.navigation.page',
        'pathInfo' => '/navigation/' . $rapaxId,
        'seoPathInfo' => 'Raven-Weapons/RAPAX/',
        'isCanonical' => true,
        'isModified' => true,
    ]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 204 || $httpCode === 200) {
    echo "   Created successfully!\n";
} else {
    echo "   HTTP $httpCode\n";
    echo "   Response: $response\n";
}

// Clear cache
echo "\n5. Clearing cache...\n";

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
echo "   Cache cleared\n";

echo "\n=== Done! ===\n";
echo "Check: https://ortak.ch/Raven-Weapons/RAPAX/\n";
