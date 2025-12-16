<?php
/**
 * Build complete RAPAX category structure:
 *
 * Raven Weapons
 * └── RAPAX (main)
 *     ├── RAPAX (sub)
 *     │   ├── RX Sport
 *     │   ├── RX Tactical
 *     │   └── RX Compact
 *     └── Caracal Lynx
 *         ├── LYNX SPORT
 *         ├── LYNX OPEN
 *         └── LYNX COMPACT
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
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

function createCategory($config, $token, $id, $parentId, $name, $afterCategoryId = null) {
    $payload = [
        'id' => $id,
        'parentId' => $parentId,
        'name' => $name,
        'active' => true,
        'visible' => true,
        'type' => 'page',
        'displayNestedProducts' => true,
        'productAssignmentType' => 'product',
    ];

    if ($afterCategoryId) {
        $payload['afterCategoryId'] = $afterCategoryId;
    }

    $result = apiRequest($config, $token, 'POST', 'category', $payload);

    if ($result['code'] === 204 || $result['code'] === 200) {
        echo "   + Created: $name\n";
        return true;
    } elseif ($result['code'] === 400 && strpos(json_encode($result['data']), 'CONTENT__DUPLICATE') !== false) {
        // Category exists, update it
        $result = apiRequest($config, $token, 'PATCH', "category/$id", $payload);
        if ($result['code'] === 204) {
            echo "   ~ Updated: $name\n";
            return true;
        }
    }

    echo "   ! Error creating $name: HTTP {$result['code']}\n";
    return false;
}

function createSeoUrl($config, $token, $salesChannelId, $categoryId, $seoPath) {
    $seoUrlId = bin2hex(random_bytes(16));

    // First delete existing SEO URLs for this category
    $result = apiRequest($config, $token, 'POST', 'search/seo-url', [
        'filter' => [
            ['type' => 'equals', 'field' => 'foreignKey', 'value' => $categoryId]
        ],
        'limit' => 50
    ]);

    if (!empty($result['data']['data'])) {
        foreach ($result['data']['data'] as $seo) {
            apiRequest($config, $token, 'DELETE', 'seo-url/' . $seo['id'], null);
        }
    }

    // Create new SEO URL
    $result = apiRequest($config, $token, 'POST', 'seo-url', [
        'id' => $seoUrlId,
        'salesChannelId' => $salesChannelId,
        'foreignKey' => $categoryId,
        'routeName' => 'frontend.navigation.page',
        'pathInfo' => '/navigation/' . $categoryId,
        'seoPathInfo' => $seoPath,
        'isCanonical' => true,
        'isModified' => true,
    ]);

    if ($result['code'] === 204 || $result['code'] === 200) {
        echo "   + SEO URL: $seoPath\n";
        return true;
    }

    echo "   ! SEO URL error for $seoPath: HTTP {$result['code']}\n";
    return false;
}

$token = getAccessToken($config);

echo "=== Building RAPAX Category Structure ===\n\n";

// Known IDs
$ravenWeaponsId = 'a61f19c9cb4b11f0b4074aca3d279c31';
$rapaxMainId = '1f36ebeb19da4fc6bc9cb3c3acfadafd';

// Generate new category IDs
$rapaxSubId = 'rapax' . substr(md5('rapax-sub'), 0, 24);
$rxSportId = 'rxspo' . substr(md5('rx-sport'), 0, 24);
$rxTacticalId = 'rxtac' . substr(md5('rx-tactical'), 0, 24);
$rxCompactId = 'rxcom' . substr(md5('rx-compact'), 0, 24);
$caracalLynxId = 'carac' . substr(md5('caracal-lynx'), 0, 24);
$lynxSportId = 'lyspo' . substr(md5('lynx-sport'), 0, 24);
$lynxOpenId = 'lyope' . substr(md5('lynx-open'), 0, 24);
$lynxCompactId = 'lycom' . substr(md5('lynx-compact'), 0, 24);

// Get sales channel
$result = apiRequest($config, $token, 'GET', 'sales-channel?limit=1', null);
$salesChannelId = $result['data']['data'][0]['id'] ?? null;
echo "Sales Channel ID: $salesChannelId\n\n";

// Step 1: Ensure main RAPAX is under Raven Weapons
echo "1. Setting up main RAPAX category...\n";
$result = apiRequest($config, $token, 'PATCH', "category/$rapaxMainId", [
    'parentId' => $ravenWeaponsId,
    'name' => 'RAPAX',
    'active' => true,
    'visible' => true,
    'type' => 'page',
    'displayNestedProducts' => true,
    'productAssignmentType' => 'product',
    'cmsPageId' => null,
]);
echo "   Main RAPAX: " . ($result['code'] === 204 ? "OK" : "HTTP {$result['code']}") . "\n";

// Step 2: Create RAPAX subcategory (under main RAPAX)
echo "\n2. Creating RAPAX subcategory...\n";
createCategory($config, $token, $rapaxSubId, $rapaxMainId, 'RAPAX');

// Step 3: Create RX subcategories under RAPAX sub
echo "\n3. Creating RX subcategories...\n";
createCategory($config, $token, $rxSportId, $rapaxSubId, 'RX Sport');
createCategory($config, $token, $rxTacticalId, $rapaxSubId, 'RX Tactical', $rxSportId);
createCategory($config, $token, $rxCompactId, $rapaxSubId, 'RX Compact', $rxTacticalId);

// Step 4: Create Caracal Lynx subcategory (under main RAPAX)
echo "\n4. Creating Caracal Lynx subcategory...\n";
createCategory($config, $token, $caracalLynxId, $rapaxMainId, 'Caracal Lynx', $rapaxSubId);

// Step 5: Create LYNX subcategories under Caracal Lynx
echo "\n5. Creating LYNX subcategories...\n";
createCategory($config, $token, $lynxSportId, $caracalLynxId, 'LYNX SPORT');
createCategory($config, $token, $lynxOpenId, $caracalLynxId, 'LYNX OPEN', $lynxSportId);
createCategory($config, $token, $lynxCompactId, $caracalLynxId, 'LYNX COMPACT', $lynxOpenId);

// Step 6: Get all RAPAX/CARACAL products
echo "\n6. Getting all products...\n";
$result = apiRequest($config, $token, 'POST', 'search/product', [
    'filter' => [
        [
            'type' => 'multi',
            'operator' => 'OR',
            'queries' => [
                ['type' => 'contains', 'field' => 'name', 'value' => 'RAPAX'],
                ['type' => 'contains', 'field' => 'name', 'value' => 'CARACAL'],
            ]
        ]
    ],
    'limit' => 100
]);

$products = $result['data']['data'] ?? [];
echo "   Found " . count($products) . " products\n";

// Step 7: Assign products to correct categories based on name
echo "\n7. Assigning products to categories...\n";

$categoryAssignments = [];

foreach ($products as $product) {
    $productId = $product['id'];
    $name = $product['name'] ?? '';

    // Determine which categories this product belongs to
    $productCategories = [$rapaxMainId]; // All products in main RAPAX

    if (stripos($name, 'RAPAX Sport') !== false) {
        $productCategories[] = $rapaxSubId;
        $productCategories[] = $rxSportId;
        echo "   $name -> RX Sport\n";
    } elseif (stripos($name, 'RAPAX Tactical') !== false) {
        $productCategories[] = $rapaxSubId;
        $productCategories[] = $rxTacticalId;
        echo "   $name -> RX Tactical\n";
    } elseif (stripos($name, 'RAPAX II COMPACT') !== false || stripos($name, 'RAPAX Compact') !== false) {
        $productCategories[] = $rapaxSubId;
        $productCategories[] = $rxCompactId;
        echo "   $name -> RX Compact\n";
    } elseif (stripos($name, 'CARACAL Lynx Sport') !== false || stripos($name, 'Caracal Lynx Sport') !== false) {
        $productCategories[] = $caracalLynxId;
        $productCategories[] = $lynxSportId;
        echo "   $name -> LYNX SPORT\n";
    } elseif (stripos($name, 'CARACAL Lynx Open') !== false || stripos($name, 'Caracal Lynx Open') !== false) {
        $productCategories[] = $caracalLynxId;
        $productCategories[] = $lynxOpenId;
        echo "   $name -> LYNX OPEN\n";
    } elseif (stripos($name, 'CARACAL Lynx Compact') !== false || stripos($name, 'Caracal Lynx Compact') !== false) {
        $productCategories[] = $caracalLynxId;
        $productCategories[] = $lynxCompactId;
        echo "   $name -> LYNX COMPACT\n";
    } else {
        echo "   $name -> Main RAPAX only\n";
    }

    foreach ($productCategories as $catId) {
        $categoryAssignments[] = [
            'productId' => $productId,
            'categoryId' => $catId,
        ];
    }
}

// Batch assign using sync API
echo "\n8. Saving category assignments...\n";

$result = apiRequest($config, $token, 'POST', '_action/sync', [
    [
        'action' => 'upsert',
        'entity' => 'product_category',
        'payload' => $categoryAssignments
    ]
]);

echo "   Sync API: HTTP {$result['code']}\n";
if ($result['code'] === 200) {
    echo "   Assigned " . count($categoryAssignments) . " product-category relationships\n";
}

// Step 9: Create SEO URLs
echo "\n9. Creating SEO URLs...\n";

$seoUrls = [
    [$rapaxMainId, 'Raven-Weapons/RAPAX/'],
    [$rapaxSubId, 'Raven-Weapons/RAPAX/RAPAX/'],
    [$rxSportId, 'Raven-Weapons/RAPAX/RAPAX/RX-Sport/'],
    [$rxTacticalId, 'Raven-Weapons/RAPAX/RAPAX/RX-Tactical/'],
    [$rxCompactId, 'Raven-Weapons/RAPAX/RAPAX/RX-Compact/'],
    [$caracalLynxId, 'Raven-Weapons/RAPAX/Caracal-Lynx/'],
    [$lynxSportId, 'Raven-Weapons/RAPAX/Caracal-Lynx/LYNX-SPORT/'],
    [$lynxOpenId, 'Raven-Weapons/RAPAX/Caracal-Lynx/LYNX-OPEN/'],
    [$lynxCompactId, 'Raven-Weapons/RAPAX/Caracal-Lynx/LYNX-COMPACT/'],
];

foreach ($seoUrls as [$catId, $seoPath]) {
    createSeoUrl($config, $token, $salesChannelId, $catId, $seoPath);
}

// Step 10: Rebuild indexes
echo "\n10. Rebuilding indexes...\n";
$result = apiRequest($config, $token, 'POST', '_action/index', ['skip' => []]);
echo "   Index: HTTP {$result['code']}\n";

// Step 11: Clear cache
echo "\n11. Clearing cache...\n";
$ch = curl_init($config['shopware_url'] . '/api/_action/cache');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
]);
curl_exec($ch);
curl_close($ch);
echo "   Cache cleared\n";

echo "\n=== Done! ===\n\n";
echo "Category Structure:\n";
echo "Raven Weapons\n";
echo "└── RAPAX: https://ortak.ch/Raven-Weapons/RAPAX/\n";
echo "    ├── RAPAX: https://ortak.ch/Raven-Weapons/RAPAX/RAPAX/\n";
echo "    │   ├── RX Sport: https://ortak.ch/Raven-Weapons/RAPAX/RAPAX/RX-Sport/\n";
echo "    │   ├── RX Tactical: https://ortak.ch/Raven-Weapons/RAPAX/RAPAX/RX-Tactical/\n";
echo "    │   └── RX Compact: https://ortak.ch/Raven-Weapons/RAPAX/RAPAX/RX-Compact/\n";
echo "    └── Caracal Lynx: https://ortak.ch/Raven-Weapons/RAPAX/Caracal-Lynx/\n";
echo "        ├── LYNX SPORT: https://ortak.ch/Raven-Weapons/RAPAX/Caracal-Lynx/LYNX-SPORT/\n";
echo "        ├── LYNX OPEN: https://ortak.ch/Raven-Weapons/RAPAX/Caracal-Lynx/LYNX-OPEN/\n";
echo "        └── LYNX COMPACT: https://ortak.ch/Raven-Weapons/RAPAX/Caracal-Lynx/LYNX-COMPACT/\n";
