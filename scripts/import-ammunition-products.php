<?php
/**
 * Shopware Ammunition Products Importer
 * Imports ammunition products scraped from shop.ravenweapon.ch to ortak.ch
 *
 * Usage: php import-ammunition-products.php
 *
 * Prerequisites:
 *   1. Run scrape-ammunition-products.js first to generate ammunition-data/ammunition-products.json
 *   2. Ensure Shopware admin credentials are correct
 */

// Configuration
$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
    'json_input' => __DIR__ . '/ammunition-data/ammunition-products.json',
    'images_dir' => __DIR__ . '/ammunition-data/images',
    'manufacturer_name' => 'Raven Weapon',
    'tax_rate' => 8.1,  // Swiss VAT
    'sales_channel_name' => 'Storefront',
    'dry_run' => false,  // Set to true to test without making changes
    'log_file' => __DIR__ . '/import-ammunition.log',
    'upload_images' => true,  // Set to true to upload product images
    'category_name' => 'Munition',  // German category name
];

echo "\n";
echo "======================================================================\n";
echo "     SHOPWARE AMMUNITION IMPORT\n";
echo "     Source: shop.ravenweapon.ch\n";
echo "     Target: {$config['shopware_url']}\n";
echo "======================================================================\n\n";

// Check if input file exists
if (!file_exists($config['json_input'])) {
    die("ERROR: Products JSON not found: {$config['json_input']}\n\nPlease run the scraper first:\n  node scrape-ammunition-products.js\n\n");
}

// Load products
$data = json_decode(file_get_contents($config['json_input']), true);
$products = $data['products'] ?? [];

if (empty($products)) {
    die("ERROR: No products found in JSON file\n");
}

echo "Loaded " . count($products) . " products from scraper output\n\n";

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
        echo "Auth failed: HTTP $httpCode - $response\n";
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

function uploadMedia($imagePath, $productId, $productName, $config) {
    if (!file_exists($imagePath)) {
        return null;
    }

    $token = getAccessToken($config);
    if (!$token) return null;

    // Create media entry
    $mediaId = bin2hex(random_bytes(16));
    $ext = pathinfo($imagePath, PATHINFO_EXTENSION) ?: 'jpg';
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];
    $mimeType = $mimeTypes[strtolower($ext)] ?? 'image/jpeg';

    // Create media record
    $result = apiRequest('POST', '/media', [
        'id' => $mediaId,
    ], $config);

    if ($result['code'] !== 204 && $result['code'] !== 200) {
        return null;
    }

    // Upload the file
    $ch = curl_init();
    $url = $config['shopware_url'] . "/api/_action/media/$mediaId/upload?extension=$ext&fileName=" . urlencode(pathinfo($imagePath, PATHINFO_FILENAME));

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: ' . $mimeType,
        ],
        CURLOPT_POSTFIELDS => file_get_contents($imagePath),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 204 || $httpCode === 200) {
        return $mediaId;
    }

    return null;
}

function getOrCreateCategory($categoryName, $parentId, $config, &$categoryCache) {
    // Check cache first
    $cacheKey = $parentId . '|' . $categoryName;
    if (isset($categoryCache[$cacheKey])) {
        return $categoryCache[$cacheKey];
    }

    // Search for existing category
    $result = apiRequest('POST', '/search/category', [
        'filter' => [
            ['type' => 'equals', 'field' => 'name', 'value' => $categoryName],
            ['type' => 'equals', 'field' => 'parentId', 'value' => $parentId],
        ],
        'limit' => 1
    ], $config);

    if (!empty($result['body']['data'][0]['id'])) {
        $categoryId = $result['body']['data'][0]['id'];
        $categoryCache[$cacheKey] = $categoryId;
        return $categoryId;
    }

    // Create new category
    $categoryId = bin2hex(random_bytes(16));
    $result = apiRequest('POST', '/category', [
        'id' => $categoryId,
        'name' => $categoryName,
        'parentId' => $parentId,
        'active' => true,
        'displayNestedProducts' => true,
    ], $config);

    if ($result['code'] === 204 || $result['code'] === 200) {
        $categoryCache[$cacheKey] = $categoryId;
        return $categoryId;
    }

    return $parentId; // Fallback to parent if creation fails
}

