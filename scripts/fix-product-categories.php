<?php
/**
 * Fix product category assignments - use correct Zubehör and Zielhilfen categories
 */

$config = [
    'base_url' => 'https://ortak.ch',
    'client_id' => 'SWIARAVEN03399CEA2C931269',
    'client_secret' => 'RavenNavbarUpdate2025!'
];

// CORRECT Mapping: Old shop category -> ortak.ch category ID
$categoryMapping = [
    // Aiming aids -> Zielhilfen, Optik & Zubehör subcategories
    'Riflescopes' => '499800fd06224f779acc5c4ac243d2e8',  // Zielfernrohre
    'Red Dots' => 'c34b9bb9a7a6473d928dd9c7c0f10f8b',     // Rotpunktvisiere
    'Spotting scopes' => '461b4f23bcd74823abbb55550af5008c', // Spektive
    'Binoculars' => 'b47d4447067c45aaa0aed7081ac465c4',   // Ferngläser

    // Accessories -> Zubehör subcategories
    'Magazines' => '00a19869155b4c0d9508dfcfeeaf93d7',     // Magazine
    'Sticks & handles' => '6aa33f0f12e543fbb28d2bd7ede4dbf2', // Griffe & Handschutz
    'Rails and Accessories' => '2d9fa9cea22f4d8e8c80fc059f8fc47d', // Schienen & Zubehör
    'Bipods' => '17d31faee72a4f0eb9863adf8bab9b00',        // Zweibeine
    'Muzzle attachments' => 'b2f4bafcda154b899c22bfb74496d140', // Mündungsaufsätze
];

// Special mapping for scope mounts (AIMPACT products)
$scopeMountCategoryId = '30d2d3ee371248d592cb1cdfdfd0f412'; // Zielfernrohrmontagen

// Category names for display
$categoryNames = [
    '499800fd06224f779acc5c4ac243d2e8' => 'Zielfernrohre',
    'c34b9bb9a7a6473d928dd9c7c0f10f8b' => 'Rotpunktvisiere',
    '461b4f23bcd74823abbb55550af5008c' => 'Spektive',
    'b47d4447067c45aaa0aed7081ac465c4' => 'Ferngläser',
    '00a19869155b4c0d9508dfcfeeaf93d7' => 'Magazine',
    '6aa33f0f12e543fbb28d2bd7ede4dbf2' => 'Griffe & Handschutz',
    '2d9fa9cea22f4d8e8c80fc059f8fc47d' => 'Schienen & Zubehör',
    '17d31faee72a4f0eb9863adf8bab9b00' => 'Zweibeine',
    'b2f4bafcda154b899c22bfb74496d140' => 'Mündungsaufsätze',
    '30d2d3ee371248d592cb1cdfdfd0f412' => 'Zielfernrohrmontagen',
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

function normalizeProductName($name) {
    $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = mb_strtolower($name, 'UTF-8');
    $name = preg_replace('/[^a-z0-9\s]/', '', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

echo "=== Fix Product Category Assignments ===\n\n";

// Load old shop products
$oldShopFile = __DIR__ . '/old-shop-data/all-products.json';
$oldProducts = json_decode(file_get_contents($oldShopFile), true);
echo "Loaded " . count($oldProducts) . " products from old shop\n";

// Create lookup
$oldProductLookup = [];
foreach ($oldProducts as $product) {
    $normalized = normalizeProductName($product['name']);
    $oldProductLookup[$normalized] = $product;
}

$token = getAccessToken($config);
if (!$token) die("Failed to get token\n");
echo "Got token\n\n";

// Fetch all products
$result = apiRequest('POST', '/search/product', [
    'limit' => 500,
    'includes' => ['product' => ['id', 'name', 'categories']]
], $token, $config);

$allProducts = $result['body']['data'] ?? [];
echo "Fetched " . count($allProducts) . " products from ortak.ch\n\n";

$assigned = 0;
$skipped = 0;

foreach ($allProducts as $product) {
    $normalized = normalizeProductName($product['name']);
    $productName = $product['name'];

    // Check if it's a scope mount (AIMPACT)
    if (stripos($productName, 'AIMPACT') !== false || stripos($productName, 'Scope Mount') !== false) {
        $targetCategoryId = $scopeMountCategoryId;
        $targetCategoryName = $categoryNames[$targetCategoryId];

        echo "  ASSIGN: $productName -> $targetCategoryName\n";

        $updateResult = apiRequest('PATCH', '/product/' . $product['id'], [
            'categories' => [['id' => $targetCategoryId]]
        ], $token, $config);

        if ($updateResult['code'] < 300) {
            $assigned++;
        } else {
            echo "    ERROR!\n";
        }
        continue;
    }

    // Find in old shop data
    if (!isset($oldProductLookup[$normalized])) {
        continue;
    }

    $oldProduct = $oldProductLookup[$normalized];
    $oldSubcategory = $oldProduct['subcategory'];

    // Handle "All" category - determine by product name
    if ($oldSubcategory === 'All') {
        // Determine category based on product characteristics
        if (stripos($productName, 'SCOPE') !== false || stripos($productName, 'SPOTTING') !== false) {
            $targetCategoryId = $categoryMapping['Spotting scopes'];
        } elseif (stripos($productName, 'RED DOT') !== false || stripos($productName, 'HALO') !== false ||
                  stripos($productName, 'REFLEX') !== false || stripos($productName, 'PRISM') !== false ||
                  stripos($productName, 'RAS 1X25') !== false) {
            $targetCategoryId = $categoryMapping['Red Dots'];
        } elseif (stripos($productName, 'VENGEANCE') !== false || stripos($productName, 'TRACE') !== false ||
                  stripos($productName, 'THRIVE') !== false) {
            $targetCategoryId = $categoryMapping['Riflescopes'];
        } elseif (stripos($productName, 'Bipod') !== false) {
            $targetCategoryId = $categoryMapping['Bipods'];
        } elseif (stripos($productName, 'Flash Hider') !== false || stripos($productName, 'Muzzle') !== false ||
                  stripos($productName, 'Hexalug') !== false) {
            $targetCategoryId = $categoryMapping['Muzzle attachments'];
        } else {
            // Skip if can't determine
            $skipped++;
            continue;
        }
    } else {
        // Use mapping
        if (!isset($categoryMapping[$oldSubcategory])) {
            $skipped++;
            continue;
        }
        $targetCategoryId = $categoryMapping[$oldSubcategory];
    }

    $targetCategoryName = $categoryNames[$targetCategoryId] ?? $targetCategoryId;

    echo "  ASSIGN: $productName -> $targetCategoryName\n";

    $updateResult = apiRequest('PATCH', '/product/' . $product['id'], [
        'categories' => [['id' => $targetCategoryId]]
    ], $token, $config);

    if ($updateResult['code'] < 300) {
        $assigned++;
    } else {
        echo "    ERROR: " . json_encode($updateResult['body']['errors'][0]['detail'] ?? 'Unknown') . "\n";
    }
}

echo "\n=== Summary ===\n";
echo "Assigned: $assigned\n";
echo "Skipped: $skipped\n";

// Clear cache
echo "\nClearing cache... ";
$cacheResult = apiRequest('DELETE', '/_action/cache', null, $token, $config);
echo ($cacheResult['code'] < 300 ? "OK" : "FAIL") . "\n";

echo "\n=== DONE ===\n";
