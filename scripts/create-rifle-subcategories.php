<?php
/**
 * Create subcategories under Sturmgewehre for each Raven rifle
 * This allows the rifle names to appear in the navigation dropdown
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

echo "=== Creating Rifle Subcategories under Sturmgewehre ===\n\n";

$sturmgewehreId = '85482f0ec50ecc1a2db23ac833846a49';

// The Raven rifle names we want as subcategories
$rifleNames = [
    '.22 LR RAVEN',
    '.223 RAVEN',
    '300 AAC RAVEN',
    '7.62x39 RAVEN',
    '9mm RAVEN',
];

// First, find the actual products to link them later
echo "Finding Raven rifle products...\n";
$result = apiPost($config, $token, 'search/product', [
    'limit' => 500
]);

$rifleProducts = [];
if (!empty($result['data'])) {
    foreach ($result['data'] as $product) {
        $name = $product['attributes']['name'] ?? '';
        $productNumber = $product['attributes']['productNumber'] ?? '';

        foreach ($rifleNames as $rifleName) {
            if (stripos($name, $rifleName) !== false || $name === $rifleName) {
                $rifleProducts[$rifleName] = [
                    'id' => $product['id'],
                    'name' => $name,
                    'sku' => $productNumber
                ];
                echo "  Found: $name (SKU: $productNumber)\n";
            }
        }
    }
}

echo "\nCreating subcategories...\n";

$createdCategories = [];
$previousCategoryId = null;

foreach ($rifleNames as $index => $rifleName) {
    $categoryId = bin2hex(random_bytes(16));

    $categoryData = [
        'id' => $categoryId,
        'parentId' => $sturmgewehreId,
        'name' => $rifleName,
        'active' => true,
        'visible' => true,
        'type' => 'page',
        'displayNestedProducts' => true,
        'productAssignmentType' => 'product',
    ];

    // Set order - each one after the previous
    if ($previousCategoryId !== null) {
        $categoryData['afterCategoryId'] = $previousCategoryId;
    }

    $result = apiPost($config, $token, 'category', $categoryData);

    if ($result['code'] == 204 || $result['code'] == 200) {
        echo "  Created: $rifleName (ID: $categoryId)\n";
        $createdCategories[$rifleName] = $categoryId;
        $previousCategoryId = $categoryId;
    } else {
        echo "  Error creating $rifleName: HTTP {$result['code']}\n";
        if (!empty($result['data'])) {
            echo "    " . json_encode($result['data']) . "\n";
        }
    }
}

// Assign products to their respective categories
echo "\nAssigning products to their categories...\n";

$payload = [];
foreach ($rifleProducts as $rifleName => $product) {
    if (isset($createdCategories[$rifleName])) {
        $payload[] = [
            'productId' => $product['id'],
            'categoryId' => $createdCategories[$rifleName],
        ];
        echo "  Assigning {$product['name']} to $rifleName category\n";
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
    echo "  Sync result: HTTP {$result['code']}\n";
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
echo "New navigation structure:\n";
echo "Raven Weapons\n";
echo "  ↳ Sturmgewehre (Assault Rifles)\n";
foreach ($rifleNames as $name) {
    echo "    • $name\n";
}
