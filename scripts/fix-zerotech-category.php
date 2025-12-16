<?php
/**
 * Fix ZeroTech spotting scope products - remove from Ausrüstung category
 * They should only be in Spektive
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

function apiDelete($config, $token, $endpoint) {
    $ch = curl_init($config['shopware_url'] . '/api/' . $endpoint);
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
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

$token = getAccessToken($config);

echo "=== Removing ZeroTech Products from Ausrüstung ===\n\n";

// Products to remove from Ausrüstung
$productsToFix = [
    '019adf49f7c97346af3c846a74bbf09d' => '20-60X80 FFP OSR MRAD SPOTTING SCOPE',
    '019adf49f88a70579ac7387fc5856acd' => '20-60X85 TING SCOPE',
];

$ausruestungId = '019b0857613474e6a799cfa07d143c76';

foreach ($productsToFix as $productId => $productName) {
    echo "Removing: $productName\n";
    echo "  Product ID: $productId\n";

    $result = apiDelete($config, $token, "product/$productId/categories/$ausruestungId");

    if ($result['code'] == 204) {
        echo "  ✓ Successfully removed from Ausrüstung\n";
    } else {
        echo "  HTTP: {$result['code']}\n";
        if (!empty($result['data']['errors'])) {
            print_r($result['data']['errors']);
        }
    }
    echo "\n";
}

// Clear cache
echo "=== Clearing Cache ===\n";
$ch = curl_init($config['shopware_url'] . '/api/_action/cache');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
]);
curl_exec($ch);
curl_close($ch);
echo "Cache cleared!\n";

echo "\n=== Done! ===\n";
echo "ZeroTech spotting scope products removed from Ausrüstung.\n";
echo "They should now only appear in Spektive category.\n";
