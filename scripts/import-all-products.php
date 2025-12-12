<?php
/**
 * Import ALL missing products from OLD site to NEW CHF site
 * Converts EUR prices to CHF (rate: 1.0638)
 */

$OLD_URL = 'https://ortak.ch';
$NEW_URL = 'http://77.42.19.154:8080';
$EUR_TO_CHF = 1.0638;

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => 'admin',
            'password' => 'shopware'
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true)['access_token'] ?? null;
}

function apiPost($baseUrl, $token, $endpoint, $data) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

function apiGet($baseUrl, $token, $endpoint) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

echo "═══════════════════════════════════════════════════════════\n";
echo "     IMPORT ALL MISSING PRODUCTS: OLD → NEW (CHF)          \n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Get tokens
echo "🔑 Getting API tokens...\n";
$oldToken = getToken($OLD_URL);
$newToken = getToken($NEW_URL);

if (!$oldToken || !$newToken) {
    die("❌ Failed to get tokens\n");
}
echo "   ✅ Tokens obtained\n\n";

// Get all products from OLD - fetch ALL pages
echo "📦 Fetching ALL products from OLD site...\n";
$oldProducts = [];
$page = 1;
$limit = 100;
$total = 0;

do {
    $response = apiPost($OLD_URL, $oldToken, 'search/product', [
        'limit' => $limit,
        'page' => $page,
        'total-count-mode' => 1,
        'associations' => [
            'categories' => [],
            'manufacturer' => []
        ]
    ]);

    if (!empty($response['data']['data'])) {
        $oldProducts = array_merge($oldProducts, $response['data']['data']);
        $fetched = count($response['data']['data']);
        echo "   Page {$page}: {$fetched} products\n";
    }

    $total = $response['data']['meta']['total'] ?? 0;
    $page++;
} while (count($oldProducts) < $total && $page <= 10);

echo "   Total fetched: " . count($oldProducts) . " / {$total} products\n\n";

// Get all products from NEW (to check which already exist)
echo "📦 Fetching existing products from NEW site...\n";
$newProductSkus = [];
$page = 1;
$total = 0;

do {
    $response = apiPost($NEW_URL, $newToken, 'search/product', [
        'limit' => $limit,
        'page' => $page,
        'total-count-mode' => 1
    ]);

    if (!empty($response['data']['data'])) {
        foreach ($response['data']['data'] as $p) {
            $sku = $p['attributes']['productNumber'] ?? $p['productNumber'] ?? '';
            if ($sku) {
                $newProductSkus[$sku] = true;
            }
        }
    }

    $total = $response['data']['meta']['total'] ?? 0;
    $page++;
} while (count($newProductSkus) < $total && $page <= 10);

echo "   Existing products: " . count($newProductSkus) . "\n";

// Show some existing SKUs
echo "   Sample existing SKUs: " . implode(', ', array_slice(array_keys($newProductSkus), 0, 5)) . "\n\n";

// Get NEW site's tax ID, sales channel
echo "📋 Getting NEW site configuration...\n";
$taxResponse = apiGet($NEW_URL, $newToken, 'tax?limit=1');
$taxId = $taxResponse['data'][0]['id'] ?? null;
echo "   Tax ID: {$taxId}\n";

$scResponse = apiPost($NEW_URL, $newToken, 'search/sales-channel', [
    'filter' => [['type' => 'equals', 'field' => 'typeId', 'value' => '8a243080f92e4c719546314b577cf82b']]
]);
$salesChannelId = $scResponse['data']['data'][0]['id'] ?? null;
echo "   Sales Channel ID: {$salesChannelId}\n";

// Get currency ID (CHF)
$currencyResponse = apiPost($NEW_URL, $newToken, 'search/currency', [
    'filter' => [['type' => 'equals', 'field' => 'isoCode', 'value' => 'CHF']]
]);
$chfCurrencyId = $currencyResponse['data']['data'][0]['id'] ?? null;
echo "   CHF Currency ID: {$chfCurrencyId}\n";

// Get or create categories
echo "\n📂 Setting up categories...\n";
$categoryMap = [];

// Get existing categories from NEW
$catResponse = apiPost($NEW_URL, $newToken, 'search/category', ['limit' => 500]);
foreach ($catResponse['data']['data'] ?? [] as $cat) {
    $name = $cat['name'] ?? $cat['translated']['name'] ?? '';
    if ($name) {
        $categoryMap[$name] = $cat['id'];
    }
}
echo "   Existing categories: " . implode(', ', array_keys($categoryMap)) . "\n";

// Get root category
$rootCatResponse = apiPost($NEW_URL, $newToken, 'search/category', [
    'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => null]],
    'limit' => 1
]);
$rootCategoryId = $rootCatResponse['data']['data'][0]['id'] ?? null;

// Get "Home" or navigation category
$navCatResponse = apiPost($NEW_URL, $newToken, 'search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Home']],
    'limit' => 1
]);
$homeCategoryId = $navCatResponse['data']['data'][0]['id'] ?? $rootCategoryId;

