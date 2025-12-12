<?php
/**
 * Shopware Product Description & Category Updater
 *
 * Updates existing Snigel products with:
 * - Full descriptions from B2B portal
 * - Subcategory assignments
 *
 * Usage: php shopware-update-descriptions.php
 */

// Configuration for CHF installation
$config = [
    'shopware_url' => 'http://localhost',  // Changed for running inside container
    'api_user' => 'admin',
    'api_password' => 'shopware',
    'json_input' => '/tmp/products-with-descriptions.json',  // Path inside container
    'dry_run' => false,
    'log_file' => '/tmp/update-descriptions.log',
];

// Snigel category mapping - B2B portal categories to German names
$categoryMapping = [
    'Bags & backpacks' => 'Taschen & Rucksäcke',
    'Belts' => 'Gürtel',
    'Tactical clothing' => 'Taktische Bekleidung',
    'Holders & pouches' => 'Holster & Taschen',
    'Ballistic protection' => 'Ballistischer Schutz',
    'Slings & holsters' => 'Tragegurte & Holster',
    'Vests & Chest rigs' => 'Westen & Chest Rigs',
    'Leg panels' => 'Beinpanels',
    'Medical gear' => 'Medizinische Ausrüstung',
    'Police gear' => 'Polizeiausrüstung',
    'Patches' => 'Patches',
    'Tactical gear' => 'Taktische Ausrüstung',
    'Covert gear' => 'Verdeckte Ausrüstung',
    'Multicam' => 'Multicam',
    'Sniper gear' => 'Scharfschützen-Ausrüstung',
    'The Brand' => 'Die Marke',
    'Miscellaneous products' => 'Verschiedene Produkte',
    'K9-units gear' => 'K9-Einheiten Ausrüstung',
    'Source® hydration' => 'Source® Hydration',
    'Admin products' => 'Verwaltungsprodukte',
    'HighVis' => 'Warnschutz',
];

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║     SHOPWARE - UPDATE DESCRIPTIONS & CATEGORIES           ║\n";
echo "║     Target: {$config['shopware_url']}                     \n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Load products with descriptions
if (!file_exists($config['json_input'])) {
    die("Error: Products JSON not found: {$config['json_input']}\n");
}

$products = json_decode(file_get_contents($config['json_input']), true);
echo "Loaded " . count($products) . " products\n\n";

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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo "Auth failed: HTTP $httpCode\n";
        return null;
    }

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

// Step 2: Get Snigel parent category
echo "Step 2: Getting Snigel parent category...\n";
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Snigel']]
], $config);

$snigelCategoryId = $result['body']['data'][0]['id'] ?? null;
if (!$snigelCategoryId) {
    die("  ERROR: Snigel category not found!\n");
}
echo "  Snigel Category ID: $snigelCategoryId\n\n";

// Step 3: Create subcategories
echo "Step 3: Creating subcategories...\n";
$subcategoryIds = [];

foreach ($categoryMapping as $englishName => $germanName) {
    // Check if subcategory exists
    $result = apiRequest('POST', '/search/category', [
        'filter' => [
            ['type' => 'equals', 'field' => 'name', 'value' => $germanName],
            ['type' => 'equals', 'field' => 'parentId', 'value' => $snigelCategoryId],
        ]
    ], $config);

    if (!empty($result['body']['data'][0]['id'])) {
        $subcategoryIds[$englishName] = $result['body']['data'][0]['id'];
        echo "  ✓ Found: $germanName\n";
    } else {
        // Create subcategory
        $subcategoryId = bin2hex(random_bytes(16));
        $createResult = apiRequest('POST', '/category', [
            'id' => $subcategoryId,
            'name' => $germanName,
            'parentId' => $snigelCategoryId,
            'active' => true,
            'displayNestedProducts' => true,
        ], $config);

        if ($createResult['code'] === 204 || $createResult['code'] === 200) {
            $subcategoryIds[$englishName] = $subcategoryId;
            echo "  + Created: $germanName\n";
        } else {
            echo "  ! Failed to create: $germanName (HTTP {$createResult['code']})\n";
        }
    }
}
echo "\n";

