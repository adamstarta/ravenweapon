<?php
/**
 * Fix RAPAX category - make it child of Raven Weapons
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

echo "=== Fixing RAPAX Category Under Raven Weapons ===\n\n";

// Known IDs from our research
$ravenWeaponsId = 'a61f19c9cb4b11f0b4074aca3d279c31';  // Raven Weapons
$rapaxId = '1f36ebeb19da4fc6bc9cb3c3acfadafd';  // RAPAX

// First check current RAPAX status
echo "1. Checking current RAPAX category...\n";

$ch = curl_init($config['shopware_url'] . "/api/category/$rapaxId");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$rapaxData = json_decode($response, true);
if ($httpCode === 200 && !empty($rapaxData['data'])) {
    $attrs = $rapaxData['data']['attributes'];
    echo "   Name: " . ($attrs['name'] ?? 'Unknown') . "\n";
    echo "   Current Parent ID: " . ($attrs['parentId'] ?? 'None') . "\n";
    echo "   Active: " . ($attrs['active'] ? 'Yes' : 'No') . "\n";
    echo "   Visible: " . ($attrs['visible'] ? 'Yes' : 'No') . "\n";
    echo "   CMS Page ID: " . ($attrs['cmsPageId'] ?? 'None') . "\n";
} else {
    echo "   RAPAX not found at ID $rapaxId\n";
    echo "   HTTP Code: $httpCode\n";

    // Search for RAPAX by name
    echo "\n   Searching for RAPAX by name...\n";

    $ch = curl_init($config['shopware_url'] . '/api/category?filter[name]=RAPAX&limit=10');
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
    if (!empty($result['data'])) {
        foreach ($result['data'] as $cat) {
            $rapaxId = $cat['id'];
            echo "   Found RAPAX: $rapaxId\n";
            break;
        }
    } else {
        // RAPAX doesn't exist, need to create it
        echo "   RAPAX category not found. Creating new one...\n";

        $rapaxId = bin2hex(random_bytes(16));
        $ch = curl_init($config['shopware_url'] . '/api/category');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'id' => $rapaxId,
                'parentId' => $ravenWeaponsId,
                'name' => 'RAPAX',
                'active' => true,
                'visible' => true,
                'type' => 'page',
                'displayNestedProducts' => true,
                'productAssignmentType' => 'product',
            ]),
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 204 || $httpCode === 200) {
            echo "   Created RAPAX category: $rapaxId\n";
        } else {
            echo "   Error creating RAPAX: HTTP $httpCode\n";
            echo $response . "\n";
            exit(1);
        }
    }
}

// Update RAPAX to be child of Raven Weapons
echo "\n2. Moving RAPAX under Raven Weapons...\n";

$ch = curl_init($config['shopware_url'] . "/api/category/$rapaxId");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'parentId' => $ravenWeaponsId,
        'name' => 'RAPAX',
        'active' => true,
        'visible' => true,
        'type' => 'page',
        'displayNestedProducts' => true,
        'productAssignmentType' => 'product',
        'cmsPageId' => null,  // Remove CMS page to show products
    ]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 204) {
    echo "   RAPAX updated successfully!\n";
    echo "   - Parent: Raven Weapons\n";
    echo "   - CMS Page: Removed\n";
} else {
    echo "   Error: HTTP $httpCode\n";
    echo "   Response: $response\n";
}

// Verify the change
echo "\n3. Verifying the change...\n";

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
    echo "   Parent ID: " . ($attrs['parentId'] ?? 'None') . "\n";
    echo "   Expected:  $ravenWeaponsId\n";
    echo "   Match: " . (($attrs['parentId'] ?? '') === $ravenWeaponsId ? 'YES' : 'NO') . "\n";
    echo "   CMS Page ID: " . ($attrs['cmsPageId'] ?? 'None (good!)') . "\n";
}

// Assign products to this RAPAX category
echo "\n4. Checking if products are in RAPAX category...\n";

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
        'associations' => [
            'categories' => []
        ],
        'limit' => 50
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
$productsInCategory = 0;
$productsNotInCategory = [];

if (!empty($result['data'])) {
    foreach ($result['data'] as $product) {
        $inRapax = false;
        if (!empty($product['categories'])) {
            foreach ($product['categories'] as $cat) {
                if ($cat['id'] === $rapaxId) {
                    $inRapax = true;
                    break;
                }
            }
        }

        if ($inRapax) {
            $productsInCategory++;
        } else {
            $productsNotInCategory[] = $product['id'];
        }
    }

    echo "   Products in RAPAX: $productsInCategory\n";
    echo "   Products NOT in RAPAX: " . count($productsNotInCategory) . "\n";

    if (count($productsNotInCategory) > 0) {
        echo "\n5. Assigning products to RAPAX category...\n";

        foreach ($productsNotInCategory as $productId) {
            $ch = curl_init($config['shopware_url'] . "/api/product/$productId/categories/$rapaxId");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => '{}',
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 204 || $httpCode === 200) {
                echo "   + Assigned product $productId\n";
            }
        }
    }
}

// Rebuild SEO URLs
echo "\n6. Triggering SEO URL rebuild...\n";

$ch = curl_init($config['shopware_url'] . '/api/_action/index');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'skip' => []
    ]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "   Index rebuild: HTTP $httpCode\n";

// Clear cache
echo "\n7. Clearing cache...\n";
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
