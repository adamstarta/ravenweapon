<?php
/**
 * Assign Raven rifle products to their category subcategories
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

echo "=== Assigning Products to Rifle Categories ===\n\n";

// Category IDs from previous script
$categories = [
    '.223 RAVEN' => 'deb6356d60c7f9969431f799195d87dd',
    '300 AAC RAVEN' => '50a2822803405b8ddabfac707451fd1e',
    '7.62x39 RAVEN' => '111b0bb5e60ce0c43d8e2b5614e7a46a',
    '9mm RAVEN' => 'c70364a333c24a81f7636485f4dbd3a1',
];

// Search for products with RAVEN in product number
$result = apiPost($config, $token, 'search/product', [
    'filter' => [
        [
            'type' => 'prefix',
            'field' => 'productNumber',
            'value' => 'RAVEN-'
        ]
    ],
    'limit' => 100
]);

echo "Found products with RAVEN- prefix:\n";
$payload = [];
foreach ($result['data'] ?? [] as $product) {
    $name = $product['attributes']['name'] ?? '';
    $sku = $product['attributes']['productNumber'] ?? '';
    $id = $product['id'] ?? null;

    if (!$id) {
        echo "Skipping product without ID\n";
        continue;
    }

    echo "- $name (SKU: $sku, ID: $id)\n";

    // Match to categories
    foreach ($categories as $catName => $catId) {
        if (stripos($name, $catName) !== false) {
            $payload[] = [
                'productId' => $id,
                'categoryId' => $catId,
            ];
            echo "  -> Assigning to $catName category\n";
        }
    }
}

echo "\nTotal assignments: " . count($payload) . "\n";

if (!empty($payload)) {
    $result = apiPost($config, $token, '_action/sync', [
        [
            'action' => 'upsert',
            'entity' => 'product_category',
            'payload' => $payload
        ]
    ]);
    echo "Sync result: HTTP {$result['code']}\n";
    if ($result['code'] != 200) {
        echo "Response: " . json_encode($result['data']) . "\n";
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
