<?php
/**
 * Final product assignment v2 - fetch product names individually
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

function apiGet($config, $token, $endpoint) {
    $ch = curl_init($config['shopware_url'] . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function apiPost($config, $token, $endpoint, $data) {
    $ch = curl_init($config['shopware_url'] . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

$token = getAccessToken($config);

echo "=== Final Product Assignment v2 ===\n\n";

// Category IDs
$rapaxMainId = '1f36ebeb19da4fc6bc9cb3c3acfadafd';
$rapaxSubId = '95a7cf1575ddc0219d8f11484ab0cbeb';
$rxSportId = '34c00eca0b38ba3aa4ae483722859b4e';
$rxTacticalId = 'fa470225519fd7d666f28d89caf25c8d';
$rxCompactId = 'ea2e04075bc0d5c50cfb0a4b52930401';
$caracalLynxId = '2b3fdb3f3dcc00eacf9c9683d5d22c6a';
$lynxSportId = '66ed5338a8574c803e01da3cb9e1f2d4';
$lynxOpenId = '7048c95bf71dd4802adb7846617b4503';
$lynxCompactId = 'da98c38ad3e48c6965ff0e93769115d4';

// Get all products using simple GET with includes
echo "1. Getting all products...\n";

$result = apiGet($config, $token, 'product?limit=200');
$allProducts = $result['data'] ?? [];

// Filter for RAPAX/CARACAL
$products = [];
foreach ($allProducts as $p) {
    $name = $p['attributes']['name'] ?? $p['attributes']['translated']['name'] ?? '';
    if (stripos($name, 'RAPAX') !== false || stripos($name, 'CARACAL') !== false) {
        $products[] = [
            'id' => $p['id'],
            'name' => $name
        ];
    }
}

echo "   Found " . count($products) . " RAPAX/CARACAL products\n";

// Build category assignments
echo "\n2. Building category assignments...\n";

$categoryAssignments = [];
$stats = [
    'RX Sport' => 0,
    'RX Tactical' => 0,
    'RX Compact' => 0,
    'LYNX SPORT' => 0,
    'LYNX OPEN' => 0,
    'LYNX COMPACT' => 0,
];

foreach ($products as $product) {
    $productId = $product['id'];
    $name = $product['name'];

    // All products go to main RAPAX
    $productCategories = [$rapaxMainId];

    // Determine specific subcategory
    if (stripos($name, 'RAPAX Sport') !== false) {
        $productCategories[] = $rapaxSubId;
        $productCategories[] = $rxSportId;
        $stats['RX Sport']++;
        echo "   $name -> RX Sport\n";
    } elseif (stripos($name, 'RAPAX Tactical') !== false) {
        $productCategories[] = $rapaxSubId;
        $productCategories[] = $rxTacticalId;
        $stats['RX Tactical']++;
        echo "   $name -> RX Tactical\n";
    } elseif (stripos($name, 'RAPAX II COMPACT') !== false) {
        $productCategories[] = $rapaxSubId;
        $productCategories[] = $rxCompactId;
        $stats['RX Compact']++;
        echo "   $name -> RX Compact\n";
    } elseif (stripos($name, 'CARACAL') !== false && stripos($name, 'Sport') !== false) {
        $productCategories[] = $caracalLynxId;
        $productCategories[] = $lynxSportId;
        $stats['LYNX SPORT']++;
        echo "   $name -> LYNX SPORT\n";
    } elseif (stripos($name, 'CARACAL') !== false && stripos($name, 'Open') !== false) {
        $productCategories[] = $caracalLynxId;
        $productCategories[] = $lynxOpenId;
        $stats['LYNX OPEN']++;
        echo "   $name -> LYNX OPEN\n";
    } elseif (stripos($name, 'CARACAL') !== false && stripos($name, 'Compact') !== false) {
        $productCategories[] = $caracalLynxId;
        $productCategories[] = $lynxCompactId;
        $stats['LYNX COMPACT']++;
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

echo "\n   Stats:\n";
foreach ($stats as $cat => $count) {
    echo "   - $cat: $count products\n";
}

// Save assignments
echo "\n3. Saving " . count($categoryAssignments) . " category assignments...\n";

$result = apiPost($config, $token, '_action/sync', [
    [
        'action' => 'upsert',
        'entity' => 'product_category',
        'payload' => $categoryAssignments
    ]
]);

echo "   Sync API: HTTP {$result['code']}\n";

// Clear cache
echo "\n4. Clearing cache...\n";
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
