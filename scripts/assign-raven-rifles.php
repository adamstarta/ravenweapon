<?php
/**
 * Find and assign Raven rifle products to Sturmgewehre category
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

function apiPost($config, $token, $endpoint, $data) {
    $ch = curl_init($config['shopware_url'] . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
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

echo "=== Finding Raven Rifle Products ===\n\n";

$sturmgewehreId = '85482f0ec50ecc1a2db23ac833846a49';

// Search ALL products
$result = apiPost($config, $token, 'search/product', [
    'limit' => 500
]);

echo "Total products found: " . count($result['data'] ?? []) . "\n\n";

$ravenRifles = [];
if (!empty($result['data'])) {
    foreach ($result['data'] as $product) {
        $name = $product['attributes']['name'] ?? '';
        $productNumber = $product['attributes']['productNumber'] ?? '';

        // Find Raven rifle products (not caliber kits, not Snigel)
        // Looking for: .22 LR RAVEN, .223 RAVEN, 300 AAC RAVEN, etc.
        if (
            (stripos($name, 'RAVEN') !== false || stripos($name, 'Raven') !== false) &&
            stripos($name, 'CALIBER KIT') === false &&
            stripos($name, 'Caliber Kit') === false &&
            stripos($productNumber, 'SN-') === false  // Not Snigel products
        ) {
            $ravenRifles[] = [
                'id' => $product['id'],
                'name' => $name,
                'sku' => $productNumber
            ];
        }
    }
}

echo "Found " . count($ravenRifles) . " Raven rifle products:\n";
foreach ($ravenRifles as $rifle) {
    echo "- {$rifle['name']} (SKU: {$rifle['sku']})\n";
}

if (count($ravenRifles) > 0) {
    echo "\nAssigning to Sturmgewehre category...\n";

    $payload = [];
    foreach ($ravenRifles as $product) {
        $payload[] = [
            'productId' => $product['id'],
            'categoryId' => $sturmgewehreId,
        ];
    }

    $result = apiPost($config, $token, '_action/sync', [
        [
            'action' => 'upsert',
            'entity' => 'product_category',
            'payload' => $payload
        ]
    ]);

    echo "Sync result: HTTP {$result['code']}\n";

    if ($result['code'] != 200) {
        echo "Error: " . json_encode($result['data']) . "\n";
    }
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
