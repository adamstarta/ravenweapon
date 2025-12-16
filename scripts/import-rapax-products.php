<?php
/**
 * Rapax Products Importer for Shopware
 * Imports scraped Rapax and Caracal Lynx products to Shopware
 *
 * Usage: php import-rapax-products.php
 */

// Configuration
$config = [
    'shopware_url' => 'https://ortak.ch',  // Target shop
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
    'json_input' => __DIR__ . '/rapax-data/rapax-products.json',
    'manufacturer_name' => 'RAPAX',
    'tax_rate' => 8.1,  // Swiss VAT
    'sales_channel_name' => 'Storefront',
    'dry_run' => false,  // Set true to test without creating products
    'log_file' => __DIR__ . '/import-rapax.log',
    'skip_existing' => true,  // Skip products that already exist
];

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║     RAPAX PRODUCTS IMPORTER - SHOPWARE                             ║\n";
echo "║     Target: {$config['shopware_url']}                               \n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// Load products
if (!file_exists($config['json_input'])) {
    die("Error: Products JSON not found: {$config['json_input']}\n");
}

$data = json_decode(file_get_contents($config['json_input']), true);
$products = $data['products'] ?? [];
echo "Loaded " . count($products) . " products from JSON\n";
echo "Categories: " . count($data['categories'] ?? []) . "\n\n";

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
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo "Auth failed: HTTP $httpCode\n";
        echo "Response: $response\n";
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
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
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

function parsePrice($priceStr) {
    // Parse price like "2'950.00" or "3,150.00"
    $price = preg_replace("/[^0-9.]/", "", str_replace("'", "", $priceStr));
    return floatval($price);
}

function uploadMediaFromUrl($imageUrl, $productId, $config) {
    // Download image
    $imageData = @file_get_contents($imageUrl);
    if (!$imageData) {
        return null;
    }

    // Get filename from URL
    $urlParts = parse_url($imageUrl);
    $filename = basename($urlParts['path']);
    $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg';

    // Create media entry
    $mediaId = bin2hex(random_bytes(16));
    $result = apiRequest('POST', '/media', [
        'id' => $mediaId,
    ], $config);

    if ($result['code'] !== 204 && $result['code'] !== 200) {
        return null;
    }

    // Upload the image
    $uploadUrl = $config['shopware_url'] . "/api/_action/media/$mediaId/upload?extension=$extension&fileName=" . urlencode(pathinfo($filename, PATHINFO_FILENAME));

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $uploadUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . getAccessToken($config),
            'Content-Type: image/' . $extension,
        ],
        CURLOPT_POSTFIELDS => $imageData,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 204 || $httpCode === 200) {
        return $mediaId;
    }

    return null;
}

// Step 1: Authenticate
echo "Step 1: Authenticating...\n";
$token = getAccessToken($config);
if (!$token) {
    die("  ERROR: Failed to authenticate! Check your credentials.\n");
}
echo "  ✓ Authenticated successfully\n\n";

// Step 2: Get required IDs
echo "Step 2: Getting Shopware configuration...\n";

// Get/create RAPAX manufacturer
$result = apiRequest('POST', '/search/product-manufacturer', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => $config['manufacturer_name']]]
], $config);

if (!empty($result['body']['data'][0]['id'])) {
    $manufacturerId = $result['body']['data'][0]['id'];
    echo "  ✓ Found manufacturer: {$config['manufacturer_name']}\n";
} else {
    $manufacturerId = bin2hex(random_bytes(16));
    apiRequest('POST', '/product-manufacturer', [
        'id' => $manufacturerId,
        'name' => $config['manufacturer_name'],
    ], $config);
    echo "  ✓ Created manufacturer: {$config['manufacturer_name']}\n";
}

// Also create Caracal manufacturer
$result = apiRequest('POST', '/search/product-manufacturer', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Caracal']]
], $config);

if (!empty($result['body']['data'][0]['id'])) {
    $caracalManufacturerId = $result['body']['data'][0]['id'];
} else {
    $caracalManufacturerId = bin2hex(random_bytes(16));
    apiRequest('POST', '/product-manufacturer', [
        'id' => $caracalManufacturerId,
        'name' => 'Caracal',
    ], $config);
    echo "  ✓ Created manufacturer: Caracal\n";
}

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
echo "  ✓ Tax ID: $taxId\n";

// Get CHF currency
$result = apiRequest('POST', '/search/currency', [
    'filter' => [['type' => 'equals', 'field' => 'isoCode', 'value' => 'CHF']]
], $config);
$chfCurrencyId = $result['body']['data'][0]['id'] ?? null;
echo "  ✓ CHF Currency ID: $chfCurrencyId\n";

