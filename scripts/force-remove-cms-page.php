<?php
/**
 * Force remove CMS page from RAPAX category using _allow_write_
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

echo "=== Force Removing CMS Page from RAPAX Category ===\n\n";

$rapaxCategoryId = '1f36ebeb19da4fc6bc9cb3c3acfadafd';

// Try using sync API to force update
$ch = curl_init($config['shopware_url'] . "/api/_action/sync");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        [
            'action' => 'upsert',
            'entity' => 'category',
            'payload' => [
                [
                    'id' => $rapaxCategoryId,
                    'cmsPageId' => null,
                    'cmsPageIdSwitched' => false,
                ]
            ]
        ]
    ]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Sync API response: HTTP $httpCode\n";
if ($httpCode !== 200) {
    echo $response . "\n";
}

// Clear cache
echo "\nClearing cache...\n";
$ch = curl_init($config['shopware_url'] . "/api/_action/cache");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Cache clear: HTTP $httpCode\n";

// Rebuild SEO URLs
echo "\nRebuilding SEO URLs...\n";
$ch = curl_init($config['shopware_url'] . "/api/_action/index");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'skip' => []
    ]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Index rebuild: HTTP $httpCode\n";

echo "\nDone! Check: https://ortak.ch/Raven-Weapons/RAPAX/\n";