// Step 1: Authenticate
echo "Step 1: Authenticating...\n";
$token = getAccessToken($config);
if (!$token) {
    die("  ERROR: Failed to authenticate!\n");
}
echo "  OK - Authenticated\n\n";

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

// Get CHF currency
$result = apiRequest('POST', '/search/currency', [
    'filter' => [['type' => 'equals', 'field' => 'isoCode', 'value' => 'CHF']]
], $config);
$chfCurrencyId = $result['body']['data'][0]['id'] ?? null;
echo "  CHF Currency ID: $chfCurrencyId\n";

if (!$chfCurrencyId) {
    die("ERROR: CHF currency not found! Check Shopware installation.\n");
}

// Get sales channel
$result = apiRequest('POST', '/search/sales-channel', [
    'filter' => [['type' => 'contains', 'field' => 'name', 'value' => $config['sales_channel_name']]]
], $config);
$salesChannelId = $result['body']['data'][0]['id'] ?? null;
echo "  Sales Channel ID: $salesChannelId\n";

// Get root category
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => null]],
    'limit' => 1
], $config);
$rootCategoryId = $result['body']['data'][0]['id'] ?? null;
echo "  Root Category ID: $rootCategoryId\n";

// Get/create Munition main category (German)
$categoryName = $config['category_name'] ?? 'Munition';
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => $categoryName]]
], $config);

if (!empty($result['body']['data'][0]['id'])) {
    $ammunitionCategoryId = $result['body']['data'][0]['id'];
    echo "  Found existing '$categoryName' category\n";
} else {
    $ammunitionCategoryId = bin2hex(random_bytes(16));
    apiRequest('POST', '/category', [
        'id' => $ammunitionCategoryId,
        'name' => $categoryName,
        'parentId' => $rootCategoryId,
        'active' => true,
        'displayNestedProducts' => true,
    ], $config);
    echo "  Created new '$categoryName' category\n";
}
echo "  $categoryName Category ID: $ammunitionCategoryId\n\n";

// Cache for subcategories
$categoryCache = [];

// Step 3: Import products
echo "Step 3: Importing products...\n\n";

$created = 0;
$updated = 0;
$errors = 0;
$imagesUploaded = 0;
$count = 0;
$total = count($products);