// Get sales channel
$result = apiRequest('POST', '/search/sales-channel', [
    'filter' => [['type' => 'contains', 'field' => 'name', 'value' => $config['sales_channel_name']]]
], $config);
$salesChannelId = $result['body']['data'][0]['id'] ?? null;
echo "  ✓ Sales Channel ID: $salesChannelId\n";

// Get root category
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => null]],
    'limit' => 1
], $config);
$rootCategoryId = $result['body']['data'][0]['id'] ?? null;

// Create/get category structure: Weapons > RAPAX
$categoryCache = [];

function getOrCreateCategory($name, $parentId, $config, &$cache) {
    $cacheKey = $parentId . '_' . $name;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $result = apiRequest('POST', '/search/category', [
        'filter' => [
            ['type' => 'equals', 'field' => 'name', 'value' => $name],
            ['type' => 'equals', 'field' => 'parentId', 'value' => $parentId]
        ]
    ], $config);

    if (!empty($result['body']['data'][0]['id'])) {
        $cache[$cacheKey] = $result['body']['data'][0]['id'];
        return $cache[$cacheKey];
    }

    // Create category
    $categoryId = bin2hex(random_bytes(16));
    $result = apiRequest('POST', '/category', [
        'id' => $categoryId,
        'name' => $name,
        'parentId' => $parentId,
        'active' => true,
        'displayNestedProducts' => true,
    ], $config);

    if ($result['code'] === 204 || $result['code'] === 200) {
        $cache[$cacheKey] = $categoryId;
        echo "    Created category: $name\n";
        return $categoryId;
    }

    return null;
}

echo "\nStep 3: Creating category structure...\n";

// Create Weapons > RAPAX > RAPAX and Weapons > RAPAX > Caracal Lynx
$weaponsId = getOrCreateCategory('Weapons', $rootCategoryId, $config, $categoryCache);
$rapaxMainId = getOrCreateCategory('RAPAX', $weaponsId, $config, $categoryCache);
$rapaxSubId = getOrCreateCategory('RAPAX', $rapaxMainId, $config, $categoryCache);
$caracalId = getOrCreateCategory('Caracal Lynx', $rapaxMainId, $config, $categoryCache);

// Create subcategories
$rxSportId = getOrCreateCategory('RX Sport', $rapaxSubId, $config, $categoryCache);
$rxTacticalId = getOrCreateCategory('RX Tactical', $rapaxSubId, $config, $categoryCache);
$rxCompactId = getOrCreateCategory('RX Compact', $rapaxSubId, $config, $categoryCache);
$lynxSportId = getOrCreateCategory('LYNX SPORT', $caracalId, $config, $categoryCache);
$lynxOpenId = getOrCreateCategory('LYNX OPEN', $caracalId, $config, $categoryCache);
$lynxCompactId = getOrCreateCategory('LYNX COMPACT', $caracalId, $config, $categoryCache);

echo "  ✓ Category structure created\n\n";

// Map category paths to IDs
$categoryMap = [
    'Weapons > RAPAX > RAPAX > RX Sport' => $rxSportId,
    'Weapons > RAPAX > RAPAX > RX Tactical' => $rxTacticalId,
    'Weapons > RAPAX > RAPAX > RX Compact' => $rxCompactId,
    'Weapons > RAPAX > Caracal Lynx > LYNX SPORT' => $lynxSportId,
    'Weapons > RAPAX > Caracal Lynx > LYNX OPEN' => $lynxOpenId,
    'Weapons > RAPAX > Caracal Lynx > LYNX COMPACT' => $lynxCompactId,
];

if (!$chfCurrencyId) {
    die("ERROR: CHF currency not found! Check Shopware installation.\n");
}

// Step 4: Import products
echo "Step 4: Importing products...\n\n";

$created = 0;
$updated = 0;
$skipped = 0;
$errors = 0;
$count = 0;
$total = count($products);

