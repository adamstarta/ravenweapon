<?php
/**
 * Update empty categories to show a message instead of blank
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

echo "=== Updating Empty Category Messages ===\n\n";

// Category IDs
$lynxOpenId = '7048c95bf71dd4802adb7846617b4503';

// German message for empty category
$emptyMessage = '<div style="text-align: center; padding: 60px 20px; background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); border-radius: 12px; margin: 20px 0;">
    <div style="font-size: 48px; margin-bottom: 20px;">🔜</div>
    <h2 style="color: #c9a227; font-size: 24px; margin-bottom: 15px; font-weight: 600;">Bald verfügbar</h2>
    <p style="color: #ffffff; font-size: 16px; max-width: 500px; margin: 0 auto; line-height: 1.6;">
        Neue Produkte werden in Kürze hinzugefügt. Schauen Sie bald wieder vorbei oder kontaktieren Sie uns für weitere Informationen.
    </p>
    <div style="margin-top: 25px;">
        <a href="mailto:info@ravenweapon.ch" style="display: inline-block; background: #c9a227; color: #000; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: 600; transition: all 0.3s;">
            Kontaktieren Sie uns
        </a>
    </div>
</div>';

echo "Updating LYNX OPEN category...\n";

$result = apiPatch($config, $token, "category/$lynxOpenId", [
    'description' => $emptyMessage
]);

echo "   HTTP: {$result['code']}\n";

if ($result['code'] == 204 || $result['code'] == 200) {
    echo "   Success!\n";
} else {
    echo "   Error: " . json_encode($result['data']) . "\n";
}

// Clear cache
echo "\nClearing cache...\n";
$ch = curl_init($config['shopware_url'] . '/api/_action/cache');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
]);
curl_exec($ch);
curl_close($ch);
echo "Done\n";

echo "\n=== Complete! ===\n";
echo "Check: https://ortak.ch/Raven-Weapons/RAPAX/Caracal-Lynx/LYNX-OPEN/\n";
