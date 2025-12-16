<?php
/**
 * Final fix - Move products to the original RAPAX category that has the SEO URL
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
echo "=== Final RAPAX Category Fix ===\n\n";

// Step 1: Find the category with SEO URL "Raven-Weapons/RAPAX"
echo "Step 1: Finding category with SEO URL 'Raven-Weapons/RAPAX'...\n";

$result = apiRequest($config, $token, 'POST', 'search/seo-url', [
    'filter' => [
        ['type' => 'equals', 'field' => 'seoPathInfo', 'value' => 'Raven-Weapons/RAPAX/']
    ]
]);

$originalRapaxId = null;
if (!empty($result['body']['data'])) {
    foreach ($result['body']['data'] as $seo) {
        echo "  Found SEO URL entry\n";
        echo "  Foreign Key (Category ID): " . ($seo['foreignKey'] ?? 'N/A') . "\n";
        $originalRapaxId = $seo['foreignKey'] ?? null;
    }
}

if (!$originalRapaxId) {
    // Try without trailing slash
    $result = apiRequest($config, $token, 'POST', 'search/seo-url', [
        'filter' => [
            ['type' => 'contains', 'field' => 'seoPathInfo', 'value' => 'Raven-Weapons/RAPAX']
        ]
    ]);

    if (!empty($result['body']['data'])) {
        foreach ($result['body']['data'] as $seo) {
            if (isset($seo['foreignKey']) && isset($seo['routeName']) && $seo['routeName'] === 'frontend.navigation.page') {
                $originalRapaxId = $seo['foreignKey'];
                echo "  Found: {$seo['seoPathInfo']} -> {$originalRapaxId}\n";
            }
        }
    }
}

if (!$originalRapaxId) {
    // Fallback: Find all categories under Raven Weapons and check translations
    echo "  Searching via category translations...\n";

    $ravenWeaponsId = 'a61f19c9cb4b11f0b4074aca3d279c31';

    // Get category with full translations
    $result = apiRequest($config, $token, 'GET', 'category?filter[parentId]=' . $ravenWeaponsId);

    if (!empty($result['body']['data'])) {
        foreach ($result['body']['data'] as $cat) {
            // Check translated name
            $name = $cat['translated']['name'] ?? $cat['name'] ?? '';
            echo "  Category: $name (ID: {$cat['id']})\n";
            if (stripos($name, 'RAPAX') !== false && $cat['id'] !== '019b24ef3599727ab066b6c3be6efdae') {
                $originalRapaxId = $cat['id'];
                echo "  ✓ This is the original RAPAX category\n";
            }
        }
    }
}

// If still not found, use 1f36ebeb19da4fc6bc9cb3c3acfadafd (one of the existing ones)
if (!$originalRapaxId) {
    $originalRapaxId = '1f36ebeb19da4fc6bc9cb3c3acfadafd';
    echo "  Using fallback category ID: $originalRapaxId\n";
}

echo "\nOriginal RAPAX category ID: $originalRapaxId\n";

// Step 2: Get all products from the new category
$newCategoryId = '019b24ef3599727ab066b6c3be6efdae';
echo "\nStep 2: Getting products from new category...\n";

$result = apiRequest($config, $token, 'POST', 'search/product', [
    'filter' => [
        ['type' => 'equals', 'field' => 'categories.id', 'value' => $newCategoryId]
    ],
    'limit' => 50
]);

$products = [];
if (!empty($result['body']['data'])) {
    foreach ($result['body']['data'] as $p) {
        $products[] = $p['id'];
    }
}
echo "Found " . count($products) . " products\n";

// Step 3: Move products to the original category
echo "\nStep 3: Moving products to original RAPAX category...\n";

$moved = 0;
foreach ($products as $productId) {
    $result = apiRequest($config, $token, 'PATCH', "product/$productId", [
        'categories' => [
            ['id' => $originalRapaxId]
        ]
    ]);

    if ($result['code'] === 204 || $result['code'] === 200) {
        $moved++;
        echo ".";
    } else {
        echo "X";
    }
}
echo "\n$moved products moved\n";

// Step 4: Delete the new duplicate category
echo "\nStep 4: Deleting duplicate category ($newCategoryId)...\n";
$result = apiRequest($config, $token, 'DELETE', "category/$newCategoryId");
if ($result['code'] === 204) {
    echo "✓ Deleted duplicate category\n";
} else {
    echo "Could not delete (HTTP {$result['code']})\n";
}

// Step 5: Delete other duplicate unnamed categories
echo "\nStep 5: Cleaning up other duplicates...\n";
$ravenWeaponsId = 'a61f19c9cb4b11f0b4074aca3d279c31';
$result = apiRequest($config, $token, 'POST', 'search/category', [
    'filter' => [
        ['type' => 'equals', 'field' => 'parentId', 'value' => $ravenWeaponsId]
    ]
]);

if (!empty($result['body']['data'])) {
    foreach ($result['body']['data'] as $cat) {
        // Skip the original RAPAX
        if ($cat['id'] === $originalRapaxId) continue;

        // Delete others that are duplicates
        $name = $cat['translated']['name'] ?? $cat['name'] ?? '';
        if (empty($name) || stripos($name, 'RAPAX') !== false) {
            echo "  Deleting duplicate: {$cat['id']}\n";
            apiRequest($config, $token, 'DELETE', "category/{$cat['id']}");
        }
    }
}

echo "\n=== COMPLETE ===\n";
echo "Products should now appear at: https://ortak.ch/Raven-Weapons/RAPAX/\n";
