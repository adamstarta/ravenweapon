<?php
/**
 * Find which Ausrüstung subcategory contains ZeroTech products
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

// Get token
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/oauth/token',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'grant_type' => 'password',
        'client_id' => 'administration',
        'username' => $config['api_user'],
        'password' => $config['api_password'],
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);
$token = $data['access_token'];

// Ausrüstung subcategory IDs from previous run
$subcategories = [
    '3516b9f976d106b5c5a8f528e753d8c9',
    '38f38bbe87620624644240a85e7fc67e',
    '40d2c4a10d4a18f69995512902daa405',
    '43276fb503438abccf6b54b791d18365',
    '442c809db5f4f3bffc45fee26c3f78a5',
    '4a6c7dc219e2e3dba3b85339d267029b',
    '4f583dec67f2540065ef06394c3b2129',
    '5fb58b6aa5a32fa17148d519cb4d8fe2',
    '6152c0c2abb52939ceff12c49d530d41',
    '764cb07ad5319085c21ce15d1901ea0e',
    '8b5f136ed349f74f355aeb74b5ff53e8',
    '96e5cc0d8f63df5c8a52a64cfddaa2ed',
    '98c2913fa110d18b3f9b5a2ed78aa918',
    'b4a2175dce5c7718a80824a6f3a59448',
    'b5dfc22fd3ca35c5e3258867e6b210c2',
    'b812fb4f768ac152ac2c8e7112e9ba0c',
    'bed321971d62163dbc89818be4ea69c8',
    'd7937596e40f37bfa8272780cb596a06',
    'ef3d2a1ada565ff62689ec632597ce9f',
    'f0530db95d0e4f26cb93ea8d9816067a',
    'f628bc7799276f8286c74fb3be2063e2',
];

echo "=== Searching for ZeroTech products in each subcategory ===\n\n";

$zerotechProductId = '019adf49f7c97346af3c846a74bbf09d';

foreach ($subcategories as $catId) {
    // Search for products in this category
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . '/api/search/product',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'filter' => [
                ['type' => 'equals', 'field' => 'categories.id', 'value' => $catId],
                ['type' => 'equals', 'field' => 'id', 'value' => $zerotechProductId]
            ],
            'limit' => 1
        ]),
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);

    $count = $result['total'] ?? 0;
    if ($count > 0) {
        echo "FOUND! Category ID: $catId contains ZeroTech product!\n";
    }
}

echo "\n=== Checking if products are directly in Ausrüstung ===\n";

$ausruestungId = '019b0857613474e6a799cfa07d143c76';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/search/product',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'filter' => [
            ['type' => 'equals', 'field' => 'categories.id', 'value' => $ausruestungId],
            ['type' => 'equals', 'field' => 'id', 'value' => $zerotechProductId]
        ],
        'limit' => 1
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);

$count = $result['total'] ?? 0;
echo "Products directly in Ausrüstung: $count\n";

// Also let's check Spektive
echo "\n=== Checking Spektive ===\n";

// Search for Spektive category
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/search/category',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'term' => 'Spektive',
        'limit' => 10
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);

echo "Categories matching 'Spektive': " . ($result['total'] ?? 0) . "\n";
if (!empty($result['data'])) {
    foreach ($result['data'] as $cat) {
        echo "  ID: {$cat['id']}\n";

        // Check if ZeroTech is in this category
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $config['shopware_url'] . '/api/search/product',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'filter' => [
                    ['type' => 'equals', 'field' => 'categories.id', 'value' => $cat['id']],
                    ['type' => 'equals', 'field' => 'id', 'value' => $zerotechProductId]
                ],
                'limit' => 1
            ]),
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $r = json_decode($response, true);

        if (($r['total'] ?? 0) > 0) {
            echo "    ^ Contains ZeroTech product!\n";
        }
    }
}

// Let's also look for "Alle Produkte" category which might show all products
echo "\n=== Checking 'Alle Produkte' Category ===\n";
$alleProdukte = '019aee0f487c79a1a8814377c46e0c10';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . "/api/category/$alleProdukte",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);

if (!empty($result['data'])) {
    echo "productAssignmentType: " . ($result['data']['productAssignmentType'] ?? 'product') . "\n";
    echo "displayNestedProducts: " . (isset($result['data']['displayNestedProducts']) ? ($result['data']['displayNestedProducts'] ? 'true' : 'false') : 'NOT SET') . "\n";
}
