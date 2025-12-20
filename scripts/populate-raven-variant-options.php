<?php
/**
 * Populate raven_variant_options custom field for Raven weapons and caliber kits
 *
 * This script reads product media and creates proper variant option entries
 * Run: php scripts/populate-raven-variant-options.php
 */

// Configuration
$config = [
    'shopware_url' => 'https://ortak.ch',
    'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
    'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg',
    'dry_run' => false, // Set to true to test without making changes
];

// Variant name mapping based on common Raven weapon variant names
$variantMapping = [
    'graphite' => 'Graphite Black',
    'black' => 'Graphite Black',
    'fde' => 'Flat Dark Earth',
    'flat-dark-earth' => 'Flat Dark Earth',
    'earth' => 'Flat Dark Earth',
    'northern' => 'Northern Lights',
    'northenlights' => 'Northern Lights',
    'lights' => 'Northern Lights',
    'olive' => 'Olive Drab Green',
    'odg' => 'Olive Drab Green',
    'green' => 'Olive Drab Green',
    'sniper' => 'Sniper Grey',
    'grey' => 'Sniper Grey',
    'gray' => 'Sniper Grey',
    'silver' => 'Sniper Grey',
];

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║     RAVEN PRODUCT VARIANT OPTIONS POPULATION SCRIPT        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Global token management
$GLOBALS['token_data'] = [
    'token' => null,
    'expires_at' => 0,
];

/**
 * Get Shopware API access token (with auto-refresh)
 */
function getAccessToken($config, $forceRefresh = false) {
    if (!$forceRefresh &&
        $GLOBALS['token_data']['token'] &&
        $GLOBALS['token_data']['expires_at'] > time() + 60) {
        return $GLOBALS['token_data']['token'];
    }

    $ch = curl_init();
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

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo "Error: Failed to get access token. HTTP $httpCode\n";
        return null;
    }

    $data = json_decode($response, true);
    $GLOBALS['token_data']['token'] = $data['access_token'] ?? null;
    $GLOBALS['token_data']['expires_at'] = time() + ($data['expires_in'] ?? 600);

    return $GLOBALS['token_data']['token'];
}

/**
 * Make API request
 */
function apiRequest($config, $method, $endpoint, $data = null) {
    $token = getAccessToken($config);
    if (!$token) return null;

    $ch = curl_init();
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ];

    $opts = [
        CURLOPT_URL => $config['shopware_url'] . $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($data) $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    } elseif ($method === 'PATCH') {
        $opts[CURLOPT_CUSTOMREQUEST] = 'PATCH';
        if ($data) $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        echo "API Error: HTTP $httpCode - $response\n";
        return null;
    }

    return json_decode($response, true);
}

/**
 * Detect variant name from filename
 */
function detectVariantFromFilename($filename, $variantMapping) {
    $filenameLower = strtolower($filename);

    foreach ($variantMapping as $key => $variantName) {
        if (strpos($filenameLower, $key) !== false) {
            return $variantName;
        }
    }

    return null;
}

// Step 1: Get all products with their media
echo "Step 1: Fetching products with media...\n";

$products = apiRequest($config, 'POST', '/api/search/product', [
    'filter' => [
        // Exclude Snigel products (they already have color options)
        [
            'type' => 'not',
            'queries' => [
                ['type' => 'contains', 'field' => 'manufacturer.name', 'value' => 'Snigel']
            ]
        ],
        // Only parent products (not variants)
        ['type' => 'equals', 'field' => 'parentId', 'value' => null]
    ],
    'associations' => [
        'media' => [
            'sort' => [['field' => 'position', 'order' => 'ASC']],
            'associations' => [
                'media' => []
            ]
        ],
        'manufacturer' => []
    ],
    'includes' => [
        'product' => ['id', 'name', 'productNumber', 'media', 'manufacturer', 'customFields'],
        'product_media' => ['media', 'position'],
        'media' => ['id', 'url', 'fileName'],
        'product_manufacturer' => ['name']
    ],
    'limit' => 500
]);

if (!$products || empty($products['data'])) {
    echo "No products found or API error.\n";
    exit(1);
}

echo "  Found " . count($products['data']) . " products.\n\n";

$updated = 0;
$skipped = 0;

// Step 2: Process each product
echo "Step 2: Processing products...\n\n";

foreach ($products['data'] as $product) {
    $productName = $product['name'] ?? 'Unknown';
    $productId = $product['id'];
    $productNumber = $product['productNumber'] ?? '';
    $media = $product['media'] ?? [];

    // Skip products with less than 2 media items (no variants)
    if (count($media) < 2) {
        echo "SKIP: $productName ($productNumber) - only " . count($media) . " media item(s)\n";
        $skipped++;
        continue;
    }

    // Check if already has raven_variant_options set
    $existingVariants = $product['customFields']['raven_variant_options'] ?? null;
    if ($existingVariants) {
        echo "SKIP: $productName ($productNumber) - already has variant options\n";
        $skipped++;
        continue;
    }

    // Build variant options from media
    $variantOptions = [];
    $usedVariants = [];
    $defaultVariants = ['Graphite Black', 'Flat Dark Earth', 'Northern Lights', 'Olive Drab Green', 'Sniper Grey'];

    foreach ($media as $index => $mediaItem) {
        $mediaUrl = $mediaItem['media']['url'] ?? '';
        $mediaFilename = $mediaItem['media']['fileName'] ?? '';

        if (!$mediaUrl) continue;

        // Try to detect variant from filename
        $detectedVariant = detectVariantFromFilename($mediaFilename, $variantMapping);

        // If no variant detected, use position-based default
        if (!$detectedVariant) {
            $detectedVariant = $defaultVariants[$index] ?? 'Variante ' . ($index + 1);
        }

        // Avoid duplicates
        if (in_array($detectedVariant, $usedVariants)) {
            $detectedVariant = $detectedVariant . ' ' . ($index + 1);
        }
        $usedVariants[] = $detectedVariant;

        $variantOptions[] = [
            'name' => $detectedVariant,
            'value' => strtolower(str_replace(' ', '-', $detectedVariant)),
            'imageUrl' => $mediaUrl
        ];
    }

    if (count($variantOptions) > 1) {
        if ($config['dry_run']) {
            echo "DRY RUN: Would update $productName with " . count($variantOptions) . " Varianten\n";
            foreach ($variantOptions as $opt) {
                echo "  - {$opt['name']}\n";
            }
            $updated++;
        } else {
            // Update product custom fields
            $updateData = [
                'customFields' => [
                    'raven_variant_options' => $variantOptions,
                    'raven_has_variants' => true
                ]
            ];

            $result = apiRequest($config, 'PATCH', '/api/product/' . $productId, $updateData);

            if ($result !== null) {
                echo "UPDATED: $productName ($productNumber) - " . count($variantOptions) . " Varianten\n";
                foreach ($variantOptions as $opt) {
                    echo "  - {$opt['name']}\n";
                }
                $updated++;
            } else {
                echo "ERROR: Failed to update $productName\n";
            }
        }
    } else {
        echo "SKIP: $productName ($productNumber) - insufficient distinct variants\n";
        $skipped++;
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                         SUMMARY                            ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";
echo "  Updated: $updated products\n";
echo "  Skipped: $skipped products\n";

if ($config['dry_run']) {
    echo "\n  (DRY RUN MODE - no changes were made)\n";
}

echo "\nDone!\n";
