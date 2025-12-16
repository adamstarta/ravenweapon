<?php
/**
 * Find all SEO URLs - raw API call
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

// Get SEO URLs
$ch = curl_init($config['shopware_url'] . '/api/seo-url?filter[seoPathInfo]=Raven-Weapons/RAPAX/&limit=10');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
]);
$response = curl_exec($ch);
curl_close($ch);

echo "=== SEO URLs for Raven-Weapons/RAPAX/ ===\n";
$result = json_decode($response, true);
print_r($result);

// Also try to get category directly by checking all children of Raven Weapons
echo "\n\n=== Raven Weapons children with full data ===\n";
$ravenWeaponsId = 'a61f19c9cb4b11f0b4074aca3d279c31';

$ch = curl_init($config['shopware_url'] . "/api/category/$ravenWeaponsId?associations[children][associations][seoUrls]=[]");
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
if (!empty($result['data']['children'])) {
    foreach ($result['data']['children'] as $child) {
        echo "\nChild Category:\n";
        echo "  ID: {$child['id']}\n";
        echo "  Name: " . ($child['translated']['name'] ?? $child['name'] ?? 'Unknown') . "\n";
        if (!empty($child['seoUrls'])) {
            foreach ($child['seoUrls'] as $seo) {
                echo "  SEO URL: {$seo['seoPathInfo']}\n";
            }
        } else {
            echo "  SEO URLs: None loaded\n";
        }
    }
} else {
    echo "No children found or different structure:\n";
    print_r($result);
}
