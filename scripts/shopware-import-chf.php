<?php
/**
 * Shopware Product Importer for CHF Installation
 * Imports products to the NEW CHF-based Shopware installation
 *
 * Usage: php shopware-import-chf.php
 */

// Configuration for CHF installation
$config = [
    'shopware_url' => 'http://new.ortak.ch:8080',
    'api_user' => 'admin',
    'api_password' => 'shopware',
    'json_input' => __DIR__ . '/snigel-merged-products.json',
    'images_dir' => __DIR__ . '/snigel-data/images',
    'manufacturer_name' => 'Snigel',
    'tax_rate' => 8.1,  // Swiss VAT
    'sales_channel_name' => 'Storefront',
    'eur_to_chf_rate' => 1.0638,  // 1 EUR = 1.0638 CHF (approx)
    'dry_run' => false,
    'log_file' => __DIR__ . '/import-chf.log',
];

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║     SHOPWARE CHF IMPORT - NEW INSTALLATION                 ║\n";
echo "║     Target: {$config['shopware_url']}                      \n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Load products
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

// Step 2: Get required IDs
echo "Step 2: Getting Shopware configuration...\n";

// Get/create manufacturer
$result = apiRequest('POST', '/search/product-manufacturer', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => $config['manufacturer_name']]]
], $config);

if (!empty($result['body']['data'][0]['id'])) {
    $manufacturerId = $result['body']['data'][0]['id'];
} else {
    $manufacturerId = bin2hex(random_bytes(16));
    apiRequest('POST', '/product-manufacturer', [
        'id' => $manufacturerId,
        'name' => $config['manufacturer_name'],
    ], $config);
}
echo "  Manufacturer ID: $manufacturerId\n";

// Get tax ID
$result = apiRequest('POST', '/search/tax', [
    'filter' => [['type' => 'equals', 'field' => 'taxRate', 'value' => $config['tax_rate']]]
], $config);

if (!empty($result['body']['data'][0]['id'])) {
    $taxId = $result['body']['data'][0]['id'];
} else {
    $taxId = bin2hex(random_bytes(16));
    apiRequest('POST', '/tax', [
        'id' => $taxId,
        'taxRate' => $config['tax_rate'],
        'name' => $config['tax_rate'] . '% MwSt',
    ], $config);
}
echo "  Tax ID: $taxId\n";

// Get CHF currency (should be default now)
$result = apiRequest('POST', '/search/currency', [
    'filter' => [['type' => 'equals', 'field' => 'isoCode', 'value' => 'CHF']]
], $config);
$chfCurrencyId = $result['body']['data'][0]['id'] ?? null;
echo "  CHF Currency ID: $chfCurrencyId\n";

// Get EUR currency
$result = apiRequest('POST', '/search/currency', [
    'filter' => [['type' => 'equals', 'field' => 'isoCode', 'value' => 'EUR']]
], $config);
$eurCurrencyId = $result['body']['data'][0]['id'] ?? null;
echo "  EUR Currency ID: $eurCurrencyId\n";

// Get sales channel
$result = apiRequest('POST', '/search/sales-channel', [
    'filter' => [['type' => 'contains', 'field' => 'name', 'value' => $config['sales_channel_name']]]
], $config);
$salesChannelId = $result['body']['data'][0]['id'] ?? null;
echo "  Sales Channel ID: $salesChannelId\n";

// Get/create Snigel category
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Snigel']]
], $config);

if (!empty($result['body']['data'][0]['id'])) {
    $snigelCategoryId = $result['body']['data'][0]['id'];
} else {
    // Get root category first
    $result = apiRequest('POST', '/search/category', [
        'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => null]],
        'limit' => 1
    ], $config);
    $rootCategoryId = $result['body']['data'][0]['id'] ?? null;

    // Create Snigel category under root
    $snigelCategoryId = bin2hex(random_bytes(16));
    apiRequest('POST', '/category', [
        'id' => $snigelCategoryId,
        'name' => 'Snigel',
        'parentId' => $rootCategoryId,
        'active' => true,
        'displayNestedProducts' => true,
    ], $config);
}
echo "  Snigel Category ID: $snigelCategoryId\n\n";

if (!$chfCurrencyId) {
    die("ERROR: CHF currency not found! Check Shopware installation.\n");
}

// Step 3: Import products
echo "Step 3: Importing products...\n\n";

$created = 0;
$updated = 0;
$errors = 0;
$count = 0;
$total = count($products);

