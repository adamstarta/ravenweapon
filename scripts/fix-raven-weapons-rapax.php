<?php
/**
 * Add products to the Raven-Weapons/RAPAX category (the one with CMS page)
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
echo "=== Fix Raven-Weapons/RAPAX Category ===\n\n";

// Find the category for /Raven-Weapons/RAPAX/
echo "Step 1: Finding category for /Raven-Weapons/RAPAX/...\n";

$result = apiRequest($config, $token, 'POST', 'search/seo-url', [
    'filter' => [
        ['type' => 'contains', 'field' => 'seoPathInfo', 'value' => 'Raven-Weapons/RAPAX']
    ],
    'limit' => 10
]);

$ravenWeaponsRapaxId = null;
if (!empty($result['body']['data'])) {
    foreach ($result['body']['data'] as $seo) {
        $path = $seo['seoPathInfo'] ?? '';
        $foreignKey = $seo['foreignKey'] ?? '';
        $routeName = $seo['routeName'] ?? '';
        echo "  Path: $path\n";
        echo "  Foreign Key: $foreignKey\n";
        echo "  Route: $routeName\n\n";

        if ($routeName === 'frontend.navigation.page' && stripos($path, 'Raven-Weapons/RAPAX') !== false) {
            $ravenWeaponsRapaxId = $foreignKey;
        }
    }
}

if (!$ravenWeaponsRapaxId) {
    // Try to find via parent
    echo "  Searching via Raven Weapons parent...\n";
    $ravenWeaponsId = 'a61f19c9cb4b11f0b4074aca3d279c31';

    $result = apiRequest($config, $token, 'GET', "category?filter[parentId]=$ravenWeaponsId");

    if (!empty($result['body']['data'])) {
        foreach ($result['body']['data'] as $cat) {
            $name = $cat['translated']['name'] ?? $cat['name'] ?? '';
            echo "  Found child: $name ({$cat['id']})\n";

            // Check SEO URL of this category
            $seoResult = apiRequest($config, $token, 'POST', 'search/seo-url', [
                'filter' => [
                    ['type' => 'equals', 'field' => 'foreignKey', 'value' => $cat['id']],
                    ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
                ]
            ]);

            if (!empty($seoResult['body']['data'])) {
                foreach ($seoResult['body']['data'] as $seo) {
                    $path = $seo['seoPathInfo'] ?? '';
                    echo "    SEO URL: $path\n";
                    if (stripos($path, 'Raven-Weapons/RAPAX') !== false) {
                        $ravenWeaponsRapaxId = $cat['id'];
                        echo "    ✓ This is the target category!\n";
                    }
                }
            }
        }
    }
}

if (!$ravenWeaponsRapaxId) {
    die("\nCould not find category for /Raven-Weapons/RAPAX/\n");
}

echo "\nTarget category ID: $ravenWeaponsRapaxId\n";

// Get products from the working /rapax/ category
$workingCategoryId = '1f36ebeb19da4fc6bc9cb3c3acfadafd';
echo "\nStep 2: Getting products from working category...\n";

$result = apiRequest($config, $token, 'POST', 'search/product', [
    'filter' => [
        ['type' => 'equals', 'field' => 'categories.id', 'value' => $workingCategoryId]
    ],
    'limit' => 50
]);

$productIds = [];
if (!empty($result['body']['data'])) {
    foreach ($result['body']['data'] as $p) {
        $productIds[] = $p['id'];
    }
}
echo "Found " . count($productIds) . " products\n";

// Add products to the Raven-Weapons/RAPAX category
echo "\nStep 3: Adding products to Raven-Weapons/RAPAX category...\n";

$added = 0;
foreach ($productIds as $productId) {
    // Add this category to the product's categories (keeping existing)
    $result = apiRequest($config, $token, 'POST', "product/$productId/categories", [
        'id' => $ravenWeaponsRapaxId
    ]);

    if ($result['code'] === 204 || $result['code'] === 200) {
        $added++;
        echo ".";
    } else {
        echo "x";
    }
}
echo "\n$added products added to category\n";

// Also update the category to show products
echo "\nStep 4: Updating category to show products...\n";
$result = apiRequest($config, $token, 'PATCH', "category/$ravenWeaponsRapaxId", [
    'cmsPageId' => null,
    'displayNestedProducts' => true,
    'productAssignmentType' => 'product',
]);

if ($result['code'] === 204) {
    echo "✓ Category updated\n";
}

echo "\nDone! Check: https://ortak.ch/Raven-Weapons/RAPAX/\n";
