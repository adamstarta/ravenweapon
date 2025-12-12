<?php
/**
 * Check Shopware Category Structure - JSON:API format
 */

$config = [
    'shopware_url' => 'http://localhost',
    'api_user' => 'admin',
    'api_password' => 'shopware',
];

function getToken($config) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . '/api/oauth/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => $config['api_user'],
            'password' => $config['api_password'],
        ]),
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true)['access_token'] ?? null;
}

function apiRequest($token, $config, $endpoint, $data = [], $headers = []) {
    $ch = curl_init();
    $defaultHeaders = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',  // Force standard JSON format
    ];
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . '/api/' . ltrim($endpoint, '/'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        CURLOPT_POSTFIELDS => json_encode($data),
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

echo "\n=== SHOPWARE CATEGORY STRUCTURE ===\n\n";

$token = getToken($config);
if (!$token) {
    die("Auth failed!\n");
}
echo "Authenticated OK\n\n";

// Get all categories
$result = apiRequest($token, $config, '/search/category', [
    'limit' => 200,
]);

$categories = $result['data'] ?? [];
echo "Found " . count($categories) . " categories\n\n";

// Debug first one
if (!empty($categories[0])) {
    echo "First category keys: " . implode(', ', array_keys($categories[0])) . "\n";
    echo "First category name: " . ($categories[0]['name'] ?? 'NULL') . "\n";
    echo "First category translated.name: " . ($categories[0]['translated']['name'] ?? 'NULL') . "\n\n";
}

// Build index
$byId = [];
$byParent = [];
foreach ($categories as $cat) {
    $name = $cat['translated']['name'] ?? $cat['name'] ?? 'UNNAMED';
    $cat['_name'] = $name;
    $byId[$cat['id']] = $cat;

    $parentId = $cat['parentId'] ?? 'ROOT';
    if (!isset($byParent[$parentId])) {
        $byParent[$parentId] = [];
    }
    $byParent[$parentId][] = $cat;
}

// Print tree function
function printTree($parentId, $byParent, $indent = 0) {
    $children = $byParent[$parentId] ?? [];
    foreach ($children as $cat) {
        $status = ($cat['active'] ?? false) ? '✓' : '✗';
        $name = $cat['_name'];
        echo str_repeat('  ', $indent) . "$status $name [" . substr($cat['id'], 0, 8) . "...]\n";
        printTree($cat['id'], $byParent, $indent + 1);
    }
}

// Find main navigation root
echo "=== CATEGORY TREE ===\n\n";
foreach ($categories as $cat) {
    // Look for Hauptnavigation or root
    if ($cat['_name'] === 'Hauptnavigation' || ($cat['level'] ?? 0) == 1) {
        echo "Navigation Root: {$cat['_name']}\n";
        printTree($cat['id'], $byParent, 1);
        echo "\n";
    }
}

// Find Snigel specifically
echo "\n=== SNIGEL DETAILS ===\n\n";
$snigelCat = null;
foreach ($categories as $cat) {
    if ($cat['_name'] === 'Snigel') {
        $snigelCat = $cat;
        echo "Snigel Category: {$cat['id']}\n";
        echo "Parent ID: " . ($cat['parentId'] ?? 'NONE') . "\n";
        echo "Active: " . ($cat['active'] ? 'Yes' : 'No') . "\n\n";

        echo "Snigel Subcategories:\n";
        $children = $byParent[$cat['id']] ?? [];
        foreach ($children as $child) {
            echo "  - {$child['_name']} [{$child['id']}]\n";
        }
        break;
    }
}

// Check products
echo "\n=== PRODUCT CHECK ===\n\n";

// Get ALL products
$result = apiRequest($token, $config, '/search/product', [
    'limit' => 500,
    'associations' => [
        'categories' => [],
    ],
]);

$products = $result['data'] ?? [];
echo "Total products: " . count($products) . "\n\n";

// Count by category assignment
$inSnigel = 0;
$inAlleProdukte = 0;
$sampleNoCategory = [];

foreach ($products as $prod) {
    $name = $prod['translated']['name'] ?? $prod['name'] ?? 'UNNAMED';
    $cats = $prod['categories'] ?? [];
    $catIds = array_column($cats, 'id');

    $hasSnigel = false;
    $hasAlleProdukte = false;

    foreach ($catIds as $cid) {
        $catName = $byId[$cid]['_name'] ?? '';
        if ($catName === 'Snigel' || ($byId[$cid]['parentId'] ?? '') === ($snigelCat['id'] ?? '')) {
            $hasSnigel = true;
        }
        if (stripos($catName, 'Alle Produkte') !== false) {
            $hasAlleProdukte = true;
        }
    }

    if ($hasSnigel) $inSnigel++;
    if ($hasAlleProdukte) $inAlleProdukte++;

    // Sample products without Snigel or Alle Produkte
    if (stripos($name, 'Snigel') !== false && count($sampleNoCategory) < 5) {
        $sampleNoCategory[] = [
            'name' => $name,
            'categories' => array_map(fn($c) => $byId[$c]['_name'] ?? $c, $catIds),
        ];
    }
}

echo "Products in Snigel category: $inSnigel\n";
echo "Products in 'Alle Produkte': $inAlleProdukte\n\n";

echo "Sample Snigel-named products and their categories:\n";
foreach ($sampleNoCategory as $p) {
    echo "  {$p['name']}\n";
    echo "    Categories: " . implode(', ', $p['categories']) . "\n";
}

// Find Alle Produkte category
echo "\n=== ALLE PRODUKTE CATEGORY ===\n\n";
foreach ($categories as $cat) {
    if (stripos($cat['_name'], 'Alle Produkte') !== false) {
        echo "Found: {$cat['_name']} [{$cat['id']}]\n";
        echo "Active: " . ($cat['active'] ? 'Yes' : 'No') . "\n";
    }
}

echo "\n";