foreach ($products as $product) {
    $count++;
    $productNumber = $product['articleNumber'] ?? 'RAPAX-' . $count;

    $displayName = mb_substr($product['name'], 0, 45);
    echo "  [$count/$total] $displayName... ";

    if ($config['dry_run']) {
        echo "SKIP (dry run)\n";
        continue;
    }

    try {
        // Parse price
        $price = parsePrice($product['price']);
        if ($price <= 0) {
            echo "ERROR (invalid price)\n";
            $errors++;
            continue;
        }

        // Check if product exists
        $result = apiRequest('POST', '/search/product', [
            'filter' => [['type' => 'equals', 'field' => 'productNumber', 'value' => $productNumber]]
        ], $config);
        $existingId = $result['body']['data'][0]['id'] ?? null;

        if ($existingId && $config['skip_existing']) {
            echo "SKIP (exists)\n";
            $skipped++;
            continue;
        }

        // Determine manufacturer based on product name
        $mfgId = (stripos($product['name'], 'Caracal') !== false || stripos($product['name'], 'Lynx') !== false)
            ? $caracalManufacturerId
            : $manufacturerId;

        // Get category ID
        $categoryId = $categoryMap[$product['category']] ?? $rapaxMainId;

        // Build description
        $description = $product['description'] ?? '';
        if (!empty($product['specifications'])) {
            $specs = $product['specifications'];
            $description = "<p><strong>Specifications:</strong></p><ul>";
            foreach ($specs as $key => $value) {
                $description .= "<li><strong>" . ucfirst($key) . ":</strong> $value</li>";
            }
            $description .= "</ul>";
        }

        // Prepare product data
        $productData = [
            'name' => $product['name'],
            'productNumber' => $productNumber,
            'stock' => 10,
            'active' => true,
            'manufacturerId' => $mfgId,
            'taxId' => $taxId,
            'price' => [
                [
                    'currencyId' => $chfCurrencyId,
                    'gross' => $price,
                    'net' => round($price / (1 + $config['tax_rate'] / 100), 2),
                    'linked' => false,
                ],
            ],
            'description' => $description,
            'metaTitle' => $product['name'],
            'metaDescription' => mb_substr(strip_tags($description), 0, 160),
        ];

        if ($existingId) {
            // Update existing
            $result = apiRequest('PATCH', "/product/$existingId", $productData, $config);
            if ($result['code'] === 204 || $result['code'] === 200) {
                echo "UPDATED (CHF $price)\n";
                $updated++;
            } else {
                echo "ERROR (update failed)\n";
                file_put_contents($config['log_file'],
                    date('Y-m-d H:i:s') . " UPDATE FAILED: {$product['name']} - " . json_encode($result['body']) . "\n",
                    FILE_APPEND);
                $errors++;
            }
        } else {
            // Create new
            $productData['id'] = bin2hex(random_bytes(16));
            $productData['categories'] = [['id' => $categoryId]];

            if ($salesChannelId) {
                $productData['visibilities'] = [[
                    'salesChannelId' => $salesChannelId,
                    'visibility' => 30,
                ]];
            }

            $result = apiRequest('POST', '/product', $productData, $config);
            if ($result['code'] === 204 || $result['code'] === 200) {
                echo "CREATED (CHF $price)\n";
                $created++;

                // Upload first image if available
                if (!empty($product['images'])) {
                    foreach ($product['images'] as $imageUrl) {
                        // Skip logo images
                        if (stripos($imageUrl, 'logo') !== false) continue;

                        $mediaId = uploadMediaFromUrl($imageUrl, $productData['id'], $config);
                        if ($mediaId) {
                            // Assign to product
                            apiRequest('POST', '/product-media', [
                                'productId' => $productData['id'],
                                'mediaId' => $mediaId,
                                'position' => 0,
                            ], $config);
                            echo "    ✓ Image uploaded\n";
                            break; // Only upload first product image
                        }
                    }
                }
            } else {
                echo "ERROR (create failed)\n";
                file_put_contents($config['log_file'],
                    date('Y-m-d H:i:s') . " CREATE FAILED: {$product['name']} - " . json_encode($result['body']) . "\n",
                    FILE_APPEND);
                $errors++;
            }
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        file_put_contents($config['log_file'],
            date('Y-m-d H:i:s') . " EXCEPTION: {$product['name']} - " . $e->getMessage() . "\n",
            FILE_APPEND);
        $errors++;
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║                         IMPORT COMPLETE                            ║\n";
echo "╠════════════════════════════════════════════════════════════════════╣\n";
echo "║  Products created:  " . str_pad($created, 48) . "║\n";
echo "║  Products updated:  " . str_pad($updated, 48) . "║\n";
echo "║  Products skipped:  " . str_pad($skipped, 48) . "║\n";
echo "║  Errors:            " . str_pad($errors, 48) . "║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

if ($errors > 0) {
    echo "Check {$config['log_file']} for error details.\n";
}

echo "Done! Visit {$config['shopware_url']}/admin to verify.\n\n";
