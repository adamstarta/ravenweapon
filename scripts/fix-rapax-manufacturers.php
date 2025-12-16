<?php
/**
 * Fix RAPAX/Caracal manufacturer assignments
 * RAPAX and Caracal are category names, NOT manufacturers
 * Remove these wrong manufacturer assignments from products
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

function apiRequest($config, $token, $method, $endpoint, $data = null) {
    $ch = curl_init($config['shopware_url'] . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_CUSTOMREQUEST => $method,
    ]);
    if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

$token = getAccessToken($config);

echo "=== Fixing RAPAX/Caracal Manufacturer Assignments ===\n\n";

// Find wrong manufacturers (RAPAX and Caracal)
echo "1. Finding wrong manufacturers...\n";
$result = apiRequest($config, $token, 'POST', 'search/product-manufacturer', [
    'filter' => [
        [
            'type' => 'multi',
            'operator' => 'OR',
            'queries' => [
                ['type' => 'contains', 'field' => 'name', 'value' => 'RAPAX'],
                ['type' => 'contains', 'field' => 'name', 'value' => 'Caracal'],
            ]
        ]
    ],
    'limit' => 50
]);

$wrongManufacturers = [];
if (!empty($result['data']['data'])) {
    foreach ($result['data']['data'] as $mfr) {
        echo "   Found: {$mfr['name']} (ID: {$mfr['id']})\n";
        $wrongManufacturers[$mfr['id']] = $mfr['name'];
    }
} else {
    echo "   No wrong manufacturers found.\n";
}

if (empty($wrongManufacturers)) {
    echo "\nNo wrong manufacturers to fix!\n";
    exit;
}

// Find products with these wrong manufacturers
echo "\n2. Finding products with wrong manufacturers...\n";
$productsToFix = [];

foreach ($wrongManufacturers as $mfrId => $mfrName) {
    $result = apiRequest($config, $token, 'POST', 'search/product', [
        'filter' => [
            ['type' => 'equals', 'field' => 'manufacturerId', 'value' => $mfrId]
        ],
        'limit' => 100
    ]);

    if (!empty($result['data']['data'])) {
        foreach ($result['data']['data'] as $product) {
            echo "   Product: {$product['name']} (manufacturer: $mfrName)\n";
            $productsToFix[] = $product['id'];
        }
    }
}

echo "\n   Total products to fix: " . count($productsToFix) . "\n";

// Remove manufacturer from products
echo "\n3. Removing manufacturer assignments from products...\n";
$fixed = 0;
foreach ($productsToFix as $productId) {
    $result = apiRequest($config, $token, 'PATCH', "product/$productId", [
        'manufacturerId' => null
    ]);

    if ($result['code'] === 204) {
        $fixed++;
        echo "   Fixed product $productId\n";
    } else {
        echo "   Error fixing product $productId: HTTP {$result['code']}\n";
    }
}

echo "\n   Fixed $fixed products\n";

// Delete wrong manufacturers
echo "\n4. Deleting wrong manufacturers...\n";
foreach ($wrongManufacturers as $mfrId => $mfrName) {
    $result = apiRequest($config, $token, 'DELETE', "product-manufacturer/$mfrId", null);

    if ($result['code'] === 204) {
        echo "   Deleted manufacturer: $mfrName\n";
    } else {
        echo "   Error deleting $mfrName: HTTP {$result['code']}\n";
    }
}

// Clear cache
echo "\n5. Clearing cache...\n";
$ch = curl_init($config['shopware_url'] . '/api/_action/cache');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
    ],
]);
curl_exec($ch);
curl_close($ch);
echo "   Cache cleared\n";

echo "\n=== Done! ===\n";
echo "Products no longer have RAPAX/Caracal as manufacturer.\n";
echo "Valid manufacturers are: Lockhart Tactical, Magpul, Snigel, ZeroTech\n";
