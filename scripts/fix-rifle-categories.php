<?php
/**
 * Fix rifle categories - assign products and remove empty .22 LR RAVEN
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

echo "=== Fixing Rifle Categories ===\n\n";

// Category IDs from previous script output
$rifleCategoryIds = [
    '.22 LR RAVEN' => '291dacbf988d390a903281592944458c',
    '.223 RAVEN' => 'deb6356d60c7f9969431f799195d87dd',
    '300 AAC RAVEN' => '50a2822803405b8ddabfac707451fd1e',
    '7.62x39 RAVEN' => '111b0bb5e60ce0c43d8e2b5614e7a46a',
    '9mm RAVEN' => 'c70364a333c24a81f7636485f4dbd3a1',
];

// Step 1: Delete .22 LR RAVEN category (no product exists)
echo "1. Deleting .22 LR RAVEN category (no product exists)...\n";
$result = apiDelete($config, $token, 'category/' . $rifleCategoryIds['.22 LR RAVEN']);
echo "   HTTP: {$result['code']}\n";

// Step 2: Find products and assign to categories
echo "\n2. Finding products...\n";
$result = apiPost($config, $token, 'search/product', ['limit' => 100]);

$rifleMapping = [
    '.223 RAVEN' => null,
    '300 AAC RAVEN' => null,
    '7.62x39 RAVEN' => null,
    '9mm RAVEN' => null,
];

foreach ($result['data'] ?? [] as $product) {
    $name = $product['attributes']['name'] ?? '';
    foreach (array_keys($rifleMapping) as $rifleName) {
        if ($name === $rifleName) {
            $rifleMapping[$rifleName] = $product['id'];
            echo "   Found: $name (ID: {$product['id']})\n";
        }
    }
}

// Step 3: Assign products to categories
echo "\n3. Assigning products to categories...\n";
$payload = [];
foreach ($rifleMapping as $rifleName => $productId) {
    if ($productId && isset($rifleCategoryIds[$rifleName])) {
        $payload[] = [
            'productId' => $productId,
            'categoryId' => $rifleCategoryIds[$rifleName],
        ];
        echo "   Assigning $rifleName to its category\n";
    }
}

if (!empty($payload)) {
    $result = apiPost($config, $token, '_action/sync', [
        [
            'action' => 'upsert',
            'entity' => 'product_category',
            'payload' => $payload
        ]
    ]);
    echo "   Sync result: HTTP {$result['code']}\n";
}

// Clear cache
echo "\n4. Clearing cache...\n";
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
echo "    • .223 RAVEN\n";
echo "    • 300 AAC RAVEN\n";
echo "    • 7.62x39 RAVEN\n";
echo "    • 9mm RAVEN\n";
