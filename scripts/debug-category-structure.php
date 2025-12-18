<?php
/**
 * Debug Category Structure - Get all categories with proper names
 */

$baseUrl = 'https://ortak.ch/api';
$clientId = 'SWIAC3HJVHFJMHQYRWRUM1E1SG';
$clientSecret = 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg';

function getAccessToken($baseUrl, $clientId, $clientSecret) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/oauth/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    return json_decode($response, true);
}

echo "=== SHOPWARE CATEGORY STRUCTURE ===\n\n";

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
if (!$token) {
    echo "ERROR: Could not get API token\n";
    exit(1);
}
echo "Got token\n\n";

// Get all categories with full data
$response = apiRequest($baseUrl, $token, 'POST', '/search/category', [
    'limit' => 500,
    'includes' => [
        'category' => ['id', 'name', 'parentId', 'level', 'path', 'breadcrumb', 'translated', 'active', 'childCount']
    ]
]);

if (!isset($response['data'])) {
    echo "Error fetching categories:\n";
    print_r($response);
    exit(1);
}

echo "Found " . count($response['data']) . " categories\n\n";

// Build lookup
$categories = [];
foreach ($response['data'] as $cat) {
    $name = $cat['translated']['name'] ?? $cat['name'] ?? '(unnamed)';
    $breadcrumb = $cat['translated']['breadcrumb'] ?? $cat['breadcrumb'] ?? [];

    $categories[$cat['id']] = [
        'id' => $cat['id'],
        'name' => $name,
        'parentId' => $cat['parentId'] ?? null,
        'level' => $cat['level'] ?? 0,
        'path' => implode(' > ', array_slice($breadcrumb, 1)),
        'breadcrumb' => $breadcrumb,
        'active' => $cat['active'] ?? false,
        'childCount' => $cat['childCount'] ?? 0
    ];
}

// Sort by level and then by name
uasort($categories, function($a, $b) {
    if ($a['level'] !== $b['level']) {
        return $a['level'] - $b['level'];
    }
    return strcmp($a['name'], $b['name']);
});

// Print tree structure
echo "CATEGORY TREE:\n";
echo str_repeat('=', 100) . "\n\n";

// Find root categories first
$rootCats = array_filter($categories, fn($c) => $c['level'] == 1);

function printCategoryTree($categories, $parentId, $indent = 0) {
    $children = array_filter($categories, fn($c) => $c['parentId'] === $parentId);

    foreach ($children as $cat) {
        $prefix = str_repeat('  ', $indent);
        $marker = $cat['childCount'] > 0 ? '📁' : '📄';
        $active = $cat['active'] ? '✓' : '✗';

        echo sprintf("%s%s [%s] %s\n", $prefix, $marker, $active, $cat['name']);
        echo sprintf("%s   ID: %s\n", $prefix, $cat['id']);

        if ($cat['childCount'] > 0) {
            printCategoryTree($categories, $cat['id'], $indent + 1);
        }
        echo "\n";
    }
}

// Find the navigation entry category
foreach ($categories as $cat) {
    if ($cat['level'] == 1 && strpos($cat['name'], 'Navigation') !== false) {
        echo "Root Navigation Category: {$cat['name']}\n";
        echo "ID: {$cat['id']}\n\n";
        printCategoryTree($categories, $cat['id'], 0);
        break;
    }
}

// Also print top-level categories
echo "\n\nALL TOP-LEVEL CATEGORIES (Level 2):\n";
echo str_repeat('=', 100) . "\n\n";

$level2Cats = array_filter($categories, fn($c) => $c['level'] == 2);
foreach ($level2Cats as $cat) {
    echo "📁 {$cat['name']}\n";
    echo "   ID: {$cat['id']}\n";
    echo "   Children: {$cat['childCount']}\n";

    // Print children
    $children = array_filter($categories, fn($c) => $c['parentId'] === $cat['id']);
    foreach ($children as $child) {
        echo "   ↳ {$child['name']} (ID: {$child['id']})\n";
    }
    echo "\n";
}

// Save for reference
file_put_contents(__DIR__ . '/shopware-categories-structure.json', json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\nSaved to shopware-categories-structure.json\n";
