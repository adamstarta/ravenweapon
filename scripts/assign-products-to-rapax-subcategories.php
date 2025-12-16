<?php
/**
 * Assign products to correct RAPAX subcategories
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

echo "=== Assigning Products to RAPAX Subcategories ===\n\n";

// Known category IDs - get from last script run or search
$rapaxMainId = '1f36ebeb19da4fc6bc9cb3c3acfadafd';

// First, find the subcategories we created
echo "1. Finding subcategories...\n";

$result = apiRequest($config, $token, 'POST', 'search/category', [
    'filter' => [
        ['type' => 'equals', 'field' => 'parentId', 'value' => $rapaxMainId]
    ],
    'limit' => 20
]);

$rapaxSubId = null;
$caracalLynxId = null;

if (!empty($result['data']['data'])) {
    foreach ($result['data']['data'] as $cat) {
        $name = $cat['name'] ?? '';
        echo "   Found: $name (ID: {$cat['id']})\n";
        if ($name === 'RAPAX') {
            $rapaxSubId = $cat['id'];
        } elseif ($name === 'Caracal Lynx') {
            $caracalLynxId = $cat['id'];
        }
    }
}

// Find RX subcategories
$rxSportId = $rxTacticalId = $rxCompactId = null;
if ($rapaxSubId) {
    $result = apiRequest($config, $token, 'POST', 'search/category', [
        'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $rapaxSubId]],
        'limit' => 20
    ]);
    if (!empty($result['data']['data'])) {
        foreach ($result['data']['data'] as $cat) {
            $name = $cat['name'] ?? '';
            echo "   Found: $name (ID: {$cat['id']})\n";
            if ($name === 'RX Sport') $rxSportId = $cat['id'];
            elseif ($name === 'RX Tactical') $rxTacticalId = $cat['id'];
            elseif ($name === 'RX Compact') $rxCompactId = $cat['id'];
        }
    }
}

// Find LYNX subcategories
$lynxSportId = $lynxOpenId = $lynxCompactId = null;
if ($caracalLynxId) {
    $result = apiRequest($config, $token, 'POST', 'search/category', [
        'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $caracalLynxId]],
        'limit' => 20
    ]);
    if (!empty($result['data']['data'])) {
        foreach ($result['data']['data'] as $cat) {
            $name = $cat['name'] ?? '';
            echo "   Found: $name (ID: {$cat['id']})\n";
            if ($name === 'LYNX SPORT') $lynxSportId = $cat['id'];
            elseif ($name === 'LYNX OPEN') $lynxOpenId = $cat['id'];
            elseif ($name === 'LYNX COMPACT') $lynxCompactId = $cat['id'];
        }
    }
}

echo "\nCategory IDs:\n";
echo "  Main RAPAX: $rapaxMainId\n";
echo "  RAPAX Sub: $rapaxSubId\n";
echo "  RX Sport: $rxSportId\n";
echo "  RX Tactical: $rxTacticalId\n";
echo "  RX Compact: $rxCompactId\n";
echo "  Caracal Lynx: $caracalLynxId\n";
echo "  LYNX SPORT: $lynxSportId\n";
echo "  LYNX OPEN: $lynxOpenId\n";
echo "  LYNX COMPACT: $lynxCompactId\n";

// Step 2: Get all products using search API
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
    $name = $product['name'] ?? $product['translated']['name'] ?? '';

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

// Step 4: Save assignments using sync API
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
    if ($result['code'] !== 200) {
        echo "   Error: " . json_encode($result['data']) . "\n";
    }
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
