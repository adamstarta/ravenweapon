<?php
/**
 * Reorganize navigation structure:
 * 1. Create Sturmgewehre (Assault Rifles) under Raven Weapons
 * 2. Move RAPAX to top-level (same level as Raven Weapons)
 * 3. Reorder RAPAX subcategories (RAPAX sub first, Caracal Lynx second)
 * 4. Assign Raven rifle products to Sturmgewehre
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

function apiPatch($config, $token, $endpoint, $data) {
    $ch = curl_init($config['shopware_url'] . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
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

echo "=== Reorganizing Navigation Structure ===\n\n";

// Category IDs
$catalogueRootId = '0191c12ccf00712e8c0cf733425fe315';  // Catalogue #1 (root)
$ravenWeaponsId = 'a61f19c9cb4b11f0b4074aca3d279c31';   // Raven Weapons
$rapaxMainId = '1f36ebeb19da4fc6bc9cb3c3acfadafd';      // RAPAX (currently under Raven Weapons)
$rapaxSubId = '95a7cf1575ddc0219d8f11484ab0cbeb';       // RAPAX (sub)
$caracalLynxId = '2b3fdb3f3dcc00eacf9c9683d5d22c6a';    // Caracal Lynx

// Generate UUID for new Sturmgewehre category
$sturmgewehreId = bin2hex(random_bytes(16));

echo "1. Creating Sturmgewehre (Assault Rifles) category under Raven Weapons...\n";

$result = apiPost($config, $token, 'category', [
    'id' => $sturmgewehreId,
    'parentId' => $ravenWeaponsId,
    'name' => 'Sturmgewehre',
    'active' => true,
    'visible' => true,
    'type' => 'page',
    'displayNestedProducts' => true,
    'productAssignmentType' => 'product',
    'afterCategoryId' => null,  // Put it first
]);

echo "   HTTP: {$result['code']}\n";
if ($result['code'] == 204 || $result['code'] == 200) {
    echo "   Created! ID: $sturmgewehreId\n";
} else {
    echo "   Response: " . json_encode($result['data']) . "\n";
}

echo "\n2. Moving RAPAX to top-level (same level as Raven Weapons)...\n";

$result = apiPatch($config, $token, "category/$rapaxMainId", [
    'parentId' => $catalogueRootId,  // Move to root level
    'afterCategoryId' => $ravenWeaponsId,  // Put after Raven Weapons
]);

echo "   HTTP: {$result['code']}\n";

echo "\n3. Reordering RAPAX subcategories (RAPAX sub first, Caracal Lynx second)...\n";

// Set RAPAX sub to come first (no afterCategoryId)
$result = apiPatch($config, $token, "category/$rapaxSubId", [
    'afterCategoryId' => null,  // First position
]);
echo "   RAPAX sub: HTTP {$result['code']}\n";

// Set Caracal Lynx to come after RAPAX sub
$result = apiPatch($config, $token, "category/$caracalLynxId", [
    'afterCategoryId' => $rapaxSubId,  // After RAPAX sub
]);
echo "   Caracal Lynx: HTTP {$result['code']}\n";

echo "\n4. Finding Raven rifle products to assign to Sturmgewehre...\n";

// Search for Raven rifle products
$result = apiPost($config, $token, 'search/product', [
    'filter' => [
        [
            'type' => 'multi',
            'operator' => 'OR',
            'queries' => [
                ['type' => 'contains', 'field' => 'name', 'value' => 'RAVEN'],
                ['type' => 'contains', 'field' => 'name', 'value' => 'Raven'],
            ]
        ]
    ],
    'limit' => 50
]);

$ravenProducts = [];
if (!empty($result['data'])) {
    foreach ($result['data'] as $product) {
        $name = $product['attributes']['name'] ?? '';
        // Only include actual Raven rifles (not Caliber Kits)
        if (stripos($name, 'RAVEN') !== false && stripos($name, 'CALIBER KIT') === false) {
            $ravenProducts[] = [
                'id' => $product['id'],
                'name' => $name
            ];
            echo "   Found: $name\n";
        }
    }
}

echo "\n5. Assigning " . count($ravenProducts) . " Raven rifle products to Sturmgewehre...\n";

if (count($ravenProducts) > 0) {
    $payload = [];
    foreach ($ravenProducts as $product) {
        $payload[] = [
            'productId' => $product['id'],
            'categoryId' => $sturmgewehreId,
        ];
    }

    $result = apiPost($config, $token, '_action/sync', [
        [
            'action' => 'upsert',
            'entity' => 'product_category',
            'payload' => $payload
        ]
    ]);

    echo "   HTTP: {$result['code']}\n";
}

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
echo "New structure:\n";
echo "- Raven Weapons\n";
echo "  - Sturmgewehre (Assault Rifles) - ID: $sturmgewehreId\n";
echo "- RAPAX (top-level)\n";
echo "  - RAPAX (sub) - first\n";
echo "  - Caracal Lynx - second\n";