foreach ($products as $product) {
    $count++;

    // Skip products with errors from scraping
    if (!empty($product['error'])) {
        echo "  [$count/$total] SKIP (scrape error): {$product['url']}\n";
        continue;
    }

    // Generate product number
    $productNumber = !empty($product['sku']) ? $product['sku'] : 'AMMO-' . substr(md5($product['url']), 0, 8);

    $displayName = mb_substr($product['name'] ?? 'Unknown', 0, 45);
    echo "  [$count/$total] $displayName... ";

    if ($config['dry_run']) {
        echo "SKIP (dry run)\n";
        continue;
    }

    try {
        // Get price
        $price = $product['numericPrice'] ?? 0;
        if ($price <= 0) {
            // Try to parse from price string
            $priceMatch = preg_match('/([\d.,]+)/', $product['price'] ?? '', $matches);
            $price = $priceMatch ? floatval(str_replace(',', '.', $matches[1])) : 10.00;
        }

        // Ensure minimum price
        if ($price <= 0) $price = 10.00;

        // Calculate net price
        $netPrice = round($price / (1 + $config['tax_rate'] / 100), 2);

        // Check if product exists
        $result = apiRequest('POST', '/search/product', [
            'filter' => [['type' => 'equals', 'field' => 'productNumber', 'value' => $productNumber]]
        ], $config);
        $existingId = $result['body']['data'][0]['id'] ?? null;

        // Get/create subcategory if different from main
        $categoryId = $ammunitionCategoryId;
        $productCategory = $product['category'] ?? 'Munition';
        // Map English category names to German if needed
        $categoryMapping = [
            'Ammunition' => 'Munition',
            '.223 ammunition' => '.223 Munition',
            '300AAC Blackout ammunition' => '300AAC Blackout Munition',
            '9mm ammunition' => '9mm Munition',
            '.22 LR ammunition' => '.22 LR Munition',
        ];
        $productCategory = $categoryMapping[$productCategory] ?? $productCategory;

        if ($productCategory !== $config['category_name'] && !empty($productCategory)) {
            $categoryId = getOrCreateCategory($productCategory, $ammunitionCategoryId, $config, $categoryCache);
        }

        // Clean description
        $description = $product['description'] ?? $product['descriptionText'] ?? '';
        // Remove excessive HTML but keep basic formatting
        $description = strip_tags($description, '<p><br><ul><li><strong><b><em><i>');

        // Prepare product data
        $productData = [
            'name' => $product['name'] ?? 'Ammunition Product',
            'productNumber' => $productNumber,
            'stock' => 100,
            'active' => true,
            'manufacturerId' => $manufacturerId,
            'taxId' => $taxId,
            'price' => [
                [
                    'currencyId' => $chfCurrencyId,
                    'gross' => $price,
                    'net' => $netPrice,
                    'linked' => false,
                ],
            ],
            'description' => $description,
            'metaDescription' => mb_substr(strip_tags($description), 0, 160),
        ];

        // Add EAN if available
        if (!empty($product['ean'])) {
            $productData['ean'] = $product['ean'];
        }

        // Add manufacturer info if available
        if (!empty($product['manufacturer'])) {
            // Could create/get manufacturer here if needed
        }

        if ($existingId) {
            // Update existing product
            $result = apiRequest('PATCH', "/product/$existingId", $productData, $config);
            if ($result['code'] === 204 || $result['code'] === 200) {
                echo "UPDATED (CHF $price)\n";
                $updated++;
            } else {
                echo "ERROR updating\n";
                file_put_contents($config['log_file'],
                    date('Y-m-d H:i:s') . " FAILED UPDATE: {$product['name']} - " . json_encode($result['body']) . "\n",
                    FILE_APPEND);
                $errors++;
            }
        } else {
            // Create new product
            $productId = bin2hex(random_bytes(16));
            $productData['id'] = $productId;
            $productData['categories'] = [['id' => $categoryId]];

            if ($salesChannelId) {
                $productData['visibilities'] = [[
                    'salesChannelId' => $salesChannelId,
                    'visibility' => 30,
                ]];
            }

            $result = apiRequest('POST', '/product', $productData, $config);
            if ($result['code'] === 204 || $result['code'] === 200) {
                echo "CREATED (CHF $price)";
                $created++;

                // Upload images if enabled
                if ($config['upload_images'] && !empty($product['localImages'])) {
                    $mediaIds = [];
                    foreach ($product['localImages'] as $localImage) {
                        $imagePath = $config['images_dir'] . '/' . $localImage;
                        $mediaId = uploadMedia($imagePath, $productId, $product['name'], $config);
                        if ($mediaId) {
                            $mediaIds[] = $mediaId;
                            $imagesUploaded++;
                        }
                    }

                    // Assign images to product
                    if (!empty($mediaIds)) {
                        $productMedia = [];
                        foreach ($mediaIds as $position => $mediaId) {
                            $productMedia[] = [
                                'mediaId' => $mediaId,
                                'position' => $position,
                            ];
                        }

                        // Set cover image
                        apiRequest('PATCH', "/product/$productId", [
                            'coverId' => $mediaIds[0],
                            'media' => $productMedia,
                        ], $config);

                        echo " + " . count($mediaIds) . " images";
                    }
                }

                echo "\n";
            } else {
                echo "ERROR creating\n";
                file_put_contents($config['log_file'],
                    date('Y-m-d H:i:s') . " FAILED CREATE: {$product['name']} - " . json_encode($result['body']) . "\n",
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
echo "======================================================================\n";
echo "                    IMPORT COMPLETE\n";
echo "======================================================================\n";
echo "  Products created: $created\n";
echo "  Products updated: $updated\n";
echo "  Images uploaded:  $imagesUploaded\n";
echo "  Errors:           $errors\n";
echo "======================================================================\n\n";

if ($errors > 0) {
    echo "Check {$config['log_file']} for error details.\n\n";
}

echo "Done! Visit {$config['shopware_url']}/admin to verify products.\n";
echo "Storefront: {$config['shopware_url']}\n\n";
