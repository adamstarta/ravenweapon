<?php
/**
 * Get Category Tree with proper names
 */

$baseUrl = 'https://ortak.ch/api';
$clientId = 'SWIAC3HJVHFJMHQYRWRUM1E1SG';
$clientSecret = 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg';

// Get token
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
$token = json_decode($response, true)['access_token'];

echo "Got token\n\n";

// Get categories with language header (German)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/search/category');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token,
    'sw-language-id: 2fbb5fe2e29a4d70aa5854ce7ce3e20b'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['limit' => 500]));
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);

echo 'Total Categories: ' . count($data['data']) . "\n\n";

// Build lookup tables - data is in 'attributes' key
$byParent = [];
$byId = [];
$fullData = [];

foreach ($data['data'] as $cat) {
    $attrs = $cat['attributes'] ?? $cat;
    $id = $cat['id'];

    $name = $attrs['translated']['name'] ?? $attrs['name'] ?? '(unnamed)';
    $byId[$id] = $name;

    $fullData[$id] = [
        'id' => $id,
        'name' => $name,
        'level' => $attrs['level'] ?? 0,
        'parentId' => $attrs['parentId'] ?? null,
        'active' => $attrs['active'] ?? false,
        'childCount' => $attrs['childCount'] ?? 0,
        'breadcrumb' => $attrs['translated']['breadcrumb'] ?? $attrs['breadcrumb'] ?? []
    ];

    $parentId = $attrs['parentId'] ?? 'root';
    if (!isset($byParent[$parentId])) {
        $byParent[$parentId] = [];
    }
    $byParent[$parentId][] = [
        'id' => $id,
        'name' => $name,
        'level' => $attrs['level'] ?? 0,
        'active' => $attrs['active'] ?? false
    ];
}

// Sort children by name
foreach ($byParent as &$children) {
    usort($children, fn($a, $b) => strcmp($a['name'], $b['name']));
}

// Print tree function
function printTree($byParent, $parentId, $indent = 0) {
    if (!isset($byParent[$parentId])) return;

    foreach ($byParent[$parentId] as $cat) {
        $prefix = str_repeat('  ', $indent);
        $active = $cat['active'] ? '✓' : '✗';
        echo sprintf("%s[%s] %s\n", $prefix, $active, $cat['name']);
        echo sprintf("%s    ID: %s\n", $prefix, $cat['id']);
        printTree($byParent, $cat['id'], $indent + 1);
    }
}

echo "CATEGORY TREE:\n";
echo str_repeat('=', 80) . "\n\n";

// Find root level categories (level 1)
$rootCats = array_filter($fullData, fn($c) => $c['level'] == 1);

foreach ($rootCats as $root) {
    echo "ROOT: {$root['name']}\n";
    echo "ID: {$root['id']}\n\n";
    printTree($byParent, $root['id'], 1);
    echo "\n";
}

// Save full data
file_put_contents(__DIR__ . '/category-tree-data.json', json_encode($fullData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\nSaved to category-tree-data.json\n";

// Now look for Zielhilfen specifically
echo "\n\nSEARCHING FOR RELEVANT CATEGORIES:\n";
echo str_repeat('=', 80) . "\n\n";

foreach ($fullData as $cat) {
    if (stripos($cat['name'], 'Ziel') !== false ||
        stripos($cat['name'], 'Optik') !== false ||
        stripos($cat['name'], 'Spektiv') !== false ||
        stripos($cat['name'], 'Fernglas') !== false ||
        stripos($cat['name'], 'Rotpunkt') !== false ||
        stripos($cat['name'], 'Muzzle') !== false ||
        stripos($cat['name'], 'Mündung') !== false ||
        stripos($cat['name'], 'Zubehör') !== false) {
        $path = implode(' > ', array_filter($cat['breadcrumb']));
        echo "Found: {$cat['name']}\n";
        echo "  ID: {$cat['id']}\n";
        echo "  Path: $path\n";
        echo "  Level: {$cat['level']}\n";
        echo "  Active: " . ($cat['active'] ? 'Yes' : 'No') . "\n\n";
    }
}
