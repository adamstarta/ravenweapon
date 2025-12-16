<?php
/**
 * Move RAPAX and Caracal Lynx under Raven Weapons
 * Final structure:
 * Raven Weapons
 *   ↳ Sturmgewehre (first)
 *   ↳ RAPAX (second) - with RX subcategories
 *   ↳ Caracal Lynx (third) - with LYNX subcategories
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

echo "=== Moving RAPAX and Caracal Lynx under Raven Weapons ===\n\n";

// Category IDs
$ravenWeaponsId = 'a61f19c9cb4b11f0b4074aca3d279c31';   // Raven Weapons
$sturmgewehreId = '85482f0ec50ecc1a2db23ac833846a49';   // Sturmgewehre
$rapaxMainId = '1f36ebeb19da4fc6bc9cb3c3acfadafd';      // RAPAX (main - currently top-level)
$rapaxSubId = '95a7cf1575ddc0219d8f11484ab0cbeb';       // RAPAX (sub)
$caracalLynxId = '2b3fdb3f3dcc00eacf9c9683d5d22c6a';    // Caracal Lynx

// Step 1: Delete the RAPAX main category (we'll use RAPAX sub directly)
echo "1. Deleting RAPAX main category (keeping RAPAX sub as the main one)...\n";

// First, move RAPAX sub and Caracal Lynx to Raven Weapons
echo "2. Moving RAPAX (sub) directly under Raven Weapons...\n";
$result = apiPatch($config, $token, "category/$rapaxSubId", [
    'parentId' => $ravenWeaponsId,
    'afterCategoryId' => $sturmgewehreId, // After Sturmgewehre
]);
echo "   HTTP: {$result['code']}\n";

echo "3. Moving Caracal Lynx directly under Raven Weapons...\n";
$result = apiPatch($config, $token, "category/$caracalLynxId", [
    'parentId' => $ravenWeaponsId,
    'afterCategoryId' => $rapaxSubId, // After RAPAX
]);
echo "   HTTP: {$result['code']}\n";

// Now delete the empty RAPAX main category
echo "4. Deleting empty RAPAX main category...\n";
$result = apiDelete($config, $token, "category/$rapaxMainId");
echo "   HTTP: {$result['code']}\n";

// Clear cache
echo "\n5. Clearing cache...\n";
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
echo "Final structure:\n";
echo "Raven Weapons\n";
echo "  ↳ Sturmgewehre\n";
echo "    • .22 LR RAVEN\n";
echo "    • .223 RAVEN\n";
echo "    • 300 AAC RAVEN\n";
echo "    • 7.62x39 RAVEN\n";
echo "    • 9mm RAVEN\n";
echo "  ↳ RAPAX\n";
echo "    • RX Sport\n";
echo "    • RX Compact\n";
echo "    • RX Tactical\n";
echo "  ↳ Caracal Lynx\n";
echo "    • LYNX SPORT\n";
echo "    • LYNX OPEN\n";
echo "    • LYNX COMPACT\n";