foreach ($products as $product) {
    $count++;
    $productNumber = !empty($product['article_no']) ? $product['article_no'] : 'SN-' . $product['slug'];

    $displayName = mb_substr($product['name'], 0, 40);
    echo "  [$count/$total] $displayName... ";

    if ($config['dry_run']) {
        echo "SKIP (dry run)\n";
        continue;
    }

    try {
        // Calculate prices - B2B price is in EUR, convert to CHF
        $purchasePriceEUR = !empty($product['b2b_price_eur']) ? round($product['b2b_price_eur'], 2) : 10.00;

        // Selling price - use RRP or calculate with 50% markup
        if (!empty($product['rrp_eur'])) {
            $sellingPriceEUR = round($product['rrp_eur'], 2);
        } else {
            $sellingPriceEUR = round($purchasePriceEUR * 1.5, 2);
        }

        // Convert to CHF (CHF is now the base currency!)
        $purchasePriceCHF = round($purchasePriceEUR * $config['eur_to_chf_rate'], 2);
        $sellingPriceCHF = round($sellingPriceEUR * $config['eur_to_chf_rate'], 2);

        // Check if product exists
        $result = apiRequest('POST', '/search/product', [
            'filter' => [['type' => 'equals', 'field' => 'productNumber', 'value' => $productNumber]]
        ], $config);
        $existingId = $result['body']['data'][0]['id'] ?? null;

        // Prepare product data - CHF is base currency
        $productData = [
            'name' => $product['name'],
            'productNumber' => $productNumber,
            'stock' => $product['stock'] ?? 100,
            'active' => true,
            'manufacturerId' => $manufacturerId,
            'taxId' => $taxId,
            'price' => [
                // CHF price (base currency)
                [
                    'currencyId' => $chfCurrencyId,
                    'gross' => $sellingPriceCHF,
                    'net' => round($sellingPriceCHF / (1 + $config['tax_rate'] / 100), 2),
                    'linked' => false,
                ],
            ],
            'purchasePrices' => [
                [
                    'currencyId' => $chfCurrencyId,
                    'gross' => $purchasePriceCHF,
                    'net' => round($purchasePriceCHF / (1 + $config['tax_rate'] / 100), 2),
                    'linked' => false,
                ],
            ],
            'description' => $product['description'] ?? $product['short_description'] ?? '',
            'metaDescription' => mb_substr($product['short_description'] ?? '', 0, 160),
            'ean' => $product['ean'] ?? null,
            'weight' => !empty($product['weight_g']) ? $product['weight_g'] / 1000 : null,
        ];

        // Add EUR price if currency exists
        if ($eurCurrencyId) {
            $productData['price'][] = [
                'currencyId' => $eurCurrencyId,
                'gross' => $sellingPriceEUR,
                'net' => round($sellingPriceEUR / (1 + $config['tax_rate'] / 100), 2),
                'linked' => false,
            ];
        }

        if ($existingId) {
            // Update existing
            $result = apiRequest('PATCH', "/product/$existingId", $productData, $config);
            if ($result['code'] === 204 || $result['code'] === 200) {
                echo "UPDATED (CHF $sellingPriceCHF)\n";
                $updated++;
            } else {
                echo "ERROR\n";
                $errors++;
            }
        } else {
            // Create new
            $productData['id'] = bin2hex(random_bytes(16));
            $productData['categories'] = [['id' => $snigelCategoryId]];

            if ($salesChannelId) {
                $productData['visibilities'] = [[
                    'salesChannelId' => $salesChannelId,
                    'visibility' => 30,
                ]];
            }

            $result = apiRequest('POST', '/product', $productData, $config);
            if ($result['code'] === 204 || $result['code'] === 200) {
                echo "CREATED (CHF $sellingPriceCHF)\n";
                $created++;
            } else {
                echo "ERROR\n";
                file_put_contents($config['log_file'],
                    date('Y-m-d H:i:s') . " FAILED: {$product['name']} - " . json_encode($result['body']) . "\n",
                    FILE_APPEND);
                $errors++;
            }
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                    IMPORT COMPLETE                         ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
echo "║  Products created: " . str_pad($created, 38) . "║\n";
echo "║  Products updated: " . str_pad($updated, 38) . "║\n";
echo "║  Errors:           " . str_pad($errors, 38) . "║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

if ($errors > 0) {
    echo "Check {$config['log_file']} for error details.\n";
}

echo "Done! Visit {$config['shopware_url']}/admin to verify.\n\n";
