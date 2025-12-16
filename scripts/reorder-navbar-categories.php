<?php
/**
 * Reorder navbar categories:
 * 1. Alle Produkte
 * 2. Raven Weapons
 * 3. Raven Caliber Kit
 * 4. Zielhilfen, Optik & Zubehör
 * 5. Munition
 * 6. Zubehör
 * 7. Ausrüstung
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

echo "=== Reordering Navbar Categories ===\n\n";

// Category IDs (from previous checks)
$categories = [
    'Alle Produkte' => '019aee0f487c79a1a8814377c46e0c10',
    'Raven Weapons' => 'a61f19c9cb4b11f0b4074aca3d279c31',
    'Raven Caliber Kit' => 'a61f1ec3cb4b11f0b4074aca3d279c31',
    'Zielhilfen, Optik & Zubehör' => '019adeff65f97225927586968691dc02',
    'Munition' => '2f40311624aea6de289c770f0bfd0ff9',
    'Zubehör' => '604131c6ae1646c98623da4fe61a739b',
    'Ausrüstung' => '019b0857613474e6a799cfa07d143c76',
];

// Order them: each one after the previous
$order = ['Alle Produkte', 'Raven Weapons', 'Raven Caliber Kit', 'Zielhilfen, Optik & Zubehör', 'Munition', 'Zubehör', 'Ausrüstung'];

$prevId = null;
foreach ($order as $index => $name) {
    $catId = $categories[$name];

    $data = [];
    if ($prevId === null) {
        // First item - no afterCategoryId (first position)
        $data['afterCategoryId'] = null;
    } else {
        $data['afterCategoryId'] = $prevId;
    }

    $result = apiPatch($config, $token, "category/$catId", $data);
    $position = $index + 1;
    echo "$position. $name: HTTP {$result['code']}\n";

    $prevId = $catId;
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
echo "New navbar order:\n";
foreach ($order as $index => $name) {
    echo ($index + 1) . ". $name\n";
}
