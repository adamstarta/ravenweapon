<?php
/**
 * Fix RAPAX category - move under Raven Weapons parent
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

echo "=== Fixing RAPAX Category Structure ===\n\n";

// First, find Raven Weapons category by SEO URL
echo "1. Finding Raven Weapons category...\n";

$ch = curl_init($config['shopware_url'] . '/api/search/seo-url');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'filter' => [
            ['type' => 'equals', 'field' => 'seoPathInfo', 'value' => 'Raven-Weapons/']
        ],
        'limit' => 1
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);

$ravenWeaponsId = null;
if (!empty($result['data'][0])) {
    $ravenWeaponsId = $result['data'][0]['foreignKey'];
    echo "   Found Raven Weapons ID: $ravenWeaponsId\n";
} else {
    echo "   Raven Weapons not found by SEO URL, searching by name...\n";

    // Search by name
    $ch = curl_init($config['shopware_url'] . '/api/search/category');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'filter' => [
                ['type' => 'equals', 'field' => 'name', 'value' => 'Raven Weapons']
            ],
            'limit' => 10
        ]),
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);

    if (!empty($result['data'])) {
        foreach ($result['data'] as $cat) {
            echo "   Found: {$cat['id']}\n";
            $ravenWeaponsId = $cat['id'];
            break;
        }
    }
}

if (!$ravenWeaponsId) {
    die("Error: Could not find Raven Weapons category\n");
}

// Now find the current RAPAX category
echo "\n2. Finding current RAPAX category...\n";

$rapaxId = '1f36ebeb19da4fc6bc9cb3c3acfadafd';  // Known ID from previous scripts

// Get RAPAX category details
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
    echo "   Current RAPAX parent ID: " . ($rapaxData['data']['parentId'] ?? 'None') . "\n";
} else {
    echo "   RAPAX category not found at ID $rapaxId\n";

    // Search for RAPAX
    $ch = curl_init($config['shopware_url'] . '/api/search/category');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'filter' => [
                ['type' => 'equals', 'field' => 'name', 'value' => 'RAPAX']
            ],
            'limit' => 10
        ]),
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);

    if (!empty($result['data'])) {
        $rapaxId = $result['data'][0]['id'];
        echo "   Found RAPAX category: $rapaxId\n";
    } else {
        die("   Error: Could not find RAPAX category\n");
    }
}

// Update RAPAX to have Raven Weapons as parent
echo "\n3. Moving RAPAX under Raven Weapons...\n";

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
        'active' => true,
        'visible' => true,
        'type' => 'page',
        'displayNestedProducts' => true,
        'productAssignmentType' => 'product',
        'cmsPageId' => null,  // Remove any CMS page to show products
    ]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 204) {
    echo "   RAPAX moved under Raven Weapons\n";
} else {
    echo "   Error: HTTP $httpCode\n";
    echo "   Response: $response\n";
}

// Rebuild SEO URLs
echo "\n4. Rebuilding SEO URLs...\n";

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
echo "\n5. Clearing cache...\n";
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