// Step 4: Get all existing Shopware products
echo "Step 4: Getting existing products from Shopware...\n";
$shopwareProducts = [];
$page = 1;
$limit = 100;

do {
    $result = apiRequest('POST', '/search/product', [
        'page' => $page,
        'limit' => $limit,
        'includes' => ['product' => ['id', 'productNumber', 'name', 'description']],
        'associations' => ['categories' => []],
    ], $config);

    $data = $result['body']['data'] ?? [];
    foreach ($data as $product) {
        $shopwareProducts[$product['productNumber']] = $product;
    }

    $page++;
} while (count($data) === $limit);

echo "  Found " . count($shopwareProducts) . " products in Shopware\n\n";

// Step 5: Update products with descriptions and categories
echo "Step 5: Updating products...\n\n";

$updated = 0;
$skipped = 0;
$errors = 0;
$total = count($products);

foreach ($products as $index => $product) {
    $progress = "[" . ($index + 1) . "/$total]";
    $productNumber = 'SNIGEL-' . strtoupper(str_replace(['-', '_'], '', $product['slug'] ?? ''));
    $productNumber = substr($productNumber, 0, 64); // Max length

    // Find in Shopware
    $shopwareProduct = null;
    foreach ($shopwareProducts as $spn => $sp) {
        if (stripos($sp['name'], $product['name']) !== false || $spn === $productNumber) {
            $shopwareProduct = $sp;
            break;
        }
    }

    if (!$shopwareProduct) {
        echo "$progress {$product['name']} - NOT FOUND in Shopware\n";
        $skipped++;
        continue;
    }

    $productId = $shopwareProduct['id'];
    $updateData = [];

    // Add description if available and not already set
    // First try description_html, then fallback to plain description
    $descToUse = '';
    if (!empty($product['description_html']) && strlen($product['description_html']) > 50) {
        $descToUse = $product['description_html'];
    } elseif (!empty($product['description']) && strlen($product['description']) > 50) {
        // Convert plain text to HTML paragraphs
        $descToUse = '<p>' . nl2br(htmlspecialchars($product['description'])) . '</p>';
    }

    if ($descToUse) {
        $currentDesc = $shopwareProduct['description'] ?? '';
        if (strlen($currentDesc) < 50) {
            $updateData['description'] = $descToUse;
        }
    }

    // Add category if available
    $categoryId = null;
    if (!empty($product['categories'][0])) {
        $catName = $product['categories'][0];
        $categoryId = $subcategoryIds[$catName] ?? null;
    }

    if ($categoryId) {
        $updateData['categories'] = [
            ['id' => $snigelCategoryId],
            ['id' => $categoryId],
        ];
    }

    // Skip if nothing to update
    if (empty($updateData)) {
        $skipped++;
        continue;
    }

    // Update product
    if (!$config['dry_run']) {
        $result = apiRequest('PATCH', "/product/$productId", $updateData, $config);

        if ($result['code'] === 204 || $result['code'] === 200) {
            $hasDesc = isset($updateData['description']) ? 'DESC' : '';
            $hasCat = isset($updateData['categories']) ? 'CAT' : '';
            echo "$progress ✓ {$product['name']} - Updated: $hasDesc $hasCat\n";
            $updated++;
        } else {
            echo "$progress ✗ {$product['name']} - Error (HTTP {$result['code']})\n";
            $errors++;
        }
    } else {
        echo "$progress [DRY RUN] Would update: {$product['name']}\n";
        $updated++;
    }

    // Small delay to avoid rate limiting
    usleep(100000); // 100ms
}

// Summary
echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                       COMPLETE                              ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "  Total products: $total\n";
echo "  Updated: $updated\n";
echo "  Skipped: $skipped\n";
echo "  Errors: $errors\n";
if ($config['dry_run']) {
    echo "  (DRY RUN - no changes made)\n";
}
echo "\n";
