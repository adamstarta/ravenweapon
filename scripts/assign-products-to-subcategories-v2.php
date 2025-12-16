<?php
/**
 * Assign products to correct RAPAX subcategories v2 - fixed API data reading
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

$token = getAccessToken($config);

echo "=== Assigning Products to RAPAX Subcategories v2 ===\n\n";

$rapaxMainId = '1f36ebeb19da4fc6bc9cb3c3acfadafd';

// Step 1: Get ALL categories under main RAPAX and find by name
echo "1. Finding all subcategories...\n";

// Get direct children of main RAPAX
$result = apiRequest($config, $token, 'GET', "category/$rapaxMainId?associations[children][associations][children][]=[]", null);

$rapaxSubId = null;
$caracalLynxId = null;
$rxSportId = null;
$rxTacticalId = null;
$rxCompactId = null;
$lynxSportId = null;
$lynxOpenId = null;
$lynxCompactId = null;

if (!empty($result['data']['data']['children'])) {
    foreach ($result['data']['data']['children'] as $child) {
        $childId = $child['id'];
        $childName = $child['attributes']['name'] ?? $child['attributes']['translated']['name'] ?? '';

        echo "   Level 1: $childName (ID: $childId)\n";

        if ($childName === 'RAPAX') {
            $rapaxSubId = $childId;

            // Check grandchildren
            if (!empty($child['children'])) {
                foreach ($child['children'] as $grandchild) {
                    $gcId = $grandchild['id'];
                    $gcName = $grandchild['attributes']['name'] ?? $grandchild['attributes']['translated']['name'] ?? '';
                    echo "      Level 2: $gcName (ID: $gcId)\n";

                    if ($gcName === 'RX Sport') $rxSportId = $gcId;
                    elseif ($gcName === 'RX Tactical') $rxTacticalId = $gcId;
                    elseif ($gcName === 'RX Compact') $rxCompactId = $gcId;
                }
            }
        } elseif ($childName === 'Caracal Lynx') {
            $caracalLynxId = $childId;

            if (!empty($child['children'])) {
                foreach ($child['children'] as $grandchild) {
                    $gcId = $grandchild['id'];
                    $gcName = $grandchild['attributes']['name'] ?? $grandchild['attributes']['translated']['name'] ?? '';
                    echo "      Level 2: $gcName (ID: $gcId)\n";

                    if ($gcName === 'LYNX SPORT') $lynxSportId = $gcId;
                    elseif ($gcName === 'LYNX OPEN') $lynxOpenId = $gcId;
                    elseif ($gcName === 'LYNX COMPACT') $lynxCompactId = $gcId;
                }
            }
        }
    }
}

// If not found via associations, search directly
if (!$rapaxSubId || !$caracalLynxId) {
    echo "\n   Searching categories by name...\n";

    // Search for all categories containing our names
    $searches = [
        'RAPAX' => ['rapaxSub' => true],
        'Caracal Lynx' => ['caracalLynx' => true],
        'RX Sport' => ['rxSport' => true],
        'RX Tactical' => ['rxTactical' => true],
        'RX Compact' => ['rxCompact' => true],
        'LYNX SPORT' => ['lynxSport' => true],
        'LYNX OPEN' => ['lynxOpen' => true],
        'LYNX COMPACT' => ['lynxCompact' => true],
    ];

    $result = apiRequest($config, $token, 'POST', 'search/category', [
        'filter' => [
            [
                'type' => 'multi',
                'operator' => 'OR',
                'queries' => [
                    ['type' => 'equals', 'field' => 'name', 'value' => 'RAPAX'],
                    ['type' => 'equals', 'field' => 'name', 'value' => 'Caracal Lynx'],
                    ['type' => 'equals', 'field' => 'name', 'value' => 'RX Sport'],
                    ['type' => 'equals', 'field' => 'name', 'value' => 'RX Tactical'],
                    ['type' => 'equals', 'field' => 'name', 'value' => 'RX Compact'],
                    ['type' => 'equals', 'field' => 'name', 'value' => 'LYNX SPORT'],
                    ['type' => 'equals', 'field' => 'name', 'value' => 'LYNX OPEN'],
                    ['type' => 'equals', 'field' => 'name', 'value' => 'LYNX COMPACT'],
                ]
            ]
        ],
        'limit' => 50
    ]);

    if (!empty($result['data']['data'])) {
        foreach ($result['data']['data'] as $cat) {
            $catId = $cat['id'];
            $catName = $cat['name'] ?? '';
            $parentId = $cat['parentId'] ?? '';

            echo "   Found: $catName (ID: $catId, Parent: $parentId)\n";

            // Only match if parent is correct
            if ($catName === 'RAPAX' && $parentId === $rapaxMainId) {
                $rapaxSubId = $catId;
            } elseif ($catName === 'Caracal Lynx' && $parentId === $rapaxMainId) {
                $caracalLynxId = $catId;
            } elseif ($catName === 'RX Sport') {
                $rxSportId = $catId;
            } elseif ($catName === 'RX Tactical') {
                $rxTacticalId = $catId;
            } elseif ($catName === 'RX Compact') {
                $rxCompactId = $catId;
            } elseif ($catName === 'LYNX SPORT') {
                $lynxSportId = $catId;
            } elseif ($catName === 'LYNX OPEN') {
                $lynxOpenId = $catId;
            } elseif ($catName === 'LYNX COMPACT') {
                $lynxCompactId = $catId;
            }
        }
    }
}

echo "\nCategory IDs Found:\n";
echo "  Main RAPAX: $rapaxMainId\n";
echo "  RAPAX Sub: " . ($rapaxSubId ?? 'NOT FOUND') . "\n";
echo "  RX Sport: " . ($rxSportId ?? 'NOT FOUND') . "\n";
echo "  RX Tactical: " . ($rxTacticalId ?? 'NOT FOUND') . "\n";
echo "  RX Compact: " . ($rxCompactId ?? 'NOT FOUND') . "\n";
echo "  Caracal Lynx: " . ($caracalLynxId ?? 'NOT FOUND') . "\n";
echo "  LYNX SPORT: " . ($lynxSportId ?? 'NOT FOUND') . "\n";
echo "  LYNX OPEN: " . ($lynxOpenId ?? 'NOT FOUND') . "\n";
echo "  LYNX COMPACT: " . ($lynxCompactId ?? 'NOT FOUND') . "\n";

// Step 2: Get all products
echo "\n2. Getting all RAPAX/CARACAL products...\n";

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

// Step 3: Build category assignments
echo "\n3. Assigning products to categories...\n";

$categoryAssignments = [];

foreach ($products as $product) {
    $productId = $product['id'];
    // Product name can be in different places depending on API version
    $name = $product['name'] ?? $product['translated']['name'] ?? $product['attributes']['name'] ?? $product['attributes']['translated']['name'] ?? '';

    if (empty($name)) {
        // Try to get from product directly
        $pResult = apiRequest($config, $token, 'GET', "product/$productId", null);
        $name = $pResult['data']['data']['attributes']['name'] ?? $pResult['data']['data']['attributes']['translated']['name'] ?? 'Unknown';
    }

    echo "   $name\n";

    // All products go to main RAPAX
    $productCategories = [$rapaxMainId];

    // Determine specific subcategory based on name
    if (stripos($name, 'RAPAX Sport') !== false) {
        if ($rapaxSubId) $productCategories[] = $rapaxSubId;
        if ($rxSportId) $productCategories[] = $rxSportId;
        echo "      -> RX Sport\n";
    } elseif (stripos($name, 'RAPAX Tactical') !== false) {
        if ($rapaxSubId) $productCategories[] = $rapaxSubId;
        if ($rxTacticalId) $productCategories[] = $rxTacticalId;
        echo "      -> RX Tactical\n";
    } elseif (stripos($name, 'RAPAX II COMPACT') !== false) {
        if ($rapaxSubId) $productCategories[] = $rapaxSubId;
        if ($rxCompactId) $productCategories[] = $rxCompactId;
        echo "      -> RX Compact\n";
    } elseif (stripos($name, 'CARACAL') !== false && stripos($name, 'Sport') !== false) {
        if ($caracalLynxId) $productCategories[] = $caracalLynxId;
        if ($lynxSportId) $productCategories[] = $lynxSportId;
        echo "      -> LYNX SPORT\n";
    } elseif (stripos($name, 'CARACAL') !== false && stripos($name, 'Open') !== false) {
        if ($caracalLynxId) $productCategories[] = $caracalLynxId;
        if ($lynxOpenId) $productCategories[] = $lynxOpenId;
        echo "      -> LYNX OPEN\n";
    } elseif (stripos($name, 'CARACAL') !== false && stripos($name, 'Compact') !== false) {
        if ($caracalLynxId) $productCategories[] = $caracalLynxId;
        if ($lynxCompactId) $productCategories[] = $lynxCompactId;
        echo "      -> LYNX COMPACT\n";
    }

    foreach ($productCategories as $catId) {
        if ($catId) {
            $categoryAssignments[] = [
                'productId' => $productId,
                'categoryId' => $catId,
            ];
        }
    }
}

// Step 4: Save assignments
echo "\n4. Saving " . count($categoryAssignments) . " category assignments...\n";

if (count($categoryAssignments) > 0) {
    $result = apiRequest($config, $token, 'POST', '_action/sync', [
        [
            'action' => 'upsert',
            'entity' => 'product_category',
            'payload' => $categoryAssignments
        ]
    ]);

    echo "   Sync API: HTTP {$result['code']}\n";
}

// Step 5: Clear cache
echo "\n5. Clearing cache...\n";
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
