<?php
/**
 * Fix RAPAX subcategory order: RAPAX sub first, Caracal Lynx second
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

function apiPatch($config, $token, $endpoint, $data) {
    $ch = curl_init($config['shopware_url'] . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

$token = getAccessToken($config);

echo "=== Fixing RAPAX Subcategory Order ===\n\n";

// Category IDs
$rapaxSubId = '95a7cf1575ddc0219d8f11484ab0cbeb';     // RAPAX (sub)
$caracalLynxId = '2b3fdb3f3dcc00eacf9c9683d5d22c6a';  // Caracal Lynx

// Set RAPAX sub to come first (no afterCategoryId = first position)
echo "1. Setting RAPAX (sub) as first...\n";
$result = apiPatch($config, $token, "category/$rapaxSubId", [
    'afterCategoryId' => null,
]);
echo "   HTTP: {$result['code']}\n";

// Set Caracal Lynx to come after RAPAX sub
echo "2. Setting Caracal Lynx after RAPAX (sub)...\n";
$result = apiPatch($config, $token, "category/$caracalLynxId", [
    'afterCategoryId' => $rapaxSubId,
]);
echo "   HTTP: {$result['code']}\n";

// Clear cache
echo "\n3. Clearing cache...\n";
$ch = curl_init($config['shopware_url'] . '/api/_action/cache');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
]);
curl_exec($ch);
curl_close($ch);
echo "   Done\n";

echo "\n=== Complete! ===\n";
echo "RAPAX structure:\n";
echo "RAPAX (top-level)\n";
echo "  ↳ RAPAX (sub) <- first\n";
echo "    • RX Sport\n";
echo "    • RX Tactical\n";
echo "    • RX Compact\n";
echo "  ↳ Caracal Lynx <- second\n";
echo "    • LYNX SPORT\n";
echo "    • LYNX OPEN\n";
echo "    • LYNX COMPACT\n";
