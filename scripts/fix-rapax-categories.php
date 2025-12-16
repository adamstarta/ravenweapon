<?php
/**
 * Fix RAPAX categories - move products to existing "Raven Weapons > RAPAX" and delete wrong "Weapons" category
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
    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

$token = getAccessToken($config);
echo "=== Fixing RAPAX Categories ===\n\n";

// Known IDs
$ravenWeaponsId = 'a61f19c9cb4b11f0b4074aca3d279c31';
$wrongWeaponsId = '1339d2362672f104f5492f4333e05ff8';

// Step 1: Get children of Raven Weapons to find RAPAX
echo "Step 1: Finding RAPAX under Raven Weapons...\n";
$result = apiRequest($config, $token, 'POST', 'search/category', [
    'filter' => [
        ['type' => 'equals', 'field' => 'parentId', 'value' => $ravenWeaponsId]
    ]
]);

$targetRapaxId = null;
if (!empty($result['body']['data'])) {
    foreach ($result['body']['data'] as $cat) {
        echo "  - {$cat['name']}: {$cat['id']}\n";
        if (stripos($cat['name'], 'RAPAX') !== false) {
            $targetRapaxId = $cat['id'];
            echo "    ✓ This is our target RAPAX category\n";
        }
    }
}

if (!$targetRapaxId) {
    echo "  RAPAX not found under Raven Weapons, creating it...\n";
    $result = apiRequest($config, $token, 'POST', 'category', [
        'name' => 'RAPAX',
        'parentId' => $ravenWeaponsId,
        'active' => true,
    ]);
    if ($result['code'] === 204 || !empty($result['body']['data']['id'])) {
        // Get the created category ID
        $searchResult = apiRequest($config, $token, 'POST', 'search/category', [
            'filter' => [
                ['type' => 'equals', 'field' => 'parentId', 'value' => $ravenWeaponsId],
                ['type' => 'equals', 'field' => 'name', 'value' => 'RAPAX']
            ]
        ]);
        if (!empty($searchResult['body']['data'][0])) {
            $targetRapaxId = $searchResult['body']['data'][0]['id'];
            echo "  ✓ Created RAPAX category: $targetRapaxId\n";
        }
    }
}

echo "\nTarget RAPAX ID: $targetRapaxId\n";

// Step 2: Find all RAPAX and CARACAL products
echo "\nStep 2: Finding all RAPAX and CARACAL products...\n";
$allProducts = [];

// Search for products by manufacturer RAPAX
$result = apiRequest($config, $token, 'POST', 'search/product', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'RAPAX']
    ],
    'limit' => 100
]);
if (!empty($result['body']['data'])) {
    foreach ($result['body']['data'] as $p) {
        $allProducts[$p['id']] = $p['name'];
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
    foreach ($result['body']['data'] as $p) {
        $allProducts[$p['id']] = $p['name'];
    }
}

echo "Found " . count($allProducts) . " products\n";

// Step 3: Update all products to use the correct category
echo "\nStep 3: Updating products to correct category...\n";
$updated = 0;
$errors = 0;

foreach ($allProducts as $productId => $name) {
    $result = apiRequest($config, $token, 'PATCH', "product/$productId", [
        'categories' => [
            ['id' => $targetRapaxId]
        ]
    ]);

    if ($result['code'] === 204 || $result['code'] === 200) {
        echo "  ✓ $name\n";
        $updated++;
    } else {
        echo "  ✗ $name: " . json_encode($result['body']) . "\n";
        $errors++;
    }
}

// Step 4: Delete the wrong "Weapons" category and its children
echo "\nStep 4: Deleting wrong 'Weapons' category tree...\n";

// First, get all children of wrong Weapons category
$result = apiRequest($config, $token, 'POST', 'search/category', [
    'filter' => [
        ['type' => 'equals', 'field' => 'parentId', 'value' => $wrongWeaponsId]
    ]
]);

$categoriesToDelete = [];
if (!empty($result['body']['data'])) {
    foreach ($result['body']['data'] as $cat) {
        // Get grandchildren
        $grandResult = apiRequest($config, $token, 'POST', 'search/category', [
            'filter' => [
                ['type' => 'equals', 'field' => 'parentId', 'value' => $cat['id']]
            ]
        ]);
        if (!empty($grandResult['body']['data'])) {
            foreach ($grandResult['body']['data'] as $grandchild) {
                // Get great-grandchildren
                $greatResult = apiRequest($config, $token, 'POST', 'search/category', [
                    'filter' => [
                        ['type' => 'equals', 'field' => 'parentId', 'value' => $grandchild['id']]
                    ]
                ]);
                if (!empty($greatResult['body']['data'])) {
                    foreach ($greatResult['body']['data'] as $great) {
                        $categoriesToDelete[] = ['id' => $great['id'], 'name' => $great['name'] ?? 'Unknown'];
                    }
                }
                $categoriesToDelete[] = ['id' => $grandchild['id'], 'name' => $grandchild['name'] ?? 'Unknown'];
            }
        }
        $categoriesToDelete[] = ['id' => $cat['id'], 'name' => $cat['name'] ?? 'Unknown'];
    }
}
$categoriesToDelete[] = ['id' => $wrongWeaponsId, 'name' => 'Weapons'];

echo "Categories to delete: " . count($categoriesToDelete) . "\n";

foreach ($categoriesToDelete as $cat) {
    $result = apiRequest($config, $token, 'DELETE', "category/{$cat['id']}");
    if ($result['code'] === 204) {
        echo "  ✓ Deleted: {$cat['name']}\n";
    } else {
        echo "  ✗ Could not delete {$cat['name']}: HTTP {$result['code']}\n";
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║                           COMPLETE                                 ║\n";
echo "╠════════════════════════════════════════════════════════════════════╣\n";
printf("║  Products moved:     %-45d ║\n", $updated);
printf("║  Errors:             %-45d ║\n", $errors);
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "\nCheck: https://ortak.ch/Raven-Weapons/RAPAX/\n";
