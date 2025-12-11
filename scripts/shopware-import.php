<?php
/**
 * Shopware Product Importer for Snigel Products
 *
 * This script imports products from the scraped Snigel JSON file into Shopware 6.
 * It uses the Shopware Admin API to create/update products.
 *
 * Usage: php shopware-import.php
 *
 * Prerequisites:
 * 1. Run snigel-scraper.php first to generate the products.json file
 * 2. Configure the Shopware API credentials below
 */

// Configuration
$config = [
    // Shopware API settings
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'admin',           // Shopware admin username
    'api_password' => '',            // Shopware admin password (fill this in)

    // Or use Integration credentials (recommended)
    'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
    'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg',

    // Input files - use merged data with B2B prices
    'json_input' => __DIR__ . '/snigel-merged-products.json',
    'images_dir' => __DIR__ . '/snigel-data/images',

    // Shopware settings
    'manufacturer_name' => 'Snigel',
    'tax_rate' => 8.1,               // Swiss VAT rate
    'sales_channel_name' => 'Storefront', // Sales channel to assign products to

    // Price markup (percentage to add to purchase price)
    'price_markup' => 30,            // 30% markup

    // Currency conversion (if scraping in SEK, convert to CHF)
    'currency_conversion' => [
        'SEK' => 0.085,   // 1 SEK = 0.085 CHF (approximate)
        'EUR' => 0.95,    // 1 EUR = 0.95 CHF (approximate)
        'USD' => 0.88,    // 1 USD = 0.88 CHF (approximate)
    ],

    // Dry run mode (set to true to test without making changes)
    'dry_run' => false,

    // Log file
    'log_file' => __DIR__ . '/snigel-data/import.log',
];

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║       SHOPWARE PRODUCT IMPORTER FOR SNIGEL PRODUCTS        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Check if JSON file exists
if (!file_exists($config['json_input'])) {
    echo "Error: Products JSON file not found: {$config['json_input']}\n";
    echo "Please run 'php snigel-scraper.php' first.\n";
    exit(1);
}

// Load products
echo "Step 1: Loading scraped products...\n";
$productsJson = file_get_contents($config['json_input']);
$products = json_decode($productsJson, true);

if (empty($products)) {
    echo "Error: No products found in JSON file.\n";
    exit(1);
}

echo "  Loaded " . count($products) . " products.\n\n";

/**
 * Log message to file and console
 */
function logMessage($message, $config) {
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    file_put_contents($config['log_file'], $logLine, FILE_APPEND);
    echo $message . "\n";
}

// Global token management
$GLOBALS['token_data'] = [
    'token' => null,
    'expires_at' => 0,
];

/**
 * Get Shopware API access token (with auto-refresh)
 */
function getAccessToken($config, $forceRefresh = false) {
    // Check if current token is still valid (with 60 second buffer)
    if (!$forceRefresh &&
        $GLOBALS['token_data']['token'] &&
        $GLOBALS['token_data']['expires_at'] > time() + 60) {
        return $GLOBALS['token_data']['token'];
    }

    $ch = curl_init();

    // Try Integration credentials first
    if (!empty($config['client_id']) && !empty($config['client_secret'])) {
        curl_setopt_array($ch, [
            CURLOPT_URL => $config['shopware_url'] . '/api/oauth/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'grant_type' => 'client_credentials',
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
            ]),
        ]);
    } else {
        // Fall back to admin credentials
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
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return null;
    }

    $data = json_decode($response, true);
    $token = $data['access_token'] ?? null;
    $expiresIn = $data['expires_in'] ?? 600; // Default 10 minutes

    if ($token) {
        $GLOBALS['token_data']['token'] = $token;
        $GLOBALS['token_data']['expires_at'] = time() + $expiresIn;
    }

    return $token;
}

/**
 * Ensure token is valid, refresh if needed
 */
function ensureValidToken($config) {
    return getAccessToken($config);
}

/**
 * Make API request to Shopware (with auto token refresh)
 */
