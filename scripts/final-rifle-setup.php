<?php
/**
 * Final setup: recreate .22 LR RAVEN category and assign all products
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

echo "=== Final Rifle Category Setup ===\n\n";

$sturmgewehreId = '85482f0ec50ecc1a2db23ac833846a49';

// Product IDs from debug
$products = [
    '.22 LR RAVEN' => '019b12d26adf7fd69e1161330af7eb13',
    '.223 RAVEN' => '019ac3a6576172c3b53d1d9c7b915a6b',
    '300 AAC RAVEN' => '019ac3a704e0712ba03fe63f85004924',
    '7.62x39 RAVEN' => '019ac3a70722706db918ab6b3e182a86',
    '9mm RAVEN' => '019ac3a6afd0712fbbeaa9eef769a2a3',
];

// Existing category IDs
$categories = [
    '.223 RAVEN' => 'deb6356d60c7f9969431f799195d87dd',
    '300 AAC RAVEN' => '50a2822803405b8ddabfac707451fd1e',
    '7.62x39 RAVEN' => '111b0bb5e60ce0c43d8e2b5614e7a46a',
    '9mm RAVEN' => 'c70364a333c24a81f7636485f4dbd3a1',
];

// Step 1: Create .22 LR RAVEN category first (it was deleted earlier)
echo "1. Creating .22 LR RAVEN category...\n";
$new22CategoryId = bin2hex(random_bytes(16));
$result = apiPost($config, $token, 'category', [
    'id' => $new22CategoryId,
    'parentId' => $sturmgewehreId,
    'name' => '.22 LR RAVEN',
    'active' => true,
    'visible' => true,
    'type' => 'page',
    'displayNestedProducts' => true,
    'productAssignmentType' => 'product',
    'afterCategoryId' => null, // First position
]);
echo "   HTTP: {$result['code']} (ID: $new22CategoryId)\n";
$categories['.22 LR RAVEN'] = $new22CategoryId;

// Reorder categories: .22 LR first, then .223, 300 AAC, 7.62x39, 9mm
echo "\n2. Reordering categories...\n";
$order = ['.22 LR RAVEN', '.223 RAVEN', '300 AAC RAVEN', '7.62x39 RAVEN', '9mm RAVEN'];
$prevId = null;
foreach ($order as $name) {
    if (isset($categories[$name]) && $prevId !== null) {
        $ch = curl_init($config['shopware_url'] . '/api/category/' . $categories[$name]);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(['afterCategoryId' => $prevId]),
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        echo "   $name: HTTP $httpCode\n";
    }
    $prevId = $categories[$name] ?? null;
}

// Step 3: Assign products to categories
echo "\n3. Assigning products to their categories...\n";
$payload = [];
foreach ($products as $name => $productId) {
    if (isset($categories[$name])) {
        $payload[] = [
            'productId' => $productId,
            'categoryId' => $categories[$name],
        ];
        echo "   $name -> {$categories[$name]}\n";
    }
}

$result = apiPost($config, $token, '_action/sync', [
    [
        'action' => 'upsert',
        'entity' => 'product_category',
        'payload' => $payload
    ]
]);
echo "   Sync result: HTTP {$result['code']}\n";

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
echo "  ↳ Sturmgewehre (Assault Rifles)\n";
foreach ($order as $name) {
    echo "    • $name\n";
}
