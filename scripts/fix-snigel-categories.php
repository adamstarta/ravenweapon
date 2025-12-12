<?php
/**
 * Fix Snigel Categories:
 * 1. Activate all English Snigel subcategories
 * 2. Assign products to proper English subcategories
 * 3. Add all Snigel products to "Alle Produkte" category
 */

$config = [
    'shopware_url' => 'http://localhost',
    'api_user' => 'admin',
    'api_password' => 'shopware',
];

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║     FIX SNIGEL CATEGORIES & ALLE PRODUKTE                 ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Token management
$GLOBALS['token_data'] = ['token' => null, 'expires_at' => 0];

function getAccessToken($config, $forceRefresh = false) {
    if (!$forceRefresh && $GLOBALS['token_data']['token'] && $GLOBALS['token_data']['expires_at'] > time() + 60) {
        return $GLOBALS['token_data']['token'];
    }

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

    $data = json_decode($response, true);
    $GLOBALS['token_data']['token'] = $data['access_token'] ?? null;
    $GLOBALS['token_data']['expires_at'] = time() + ($data['expires_in'] ?? 600);

    return $GLOBALS['token_data']['token'];
}

function apiRequest($method, $endpoint, $data, $config, $retry = true) {
    $token = getAccessToken($config);
    if (!$token) return ['code' => 0, 'body' => null];

    $ch = curl_init();
    $url = $config['shopware_url'] . '/api/' . ltrim($endpoint, '/');

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    if ($data && in_array($method, ['POST', 'PATCH', 'PUT'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 401 && $retry) {
        $GLOBALS['token_data']['token'] = null;
        return apiRequest($method, $endpoint, $data, $config, false);
    }

    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

// Step 1: Authenticate
echo "Step 1: Authenticating...\n";
$token = getAccessToken($config);
if (!$token) {
    die("  ERROR: Failed to authenticate!\n");
}
echo "  ✓ Authenticated\n\n";

// Step 2: Get all categories
echo "Step 2: Getting all categories...\n";
$result = apiRequest('POST', '/search/category', ['limit' => 200], $config);
$categories = $result['body']['data'] ?? [];

$byId = [];
$byName = [];
foreach ($categories as $cat) {
    $name = $cat['translated']['name'] ?? $cat['name'] ?? '';
    $byId[$cat['id']] = $cat;
    $byId[$cat['id']]['_name'] = $name;
    $byName[$name][] = $cat['id'];
}
echo "  Found " . count($categories) . " categories\n\n";

// Step 3: Find main Snigel category (the one under Hauptnavigation)
echo "Step 3: Finding main Snigel category...\n";
$snigelCatId = null;
foreach ($categories as $cat) {
    $name = $cat['translated']['name'] ?? $cat['name'] ?? '';
    if ($name === 'Snigel' && $cat['active']) {
        $snigelCatId = $cat['id'];
        echo "  Found: Snigel [{$cat['id']}]\n";
        break;
    }
}
if (!$snigelCatId) {
    die("  ERROR: Main Snigel category not found!\n");
}

// Step 4: Find "Alle Produkte" category
echo "\nStep 4: Finding 'Alle Produkte' category...\n";
$alleProdukteCatId = null;
foreach ($categories as $cat) {
    $name = $cat['translated']['name'] ?? $cat['name'] ?? '';
    if ($name === 'Alle Produkte') {
        $alleProdukteCatId = $cat['id'];
        echo "  Found: Alle Produkte [{$cat['id']}]\n";
        break;
    }
}
if (!$alleProdukteCatId) {
    die("  ERROR: Alle Produkte category not found!\n");
}

// Step 5: Find and activate INACTIVE English subcategories under Snigel
echo "\nStep 5: Activating inactive Snigel subcategories...\n";

$englishSubcategories = [
    'Tactical gear', 'HighVis', 'Source® hydration', 'Miscellaneous products',
    'Bags & backpacks', 'K9-units gear', 'Vests & Chest rigs', 'Leg panels',
    'Multicam', 'Admin products', 'Tactical clothing', 'Covert gear',
    'Police gear', 'The Brand', 'Sniper gear', 'Slings & holsters',
    'Patches', 'Medical gear', 'Holders & pouches', 'Ballistic protection', 'Belts'
];

$activated = 0;
foreach ($categories as $cat) {
    $name = $cat['translated']['name'] ?? $cat['name'] ?? '';
    if ($cat['parentId'] === $snigelCatId && !$cat['active']) {
        if (in_array($name, $englishSubcategories)) {
            $updateResult = apiRequest('PATCH', "/category/{$cat['id']}", [
                'active' => true,
            ], $config);

            if ($updateResult['code'] === 204) {
                echo "  ✓ Activated: $name\n";
                $activated++;
            } else {
                echo "  ✗ Failed to activate: $name (HTTP {$updateResult['code']})\n";
            }
        }
    }
}
echo "  Activated $activated subcategories\n\n";

// Step 6: Get all Snigel products
echo "Step 6: Getting all Snigel products...\n";
$allProducts = [];
$page = 1;
do {
    $result = apiRequest('POST', '/search/product', [
        'page' => $page,
        'limit' => 100,
        'associations' => ['categories' => []],
    ], $config);

    $products = $result['body']['data'] ?? [];
    foreach ($products as $prod) {
        $allProducts[] = $prod;
    }
    $page++;
} while (count($products) === 100);

echo "  Total products: " . count($allProducts) . "\n\n";

// Step 7: Add all Snigel products to "Alle Produkte"
echo "Step 7: Adding Snigel products to 'Alle Produkte'...\n";

$snigelProducts = [];
foreach ($allProducts as $prod) {
    $cats = $prod['categories'] ?? [];
    $catIds = array_column($cats, 'id');

    // Check if in Snigel category or any of its subcategories
    $isSnigel = false;
    foreach ($catIds as $cid) {
        if ($cid === $snigelCatId || ($byId[$cid]['parentId'] ?? '') === $snigelCatId) {
            $isSnigel = true;
            break;
        }
    }

    if ($isSnigel) {
        $snigelProducts[] = $prod;

        // Check if already in Alle Produkte
        $inAlle = in_array($alleProdukteCatId, $catIds);
        if (!$inAlle) {
            // Add to existing categories + Alle Produkte
            $newCategories = [];
            foreach ($catIds as $cid) {
                $newCategories[] = ['id' => $cid];
            }
            $newCategories[] = ['id' => $alleProdukteCatId];

            $updateResult = apiRequest('PATCH', "/product/{$prod['id']}", [
                'categories' => $newCategories,
            ], $config);

            $name = $prod['translated']['name'] ?? $prod['name'] ?? 'UNNAMED';
            if ($updateResult['code'] === 204) {
                echo "  ✓ Added to Alle Produkte: " . substr($name, 0, 50) . "\n";
            } else {
                echo "  ✗ Failed: $name (HTTP {$updateResult['code']})\n";
            }
            usleep(50000); // 50ms delay
        }
    }
}

echo "\n  Snigel products found: " . count($snigelProducts) . "\n\n";

// Summary
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                       COMPLETE                             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "  Subcategories activated: $activated\n";
echo "  Snigel products added to 'Alle Produkte'\n";
echo "\n";
