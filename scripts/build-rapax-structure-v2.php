<?php
/**
 * Build complete RAPAX category structure v2 - with proper UUIDs
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
    return ['code' => $httpCode, 'data' => json_decode($response, true), 'raw' => $response];
}

function generateUuid() {
    return bin2hex(random_bytes(16));
}

$token = getAccessToken($config);

echo "=== Building RAPAX Category Structure v2 ===\n\n";

// Known IDs
$ravenWeaponsId = 'a61f19c9cb4b11f0b4074aca3d279c31';
$rapaxMainId = '1f36ebeb19da4fc6bc9cb3c3acfadafd';

// Generate proper UUIDs for new categories
$rapaxSubId = generateUuid();
$rxSportId = generateUuid();
$rxTacticalId = generateUuid();
$rxCompactId = generateUuid();
$caracalLynxId = generateUuid();
$lynxSportId = generateUuid();
$lynxOpenId = generateUuid();
$lynxCompactId = generateUuid();

echo "Generated Category IDs:\n";
echo "  RAPAX Sub: $rapaxSubId\n";
echo "  RX Sport: $rxSportId\n";
echo "  RX Tactical: $rxTacticalId\n";
echo "  RX Compact: $rxCompactId\n";
echo "  Caracal Lynx: $caracalLynxId\n";
echo "  LYNX SPORT: $lynxSportId\n";
echo "  LYNX OPEN: $lynxOpenId\n";
echo "  LYNX COMPACT: $lynxCompactId\n\n";

// Get sales channel
$result = apiRequest($config, $token, 'GET', 'sales-channel?limit=1', null);
$salesChannelId = $result['data']['data'][0]['id'] ?? null;
echo "Sales Channel ID: $salesChannelId\n\n";

// Step 1: Use sync API to create all categories at once
echo "1. Creating all categories using sync API...\n";

$categoryPayload = [
    // Main RAPAX update
    [
        'id' => $rapaxMainId,
        'parentId' => $ravenWeaponsId,
        'name' => 'RAPAX',
        'active' => true,
        'visible' => true,
        'type' => 'page',
        'displayNestedProducts' => true,
        'productAssignmentType' => 'product',
    ],
    // RAPAX subcategory
    [
        'id' => $rapaxSubId,
        'parentId' => $rapaxMainId,
        'name' => 'RAPAX',
        'active' => true,
        'visible' => true,
        'type' => 'page',
        'displayNestedProducts' => true,
        'productAssignmentType' => 'product',
    ],
    // RX Sport
    [
        'id' => $rxSportId,
        'parentId' => $rapaxSubId,
        'name' => 'RX Sport',
        'active' => true,
        'visible' => true,
        'type' => 'page',
        'displayNestedProducts' => true,
        'productAssignmentType' => 'product',
    ],
    // RX Tactical
    [
        'id' => $rxTacticalId,
        'parentId' => $rapaxSubId,
        'name' => 'RX Tactical',
        'active' => true,
        'visible' => true,
        'type' => 'page',
        'displayNestedProducts' => true,
        'productAssignmentType' => 'product',
    ],
    // RX Compact
    [
        'id' => $rxCompactId,
        'parentId' => $rapaxSubId,
        'name' => 'RX Compact',
        'active' => true,
        'visible' => true,
        'type' => 'page',
        'displayNestedProducts' => true,
        'productAssignmentType' => 'product',
    ],
    // Caracal Lynx
    [
        'id' => $caracalLynxId,
        'parentId' => $rapaxMainId,
        'name' => 'Caracal Lynx',
        'active' => true,
        'visible' => true,
        'type' => 'page',
        'displayNestedProducts' => true,
        'productAssignmentType' => 'product',
    ],
    // LYNX SPORT
    [
        'id' => $lynxSportId,
        'parentId' => $caracalLynxId,
        'name' => 'LYNX SPORT',
        'active' => true,
        'visible' => true,
        'type' => 'page',
        'displayNestedProducts' => true,
        'productAssignmentType' => 'product',
    ],
    // LYNX OPEN
    [
        'id' => $lynxOpenId,
        'parentId' => $caracalLynxId,
        'name' => 'LYNX OPEN',
        'active' => true,
        'visible' => true,
        'type' => 'page',
        'displayNestedProducts' => true,
        'productAssignmentType' => 'product',
    ],
    // LYNX COMPACT
    [
        'id' => $lynxCompactId,
        'parentId' => $caracalLynxId,
        'name' => 'LYNX COMPACT',
        'active' => true,
        'visible' => true,
        'type' => 'page',
        'displayNestedProducts' => true,
        'productAssignmentType' => 'product',
    ],
];

$result = apiRequest($config, $token, 'POST', '_action/sync', [
    [
        'action' => 'upsert',
        'entity' => 'category',
        'payload' => $categoryPayload
    ]
]);

echo "   Sync API: HTTP {$result['code']}\n";
if ($result['code'] !== 200) {
    echo "   Error: " . $result['raw'] . "\n";
}

// Step 2: Get all products with proper name field
echo "\n2. Getting all RAPAX/CARACAL products...\n";

$result = apiRequest($config, $token, 'GET', 'product?filter[name][type]=contains&filter[name][value]=RAPAX&limit=50', null);
$rapaxProducts = $result['data']['data'] ?? [];

$result = apiRequest($config, $token, 'GET', 'product?filter[name][type]=contains&filter[name][value]=CARACAL&limit=50', null);
$caracalProducts = $result['data']['data'] ?? [];

// Merge and dedupe
$allProducts = [];
foreach (array_merge($rapaxProducts, $caracalProducts) as $p) {
    $allProducts[$p['id']] = $p;
}

echo "   Found " . count($allProducts) . " products\n";

// Step 3: Assign products to categories
echo "\n3. Assigning products to categories...\n";

$categoryAssignments = [];

foreach ($allProducts as $product) {
    $productId = $product['id'];
    $name = $product['attributes']['name'] ?? $product['attributes']['translated']['name'] ?? '';

    echo "   Product: $name\n";

    // All products go to main RAPAX
    $productCategories = [$rapaxMainId];

    // Determine specific subcategory
    if (stripos($name, 'RAPAX Sport') !== false) {
        $productCategories[] = $rapaxSubId;
        $productCategories[] = $rxSportId;
        echo "      -> RX Sport\n";
    } elseif (stripos($name, 'RAPAX Tactical') !== false) {
        $productCategories[] = $rapaxSubId;
        $productCategories[] = $rxTacticalId;
        echo "      -> RX Tactical\n";
    } elseif (stripos($name, 'RAPAX II COMPACT') !== false || stripos($name, 'RAPAX Compact') !== false) {
        $productCategories[] = $rapaxSubId;
        $productCategories[] = $rxCompactId;
        echo "      -> RX Compact\n";
    } elseif (stripos($name, 'CARACAL') !== false && stripos($name, 'Sport') !== false) {
        $productCategories[] = $caracalLynxId;
        $productCategories[] = $lynxSportId;
        echo "      -> LYNX SPORT\n";
    } elseif (stripos($name, 'CARACAL') !== false && stripos($name, 'Open') !== false) {
        $productCategories[] = $caracalLynxId;
        $productCategories[] = $lynxOpenId;
        echo "      -> LYNX OPEN\n";
    } elseif (stripos($name, 'CARACAL') !== false && stripos($name, 'Compact') !== false) {
        $productCategories[] = $caracalLynxId;
        $productCategories[] = $lynxCompactId;
        echo "      -> LYNX COMPACT\n";
    }

    foreach ($productCategories as $catId) {
        $categoryAssignments[] = [
            'productId' => $productId,
            'categoryId' => $catId,
        ];
    }
}

// Save assignments
echo "\n4. Saving category assignments...\n";

$result = apiRequest($config, $token, 'POST', '_action/sync', [
    [
        'action' => 'upsert',
        'entity' => 'product_category',
        'payload' => $categoryAssignments
    ]
]);

echo "   Sync API: HTTP {$result['code']}\n";
echo "   Assigned " . count($categoryAssignments) . " relationships\n";

// Step 5: Create SEO URLs
echo "\n5. Creating SEO URLs...\n";

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
    // Delete existing
    $result = apiRequest($config, $token, 'POST', 'search/seo-url', [
        'filter' => [['type' => 'equals', 'field' => 'foreignKey', 'value' => $catId]],
        'limit' => 50
    ]);
    if (!empty($result['data']['data'])) {
        foreach ($result['data']['data'] as $seo) {
            apiRequest($config, $token, 'DELETE', 'seo-url/' . $seo['id'], null);
        }
    }

    // Create new
    $seoUrlId = generateUuid();
    $result = apiRequest($config, $token, 'POST', 'seo-url', [
        'id' => $seoUrlId,
        'salesChannelId' => $salesChannelId,
        'foreignKey' => $catId,
        'routeName' => 'frontend.navigation.page',
        'pathInfo' => '/navigation/' . $catId,
        'seoPathInfo' => $seoPath,
        'isCanonical' => true,
        'isModified' => true,
    ]);

    if ($result['code'] === 204 || $result['code'] === 200) {
        echo "   + $seoPath\n";
    } else {
        echo "   ! $seoPath: HTTP {$result['code']}\n";
    }
}

// Step 6: Clear cache
echo "\n6. Clearing cache...\n";
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
echo "\nTest URLs:\n";
foreach ($seoUrls as [$catId, $seoPath]) {
    echo "  https://ortak.ch/$seoPath\n";
}