function apiRequest($method, $endpoint, $data, $token, $config, $retry = true) {
    // Always get fresh token
    $token = ensureValidToken($config);

    $ch = curl_init();

    $url = $config['shopware_url'] . '/api/' . ltrim($endpoint, '/');

    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    if ($data && in_array($method, ['POST', 'PATCH', 'PUT'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // If 401 (token expired), refresh and retry once
    if ($httpCode === 401 && $retry) {
        $newToken = getAccessToken($config, true); // Force refresh
        if ($newToken) {
            return apiRequest($method, $endpoint, $data, $newToken, $config, false);
        }
    }

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
    ];
}

/**
 * Get or create manufacturer
 */
function getOrCreateManufacturer($name, $token, $config) {
    // Search for existing manufacturer
    $result = apiRequest('POST', '/search/product-manufacturer', [
        'filter' => [
            ['type' => 'equals', 'field' => 'name', 'value' => $name]
        ]
    ], $token, $config);

    if (!empty($result['body']['data'][0]['id'])) {
        return $result['body']['data'][0]['id'];
    }

    // Create new manufacturer
    $id = bin2hex(random_bytes(16));
    $result = apiRequest('POST', '/product-manufacturer', [
        'id' => $id,
        'name' => $name,
    ], $token, $config);

    if ($result['code'] === 204 || $result['code'] === 200) {
        return $id;
    }

    return null;
}

/**
 * Get tax ID for given rate
 */
function getTaxId($rate, $token, $config) {
    $result = apiRequest('POST', '/search/tax', [
        'filter' => [
            ['type' => 'equals', 'field' => 'taxRate', 'value' => $rate]
        ]
    ], $token, $config);

    if (!empty($result['body']['data'][0]['id'])) {
        return $result['body']['data'][0]['id'];
    }

    // Create tax if not found
    $id = bin2hex(random_bytes(16));
    apiRequest('POST', '/tax', [
        'id' => $id,
        'taxRate' => $rate,
        'name' => $rate . '% MwSt',
    ], $token, $config);

    return $id;
}

/**
 * Get default currency ID (EUR - system default)
 */
function getDefaultCurrencyId($token, $config) {
    $result = apiRequest('POST', '/search/currency', [
        'filter' => [
            ['type' => 'equals', 'field' => 'isSystemDefault', 'value' => true]
        ]
    ], $token, $config);

    if (!empty($result['body']['data'][0]['id'])) {
        return $result['body']['data'][0]['id'];
    }

    // Fallback to EUR
    $result = apiRequest('POST', '/search/currency', [
        'filter' => [
            ['type' => 'equals', 'field' => 'isoCode', 'value' => 'EUR']
        ]
    ], $token, $config);

    return $result['body']['data'][0]['id'] ?? null;
}

/**
 * Get CHF currency ID
 */
function getCHFCurrencyId($token, $config) {
    $result = apiRequest('POST', '/search/currency', [
        'filter' => [
            ['type' => 'equals', 'field' => 'isoCode', 'value' => 'CHF']
        ]
    ], $token, $config);

    return $result['body']['data'][0]['id'] ?? null;
}

/**
 * Get sales channel ID
 */
function getSalesChannelId($name, $token, $config) {
    $result = apiRequest('POST', '/search/sales-channel', [
        'filter' => [
            ['type' => 'contains', 'field' => 'name', 'value' => $name]
        ]
    ], $token, $config);

    if (!empty($result['body']['data'][0]['id'])) {
        return $result['body']['data'][0]['id'];
    }

    return null;
}

/**
 * Get or create category under Snigel
 */
function getOrCreateCategory($categoryName, $parentId, $token, $config) {
    // Search for existing category
    $result = apiRequest('POST', '/search/category', [
        'filter' => [
            ['type' => 'equals', 'field' => 'name', 'value' => $categoryName],
            ['type' => 'equals', 'field' => 'parentId', 'value' => $parentId],
        ]
    ], $token, $config);

    if (!empty($result['body']['data'][0]['id'])) {
        return $result['body']['data'][0]['id'];
    }

    // Create new category
    $id = bin2hex(random_bytes(16));
    $result = apiRequest('POST', '/category', [
        'id' => $id,
        'name' => $categoryName,
        'parentId' => $parentId,
        'active' => true,
        'displayNestedProducts' => true,
    ], $token, $config);

    if ($result['code'] === 204 || $result['code'] === 200) {
        return $id;
    }

    return null;
}

/**
 * Upload media and get media ID
 */
function uploadMedia($imagePath, $productName, $token, $config) {
    $mediaId = bin2hex(random_bytes(16));

    // Create media entity
    $result = apiRequest('POST', '/media', [
        'id' => $mediaId,
    ], $token, $config);

    if ($result['code'] !== 204 && $result['code'] !== 200) {
        return null;
    }

    // Upload the file
    if (file_exists($imagePath)) {
        $extension = pathinfo($imagePath, PATHINFO_EXTENSION);
        $filename = pathinfo($imagePath, PATHINFO_FILENAME);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $config['shopware_url'] . "/api/_action/media/$mediaId/upload?extension=$extension&fileName=$filename",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: image/' . $extension,
            ],
            CURLOPT_POSTFIELDS => file_get_contents($imagePath),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 204 || $httpCode === 200) {
            return $mediaId;
        }
    }

    return null;
}

