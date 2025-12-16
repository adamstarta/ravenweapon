<?php
/**
 * Move RAPAX/CARACAL products to the correct "Raven Weapons > RAPAX" category
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

// Get OAuth token
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        die("Authentication failed: $response\n");
    }

    $data = json_decode($response, true);
    return $data['access_token'];
}

function apiRequest($config, $token, $method, $endpoint, $data = null) {
    $ch = curl_init($config['shopware_url'] . '/api/' . $endpoint);
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method,
    ]);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

echo "=== Moving RAPAX Products to Correct Category ===\n\n";

// Get token
echo "Authenticating...\n";
$token = getAccessToken($config);
echo "✓ Authenticated\n\n";

// Step 1: Find the existing "Raven Weapons > RAPAX" category
echo "Step 1: Finding existing 'Raven Weapons > RAPAX' category...\n";

$result = apiRequest($config, $token, 'POST', 'search/category', [
    'filter' => [
        ['type' => 'equals', 'field' => 'name', 'value' => 'RAPAX']
    ],
    'associations' => [
        'parent' => []
    ]
]);

$targetCategoryId = null;
$wrongCategoryId = null;

if (!empty($result['body']['data'])) {
    foreach ($result['body']['data'] as $cat) {
        $parentName = $cat['parent']['name'] ?? 'ROOT';
        echo "  Found RAPAX category: {$cat['id']} (parent: $parentName)\n";

        if ($parentName === 'Raven Weapons') {
            $targetCategoryId = $cat['id'];
            echo "  ✓ This is the TARGET category (Raven Weapons > RAPAX)\n";
        } elseif ($parentName === 'Weapons' || $parentName === 'ROOT') {
            $wrongCategoryId = $cat['id'];
            echo "  ✗ This is the WRONG category (Weapons > RAPAX)\n";
        }
    }
}

if (!$targetCategoryId) {
    die("\nERROR: Could not find 'Raven Weapons > RAPAX' category!\n");
}

echo "\nTarget Category ID: $targetCategoryId\n";

// Step 2: Find all RAPAX and CARACAL products
echo "\nStep 2: Finding all RAPAX and CARACAL products...\n";

$allProducts = [];

// Search for RAPAX products
$result = apiRequest($config, $token, 'POST', 'search/product', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'RAPAX']
    ],
    'limit' => 100
]);

if (!empty($result['body']['data'])) {
    foreach ($result['body']['data'] as $product) {
        $allProducts[$product['id']] = $product['name'];
    }
}

// Search for CARACAL products
$result = apiRequest($config, $token, 'POST', 'search/product', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'CARACAL']
    ],
    'limit' => 100
]);

if (!empty($result['body']['data'])) {
    foreach ($result['body']['data'] as $product) {
        $allProducts[$product['id']] = $product['name'];
    }
}

echo "Found " . count($allProducts) . " products to update\n";

// Step 3: Update each product's category
echo "\nStep 3: Updating product categories...\n";

$updated = 0;
$errors = 0;

foreach ($allProducts as $productId => $productName) {
    // Update product category
    $result = apiRequest($config, $token, 'PATCH', "product/$productId", [
        'categories' => [
            ['id' => $targetCategoryId]
        ]
    ]);

    if ($result['code'] === 204 || $result['code'] === 200) {
        echo "  ✓ Updated: $productName\n";
        $updated++;
    } else {
        echo "  ✗ Error updating $productName: " . json_encode($result['body']) . "\n";
        $errors++;
    }
}

// Step 4: Delete the wrong "Weapons" category tree if it exists
if ($wrongCategoryId) {
    echo "\nStep 4: Cleaning up wrong category structure...\n";

    // First find the parent "Weapons" category
    $result = apiRequest($config, $token, 'POST', 'search/category', [
        'filter' => [
            ['type' => 'equals', 'field' => 'name', 'value' => 'Weapons']
        ]
    ]);

    if (!empty($result['body']['data'])) {
        foreach ($result['body']['data'] as $cat) {
            echo "  Found 'Weapons' category: {$cat['id']}\n";
            // Delete the category (this will cascade to children if empty)
            $deleteResult = apiRequest($config, $token, 'DELETE', "category/{$cat['id']}");
            if ($deleteResult['code'] === 204) {
                echo "  ✓ Deleted 'Weapons' category\n";
            } else {
                echo "  Note: Could not delete 'Weapons' category (may have other products)\n";
            }
        }
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║                         UPDATE COMPLETE                            ║\n";
echo "╠════════════════════════════════════════════════════════════════════╣\n";
printf("║  Products updated:  %-47d ║\n", $updated);
printf("║  Errors:            %-47d ║\n", $errors);
echo "╚════════════════════════════════════════════════════════════════════╝\n";

echo "\nProducts should now appear at: https://ortak.ch/Raven-Weapons/RAPAX/\n";