// Create missing categories
$categoriesToCreate = ['Raven Weapons', 'Raven Caliber Kit', 'Waffenzubehör', 'Alle Produkte'];
foreach ($categoriesToCreate as $catName) {
    if (!isset($categoryMap[$catName])) {
        $newCatId = bin2hex(random_bytes(16));
        $result = apiPost($NEW_URL, $newToken, 'category', [
            'id' => $newCatId,
            'name' => $catName,
            'parentId' => $homeCategoryId,
            'active' => true
        ]);
        if ($result['code'] >= 200 && $result['code'] < 300 || $result['code'] == 204) {
            $categoryMap[$catName] = $newCatId;
            echo "   ✅ Created category: {$catName}\n";
        }
    }
}

echo "\n🚀 Importing missing products...\n\n";
$imported = 0;
$skipped = 0;
$errors = 0;

foreach ($oldProducts as $product) {
    // Handle both direct and attributes structure
    $sku = $product['attributes']['productNumber'] ?? $product['productNumber'] ?? '';
    $name = $product['attributes']['name'] ?? $product['name'] ?? $product['translated']['name'] ?? 'Unknown';

    // Skip if already exists (case insensitive check)
    $skuLower = strtolower($sku);
    $exists = false;
    foreach ($newProductSkus as $existingSku => $v) {
        if (strtolower($existingSku) === $skuLower) {
            $exists = true;
            break;
        }
    }

    if ($exists) {
        $skipped++;
        continue;
    }

    // Skip if no SKU
    if (empty($sku)) {
        $errors++;
        continue;
    }

    // Get price and convert to CHF (handle attributes structure)
    $priceData = $product['attributes']['price'] ?? $product['price'] ?? [];
    $price = 0;
    if (!empty($priceData[0]['gross'])) {
        $price = round($priceData[0]['gross'] * $EUR_TO_CHF, 2);
    }

    // Determine category
    $categoryId = $categoryMap['Alle Produkte'] ?? $homeCategoryId;
    $categoryName = 'Alle Produkte';

    // Check product name/SKU to assign correct category
    if (stripos($sku, 'RAVEN-') === 0 || stripos($name, 'RAVEN') !== false) {
        if (isset($categoryMap['Raven Weapons'])) {
            $categoryId = $categoryMap['Raven Weapons'];
            $categoryName = 'Raven Weapons';
        }
    } elseif (stripos($sku, 'KIT-') === 0 || stripos($name, 'CALIBER KIT') !== false) {
        if (isset($categoryMap['Raven Caliber Kit'])) {
            $categoryId = $categoryMap['Raven Caliber Kit'];
            $categoryName = 'Raven Caliber Kit';
        }
    } elseif (stripos($sku, 'SN-') === 0 || stripos($name, 'Snigel') !== false) {
        if (isset($categoryMap['Snigel'])) {
            $categoryId = $categoryMap['Snigel'];
            $categoryName = 'Snigel';
        }
    }

    // Also check from OLD categories
    if (!empty($product['categories'])) {
        foreach ($product['categories'] as $cat) {
            $oldCatName = $cat['name'] ?? $cat['translated']['name'] ?? '';
            if ($oldCatName && isset($categoryMap[$oldCatName])) {
                $categoryId = $categoryMap[$oldCatName];
                $categoryName = $oldCatName;
                break;
            }
        }
    }

    // Prepare product data
    $newProductId = bin2hex(random_bytes(16));

    // Get other fields from attributes
    $stock = $product['attributes']['stock'] ?? $product['stock'] ?? 100;
    $active = $product['attributes']['active'] ?? $product['active'] ?? true;
    $description = $product['attributes']['description'] ?? $product['description'] ?? $product['translated']['description'] ?? '';

    $productData = [
        'id' => $newProductId,
        'name' => $name,
        'productNumber' => $sku,
        'stock' => $stock,
        'taxId' => $taxId,
        'price' => [[
            'currencyId' => $chfCurrencyId,
            'gross' => $price,
            'net' => round($price / 1.077, 2),
            'linked' => true
        ]],
        'active' => $active,
        'description' => $description,
        'categories' => [['id' => $categoryId]],
        'visibilities' => [[
            'salesChannelId' => $salesChannelId,
            'visibility' => 30
        ]]
    ];

    // Create product
    $result = apiPost($NEW_URL, $newToken, 'product', $productData);

    if ($result['code'] >= 200 && $result['code'] < 300 || $result['code'] == 204) {
        $imported++;
        echo "   ✅ [{$imported}] {$sku}: {$name} → CHF {$price} ({$categoryName})\n";
        $newProductSkus[$sku] = true; // Track newly added
    } else {
        $errors++;
        $errorMsg = $result['data']['errors'][0]['detail'] ?? json_encode($result['data']);
        echo "   ❌ {$sku}: " . substr($errorMsg, 0, 100) . "\n";
    }

    // Rate limit
    if (($imported + $errors) % 20 == 0) {
        usleep(300000);
    }
}

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "                    IMPORT COMPLETE!                        \n";
echo "═══════════════════════════════════════════════════════════\n";
echo "   ✅ Imported: {$imported} products\n";
echo "   ⏭️ Skipped (already exist): {$skipped} products\n";
echo "   ❌ Errors: {$errors} products\n";
echo "   📦 Total in NEW site: " . ($imported + count($newProductSkus)) . " products\n";
echo "═══════════════════════════════════════════════════════════\n";