/**
 * Convert price to CHF
 */
function convertToCHF($price, $currency, $config) {
    if ($currency === 'CHF') {
        return $price;
    }

    $rate = $config['currency_conversion'][$currency] ?? 1;
    return $price * $rate;
}

/**
 * Calculate selling price with markup
 */
function calculateSellingPrice($purchasePrice, $config) {
    return $purchasePrice * (1 + $config['price_markup'] / 100);
}

/**
 * Check if product exists by product number
 */
function findExistingProduct($productNumber, $token, $config) {
    $result = apiRequest('POST', '/search/product', [
        'filter' => [
            ['type' => 'equals', 'field' => 'productNumber', 'value' => $productNumber]
        ]
    ], $token, $config);

    if (!empty($result['body']['data'][0]['id'])) {
        return $result['body']['data'][0]['id'];
    }

    return null;
}

// ============================================
// MAIN IMPORT LOGIC
// ============================================

if ($config['dry_run']) {
    echo "*** DRY RUN MODE - No changes will be made ***\n\n";
}

// Step 2: Get API token
echo "Step 2: Authenticating with Shopware API...\n";

if (empty($config['api_password']) && empty($config['client_secret'])) {
    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  CONFIGURATION REQUIRED                                    ║\n";
    echo "╠════════════════════════════════════════════════════════════╣\n";
    echo "║  Please configure API credentials in this script:         ║\n";
    echo "║                                                            ║\n";
    echo "║  Option 1: Admin credentials                               ║\n";
    echo "║    - api_user: 'admin'                                     ║\n";
    echo "║    - api_password: 'your-password'                         ║\n";
    echo "║                                                            ║\n";
    echo "║  Option 2: Integration credentials (recommended)           ║\n";
    echo "║    - client_id: 'SWIA...'                                  ║\n";
    echo "║    - client_secret: 'your-secret'                          ║\n";
    echo "║                                                            ║\n";
    echo "║  Create Integration in: Admin > Settings > System >        ║\n";
    echo "║  Integrations > Add Integration                            ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    exit(1);
}

$token = getAccessToken($config);

if (!$token) {
    echo "  Error: Failed to authenticate. Check credentials.\n";
    exit(1);
}

echo "  ✓ Authenticated successfully!\n\n";

// Step 3: Get required IDs
echo "Step 3: Fetching Shopware configuration...\n";

$manufacturerId = getOrCreateManufacturer($config['manufacturer_name'], $token, $config);
echo "  Manufacturer ID: $manufacturerId\n";

$taxId = getTaxId($config['tax_rate'], $token, $config);
echo "  Tax ID: $taxId\n";

$defaultCurrencyId = getDefaultCurrencyId($token, $config);
echo "  Default Currency ID (EUR): $defaultCurrencyId\n";

$chfCurrencyId = getCHFCurrencyId($token, $config);
echo "  CHF Currency ID: $chfCurrencyId\n";

$salesChannelId = getSalesChannelId($config['sales_channel_name'], $token, $config);
echo "  Sales Channel ID: $salesChannelId\n";

// Get Snigel parent category
$snigelCategoryResult = apiRequest('POST', '/search/category', [
    'filter' => [
        ['type' => 'equals', 'field' => 'name', 'value' => 'Snigel']
    ]
], $token, $config);
$snigelCategoryId = $snigelCategoryResult['body']['data'][0]['id'] ?? null;
echo "  Snigel Category ID: $snigelCategoryId\n\n";

// Step 4: Import products
echo "Step 4: Importing products...\n";

$created = 0;
$updated = 0;
$errors = 0;
$count = 0;
$total = count($products);

