<?php
/**
 * Assign Snigel products to English subcategories
 *
 * Products were assigned to German subcategories (Taschen & Rucksäcke)
 * but navigation uses English subcategories (Bags & backpacks)
 * This script assigns products to both.
 */

$config = [
    'shopware_url' => 'http://localhost',
    'api_user' => 'admin',
    'api_password' => 'shopware',
];

// Mapping from German to English category names
$germanToEnglish = [
    'Taschen & Rucksäcke' => 'Bags & backpacks',
    'Gürtel' => 'Belts',
    'Taktische Bekleidung' => 'Tactical clothing',
    'Holster & Taschen' => 'Holders & pouches',
    'Ballistischer Schutz' => 'Ballistic protection',
    'Tragegurte & Holster' => 'Slings & holsters',
    'Westen & Chest Rigs' => 'Vests & Chest rigs',
    'Beinpanels' => 'Leg panels',
    'Medizinische Ausrüstung' => 'Medical gear',
    'Polizeiausrüstung' => 'Police gear',
    'Patches' => 'Patches',
    'Taktische Ausrüstung' => 'Tactical gear',
    'Verdeckte Ausrüstung' => 'Covert gear',
    'Multicam' => 'Multicam',
    'Scharfschützen-Ausrüstung' => 'Sniper gear',
    'Die Marke' => 'The Brand',
    'Verschiedene Produkte' => 'Miscellaneous products',
    'K9-Einheiten Ausrüstung' => 'K9-units gear',
    'Source® Hydration' => 'Source® hydration',
    'Verwaltungsprodukte' => 'Admin products',
    'Warnschutz' => 'HighVis',
];

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  ASSIGN PRODUCTS TO ENGLISH SUBCATEGORIES                 ║\n";
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
    if (!isset($byName[$name])) {
        $byName[$name] = [];
    }
    $byName[$name][] = $cat['id'];
}
echo "  Found " . count($categories) . " categories\n\n";

// Step 3: Find Snigel parent category
echo "Step 3: Finding Snigel parent category...\n";
$snigelCatId = null;
foreach ($categories as $cat) {
    $name = $cat['translated']['name'] ?? $cat['name'] ?? '';
    if ($name === 'Snigel' && $cat['active']) {
        $snigelCatId = $cat['id'];
        echo "  Found Snigel: {$cat['id']}\n";
        break;
    }
}

// Step 4: Build German -> English category ID mapping
echo "\nStep 4: Building category mappings...\n";
$germanToEnglishIds = [];

foreach ($germanToEnglish as $germanName => $englishName) {
    $germanId = null;
    $englishId = null;

    // Find German category under Snigel
    foreach ($byName[$germanName] ?? [] as $catId) {
        if (($byId[$catId]['parentId'] ?? '') === $snigelCatId) {
            $germanId = $catId;
            break;
        }
    }

    // Find English category under Snigel
    foreach ($byName[$englishName] ?? [] as $catId) {
        if (($byId[$catId]['parentId'] ?? '') === $snigelCatId) {
            $englishId = $catId;
            break;
        }
    }

    if ($germanId && $englishId) {
        $germanToEnglishIds[$germanId] = $englishId;
        echo "  $germanName -> $englishName\n";
    } else {
        echo "  ! Missing: $germanName ($germanId) -> $englishName ($englishId)\n";
    }
}
echo "\n";

// Step 5: Get all products with their categories
echo "Step 5: Getting all products...\n";
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

// Step 6: For each product in a German category, also assign to English category
echo "Step 6: Assigning products to English subcategories...\n\n";

$updated = 0;
$skipped = 0;

foreach ($allProducts as $prod) {
    $name = $prod['translated']['name'] ?? $prod['name'] ?? 'UNNAMED';
    $cats = $prod['categories'] ?? [];
    $catIds = array_column($cats, 'id');

    $newCatIds = $catIds; // Start with existing categories
    $needsUpdate = false;

    // Check if product is in any German subcategory
    foreach ($catIds as $catId) {
        if (isset($germanToEnglishIds[$catId])) {
            $englishId = $germanToEnglishIds[$catId];
            // Check if not already in English category
            if (!in_array($englishId, $newCatIds)) {
                $newCatIds[] = $englishId;
                $needsUpdate = true;
            }
        }
    }

    if ($needsUpdate) {
        // Build category array for update
        $categoryData = [];
        foreach (array_unique($newCatIds) as $cid) {
            $categoryData[] = ['id' => $cid];
        }

        $updateResult = apiRequest('PATCH', "/product/{$prod['id']}", [
            'categories' => $categoryData,
        ], $config);

        if ($updateResult['code'] === 204) {
            echo "  ✓ " . substr($name, 0, 50) . "\n";
            $updated++;
        } else {
            echo "  ✗ " . substr($name, 0, 50) . " (HTTP {$updateResult['code']})\n";
        }
        usleep(50000); // 50ms delay
    } else {
        $skipped++;
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                       COMPLETE                             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "  Products updated: $updated\n";
echo "  Products skipped: $skipped\n";
echo "\n";
