<?php
/**
 * Preview category assignments - DRY RUN
 */

$config = [
    'base_url' => 'https://ortak.ch',
    'client_id' => 'SWIARAVEN03399CEA2C931269',
    'client_secret' => 'RavenNavbarUpdate2025!'
];

// Mapping: Old shop category -> German Ausrüstung subcategory
$categoryMapping = [
    'Riflescopes' => 'Scharfschützen-Ausrüstung',
    'Red Dots' => 'Taktische Ausrüstung',
    'Spotting scopes' => 'Scharfschützen-Ausrüstung',
    'Binoculars' => 'Verschiedenes',
    'Magazines' => 'Taktische Ausrüstung',
    'Sticks & handles' => 'Taktische Ausrüstung',
    'Rails and Accessories' => 'Taktische Ausrüstung',
    'Bipods' => 'Scharfschützen-Ausrüstung',
    'Muzzle attachments' => 'Taktische Ausrüstung',
    'All' => 'Taktische Ausrüstung'
];

function getAccessToken($config) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['base_url'] . '/api/oauth/token',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'client_credentials',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret']
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function apiRequest($method, $endpoint, $data, $token, $config) {
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $config['base_url'] . '/api' . $endpoint,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ];
    if ($data !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function normalizeProductName($name) {
    $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = mb_strtolower($name, 'UTF-8');
    $name = preg_replace('/[^a-z0-9\s]/', '', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

echo "=== PREVIEW: Category Assignments (DRY RUN) ===\n\n";

// Load old shop products
$oldShopFile = __DIR__ . '/old-shop-data/all-products.json';
$oldProducts = json_decode(file_get_contents($oldShopFile), true);

// Create lookup
$oldProductLookup = [];
foreach ($oldProducts as $product) {
    $normalized = normalizeProductName($product['name']);
    $oldProductLookup[$normalized] = $product;
}

$token = getAccessToken($config);

// Fetch all products
$result = apiRequest('POST', '/search/product', [
    'limit' => 500,
    'includes' => ['product' => ['id', 'name']]
], $token, $config);

$allProducts = $result['data'] ?? [];

// Group assignments by target category
$assignments = [];
foreach ($categoryMapping as $target) {
    $assignments[$target] = [];
}

foreach ($allProducts as $product) {
    $normalized = normalizeProductName($product['name']);

    if (isset($oldProductLookup[$normalized])) {
        $oldProduct = $oldProductLookup[$normalized];
        $oldSubcategory = $oldProduct['subcategory'];
        $targetCategory = $categoryMapping[$oldSubcategory] ?? $categoryMapping['All'];

        $assignments[$targetCategory][] = [
            'name' => $product['name'],
            'oldCategory' => $oldProduct['category'] . ' > ' . $oldSubcategory
        ];
    }
}

// Print preview
foreach ($assignments as $targetCategory => $products) {
    if (empty($products)) continue;

    echo "\n========================================\n";
    echo "TARGET: $targetCategory (" . count($products) . " products)\n";
    echo "========================================\n";

    foreach ($products as $p) {
        echo "  • {$p['name']}\n";
        echo "    From: {$p['oldCategory']}\n";
    }
}

// Summary
echo "\n\n=== SUMMARY ===\n";
foreach ($assignments as $targetCategory => $products) {
    if (!empty($products)) {
        echo "$targetCategory: " . count($products) . " products\n";
    }
}

echo "\n*** This is a PREVIEW only - no changes made ***\n";