foreach ($products as $product) {
    $count++;
    $productNumber = !empty($product['article_no']) ? $product['article_no'] : 'SN-' . $product['slug'];

    echo "  [$count/$total] {$product['name']}... ";

    if ($config['dry_run']) {
        echo "SKIP (dry run)\n";
        continue;
    }

    try {
        // Use B2B EUR price if available, otherwise use RRP or default
        $purchasePriceEUR = 0;
        $sellingPriceEUR = 0;

        if (!empty($product['b2b_price_eur'])) {
            // We have B2B price - use it as purchase price
            $purchasePriceEUR = round($product['b2b_price_eur'], 2);
            // Use RRP as selling price, or apply markup
            $sellingPriceEUR = !empty($product['rrp_eur'])
                ? round($product['rrp_eur'], 2)
                : round($purchasePriceEUR * (1 + $config['price_markup'] / 100), 2);
        } else {
            // No B2B price - set a placeholder price (to be updated later)
            $purchasePriceEUR = 10.00;
            $sellingPriceEUR = 15.00;
        }

        // Convert EUR to CHF (approximate rate)
        $eurToChfRate = 0.95; // 1 EUR ≈ 0.95 CHF
        $purchasePriceCHF = round($purchasePriceEUR * $eurToChfRate, 2);
        $sellingPriceCHF = round($sellingPriceEUR * $eurToChfRate, 2);

        // Check if product exists
        $existingId = findExistingProduct($productNumber, $token, $config);

        // Prepare product data with EUR (default) and CHF prices
        $productData = [
            'name' => $product['name'],
            'productNumber' => $productNumber,
            'stock' => 100,
            'active' => true,
            'manufacturerId' => $manufacturerId,
            'taxId' => $taxId,
            'price' => [
                // EUR price (default currency - REQUIRED)
                [
                    'currencyId' => $defaultCurrencyId,
                    'gross' => $sellingPriceEUR,
                    'net' => round($sellingPriceEUR / (1 + $config['tax_rate'] / 100), 2),
                    'linked' => true,
                ],
                // CHF price
                [
                    'currencyId' => $chfCurrencyId,
                    'gross' => $sellingPriceCHF,
                    'net' => round($sellingPriceCHF / (1 + $config['tax_rate'] / 100), 2),
                    'linked' => true,
                ],
            ],
            'purchasePrices' => [
                [
                    'currencyId' => $defaultCurrencyId,
                    'gross' => $purchasePriceEUR,
                    'net' => round($purchasePriceEUR / (1 + $config['tax_rate'] / 100), 2),
                    'linked' => true,
                ],
                [
                    'currencyId' => $chfCurrencyId,
                    'gross' => $purchasePriceCHF,
                    'net' => round($purchasePriceCHF / (1 + $config['tax_rate'] / 100), 2),
                    'linked' => true,
                ],
            ],
            'description' => $product['description'] ?? $product['short_description'] ?? '',
            'metaDescription' => substr($product['short_description'] ?? '', 0, 160),
            'ean' => $product['ean'] ?? null,
            'weight' => !empty($product['weight_g']) ? $product['weight_g'] / 1000 : null,
        ];

        // Add categories
        if ($snigelCategoryId) {
            $categoryIds = [['id' => $snigelCategoryId]];

            // Create subcategories based on product categories (if available)
            $categories = $product['categories'] ?? [];
            foreach ($categories as $catName) {
                $subCatId = getOrCreateCategory($catName, $snigelCategoryId, $token, $config);
                if ($subCatId) {
                    $categoryIds[] = ['id' => $subCatId];
                }
            }

            $productData['categories'] = $categoryIds;
        }

        if ($existingId) {
            // Update existing product - don't include visibilities (causes conflicts)
            unset($productData['categories']); // Also skip categories on update
            $result = apiRequest('PATCH', "/product/$existingId", $productData, $token, $config);
            if ($result['code'] === 204 || $result['code'] === 200) {
                echo "UPDATED\n";
                $updated++;
            } else {
                echo "ERROR (update failed)\n";
                logMessage("Failed to update: {$product['name']} - " . json_encode($result['body']), $config);
                $errors++;
            }
        } else {
            // Add visibility for sales channel (only for new products)
            if ($salesChannelId) {
                $productData['visibilities'] = [[
                    'salesChannelId' => $salesChannelId,
                    'visibility' => 30, // All (search + listing)
                ]];
            }
            // Create new product
            $productData['id'] = bin2hex(random_bytes(16));
            $result = apiRequest('POST', '/product', $productData, $token, $config);
            if ($result['code'] === 204 || $result['code'] === 200) {
                echo "CREATED\n";
                $created++;

                // Upload images for new products
                if (!empty($product['local_images'])) {
                    $mediaIds = [];
                    foreach (array_slice($product['local_images'], 0, 5) as $i => $imageFile) {
                        $imagePath = $config['images_dir'] . '/' . $imageFile;
                        $mediaId = uploadMedia($imagePath, $product['name'], $token, $config);
                        if ($mediaId) {
                            $mediaIds[] = [
                                'mediaId' => $mediaId,
                                'position' => $i,
                            ];
                        }
                    }

                    if (!empty($mediaIds)) {
                        // Set cover image
                        apiRequest('PATCH', '/product/' . $productData['id'], [
                            'coverId' => $mediaIds[0]['mediaId'],
                            'media' => $mediaIds,
                        ], $token, $config);
                    }
                }
            } else {
                echo "ERROR (create failed)\n";
                logMessage("Failed to create: {$product['name']} - " . json_encode($result['body']), $config);
                $errors++;
            }
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n";

// Summary
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

echo "Done! Visit your Shopware admin to verify the imported products.\n";
