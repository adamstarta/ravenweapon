<?php
/**
 * Complete fix for RAPAX category
 * 1. Remove CMS page using sync API
 * 2. Assign products to RAPAX category
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

// Get OAuth token
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
$token = $data['access_token'];

echo "=== Complete RAPAX Fix ===\n\n";

$ravenWeaponsId = 'a61f19c9cb4b11f0b4074aca3d279c31';
$rapaxId = '1f36ebeb19da4fc6bc9cb3c3acfadafd';

// Step 1: Get all RAPAX/CARACAL products
echo "1. Getting all RAPAX/CARACAL products...\n";

$ch = curl_init($config['shopware_url'] . '/api/search/product');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
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
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
$productIds = [];
if (!empty($result['data'])) {
    foreach ($result['data'] as $product) {
        $productIds[] = $product['id'];
        echo "   Found: " . ($product['name'] ?? $product['translated']['name'] ?? 'Unknown') . "\n";
    }
}
echo "   Total products: " . count($productIds) . "\n";

// Step 2: Use sync API to remove CMS page from RAPAX category
echo "\n2. Removing CMS page from RAPAX category (sync API)...\n";

$ch = curl_init($config['shopware_url'] . '/api/_action/sync');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        [
            'action' => 'upsert',
            'entity' => 'category',
            'payload' => [
                [
                    'id' => $rapaxId,
                    'cmsPageId' => null,
                ]
            ]
        ]
    ]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Sync API response: HTTP $httpCode\n";
if ($httpCode !== 200) {
    echo "   Response: $response\n";
}

// Step 3: Use sync API to assign products to RAPAX category
echo "\n3. Assigning products to RAPAX category (sync API)...\n";

$categoryAssignments = [];
foreach ($productIds as $productId) {
    $categoryAssignments[] = [
        'productId' => $productId,
        'categoryId' => $rapaxId,
    ];
}

$ch = curl_init($config['shopware_url'] . '/api/_action/sync');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        [
            'action' => 'upsert',
            'entity' => 'product_category',
            'payload' => $categoryAssignments
        ]
    ]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Sync API response: HTTP $httpCode\n";
if ($httpCode === 200) {
    echo "   Assigned " . count($productIds) . " products to RAPAX category\n";
} else {
    echo "   Response: $response\n";
}

// Step 4: Verify RAPAX category settings
echo "\n4. Verifying RAPAX category...\n";

$ch = curl_init($config['shopware_url'] . "/api/category/$rapaxId");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if (!empty($result['data']['attributes'])) {
    $attrs = $result['data']['attributes'];
    echo "   Name: " . ($attrs['name'] ?? 'Unknown') . "\n";
    echo "   Parent ID: " . ($attrs['parentId'] ?? 'None') . "\n";
    echo "   CMS Page ID: " . ($attrs['cmsPageId'] ?? 'NONE (Good!)') . "\n";
    echo "   Active: " . ($attrs['active'] ? 'Yes' : 'No') . "\n";
    echo "   Visible: " . ($attrs['visible'] ? 'Yes' : 'No') . "\n";
    echo "   Type: " . ($attrs['type'] ?? 'N/A') . "\n";
}

// Step 5: Rebuild SEO URLs
echo "\n5. Rebuilding SEO URLs...\n";

$ch = curl_init($config['shopware_url'] . '/api/_action/index');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode(['skip' => []]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "   Index: HTTP $httpCode\n";

// Step 6: Clear cache
echo "\n6. Clearing cache...\n";

$ch = curl_init($config['shopware_url'] . '/api/_action/cache');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
    ],
]);
curl_exec($ch);
curl_close($ch);
echo "   Cache cleared\n";

echo "\n=== Done! ===\n";
echo "Check: https://ortak.ch/Raven-Weapons/RAPAX/\n";
