<?php
/**
 * Scan Ausrüstung category structure
 */

$API_URL = 'https://ortak.ch';

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'client_credentials',
            'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
            'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

echo "=== SCANNING AUSRÜSTUNG CATEGORY STRUCTURE ===\n\n";

$token = getToken($API_URL);
if (!$token) {
    die("Failed to authenticate!\n");
}
echo "✓ Authenticated\n\n";

// Get all categories
$result = apiRequest($API_URL, $token, 'POST', 'search/category', [
    'limit' => 500,
    'associations' => [
        'children' => []
    ]
]);

$categories = [];
$ausrustungId = null;

// Index all categories
foreach ($result['data'] ?? [] as $cat) {
    $id = $cat['id'];
    $name = $cat['attributes']['name'] ?? $cat['name'] ?? 'Unknown';
    $parentId = $cat['attributes']['parentId'] ?? $cat['parentId'] ?? null;
    $level = $cat['attributes']['level'] ?? $cat['level'] ?? 0;

    $categories[$id] = [
        'id' => $id,
        'name' => $name,
        'parentId' => $parentId,
        'level' => $level,
        'children' => []
    ];

    if (stripos($name, 'Ausrüstung') !== false && $level == 2) {
        $ausrustungId = $id;
    }
}

// Build tree
foreach ($categories as $id => &$cat) {
    if ($cat['parentId'] && isset($categories[$cat['parentId']])) {
        $categories[$cat['parentId']]['children'][] = $id;
    }
}
unset($cat);

// Function to print category tree
function printTree($categories, $id, $indent = 0) {
    $cat = $categories[$id];
    $prefix = str_repeat('  ', $indent);
    $symbol = $indent > 0 ? '↳ ' : '';
    echo $prefix . $symbol . $cat['name'] . " (level: {$cat['level']}, id: {$cat['id']})\n";

    foreach ($cat['children'] as $childId) {
        printTree($categories, $childId, $indent + 1);
    }
}

echo "=== AUSRÜSTUNG CATEGORY TREE ===\n\n";

if ($ausrustungId) {
    printTree($categories, $ausrustungId);

    // Count children
    $childCount = count($categories[$ausrustungId]['children']);
    echo "\n\nTotal direct subcategories under Ausrüstung: $childCount\n";

    // List all children with product counts
    echo "\n=== SUBCATEGORIES DETAIL ===\n";
    foreach ($categories[$ausrustungId]['children'] as $childId) {
        $childName = $categories[$childId]['name'];

        // Get product count for this category
        $productResult = apiRequest($API_URL, $token, 'POST', 'search/product', [
            'filter' => [
                ['type' => 'equals', 'field' => 'categoryTree', 'value' => $childId]
            ],
            'limit' => 1,
            'total-count-mode' => 1
        ]);
        $productCount = $productResult['meta']['total'] ?? 0;

        echo "- $childName: $productCount products\n";
    }
} else {
    echo "Ausrüstung category not found!\n";

    // List all level 2 categories
    echo "\nLevel 2 categories:\n";
    foreach ($categories as $cat) {
        if ($cat['level'] == 2) {
            echo "- {$cat['name']} (id: {$cat['id']})\n";
        }
    }
}

// Also check Raven Weapons structure for comparison
echo "\n\n=== RAVEN WEAPONS STRUCTURE (FOR COMPARISON) ===\n\n";
$ravenId = null;
foreach ($categories as $cat) {
    if (stripos($cat['name'], 'Raven Weapons') !== false && $cat['level'] == 2) {
        $ravenId = $cat['id'];
        break;
    }
}

if ($ravenId) {
    printTree($categories, $ravenId);
}

echo "\n=== DONE ===\n";
