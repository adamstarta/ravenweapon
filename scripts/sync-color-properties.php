<?php
/**
 * Sync Color Properties from Custom Fields to Shopware Properties
 *
 * This script:
 * 1. Creates "Farbe" property group (if not exists)
 * 2. Creates color options (Black, Grey, Multicam, etc.)
 * 3. Reads snigel_color_options from product custom fields
 * 4. Assigns color properties to products
 *
 * Run: php sync-color-properties.php
 */

$API_URL = 'http://localhost';

// Actual colors whitelist (no sizes)
$COLOR_WHITELIST = [
    'Black',
    'Clear',
    'Coyote',
    'Grey',
    'HighVis yellow',
    'Khaki',
    'Multicam',
    'Navy',
    'Olive',
    'Swecam',
    'Various',
    'White'
];

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
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
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

function generateUuid() {
    return sprintf('%04x%04x%04x%04x%04x%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

echo "=== Sync Color Properties ===\n\n";

// Get token
$token = getToken($API_URL);
if (!$token) {
    die("ERROR: Could not get API token\n");
}
echo "[OK] Got API token\n";

// Step 1: Check if "Farbe" property group exists
echo "\n[1] Checking for Farbe property group...\n";

$result = apiRequest($API_URL, $token, 'POST', 'search/property-group', [
    'filter' => [
        ['type' => 'equals', 'field' => 'name', 'value' => 'Farbe']
    ],
    'associations' => ['options' => []]
]);

$propertyGroupId = null;
$existingOptions = [];

if (!empty($result['data']['data'])) {
    $propertyGroupId = $result['data']['data'][0]['id'];
    echo "    Found existing Farbe group: $propertyGroupId\n";

    // Get existing options
    foreach ($result['data']['data'][0]['options'] ?? [] as $opt) {
        $existingOptions[$opt['name']] = $opt['id'];
    }
    echo "    Existing options: " . count($existingOptions) . "\n";
} else {
    // Create property group
    echo "    Creating Farbe property group...\n";
    $propertyGroupId = generateUuid();

    $createResult = apiRequest($API_URL, $token, 'POST', 'property-group', [
        'id' => $propertyGroupId,
        'name' => 'Farbe',
        'displayType' => 'text',
        'sortingType' => 'alphanumeric',
        'filterable' => true,
        'visibleOnProductDetailPage' => true
    ]);

    if ($createResult['code'] >= 200 && $createResult['code'] < 300) {
        echo "    [OK] Created Farbe group: $propertyGroupId\n";
    } else {
        echo "    [ERROR] Failed to create group: " . json_encode($createResult['data']) . "\n";
        die();
    }
}

// Step 2: Create color options
echo "\n[2] Creating color options...\n";

$colorOptionIds = [];

foreach ($COLOR_WHITELIST as $colorName) {
    if (isset($existingOptions[$colorName])) {
        $colorOptionIds[$colorName] = $existingOptions[$colorName];
        echo "    [EXISTS] $colorName\n";
    } else {
        $optionId = generateUuid();

        $createResult = apiRequest($API_URL, $token, 'POST', 'property-group-option', [
            'id' => $optionId,
            'groupId' => $propertyGroupId,
            'name' => $colorName
        ]);

        if ($createResult['code'] >= 200 && $createResult['code'] < 300) {
            $colorOptionIds[$colorName] = $optionId;
            echo "    [CREATED] $colorName\n";
        } else {
            echo "    [ERROR] $colorName: " . json_encode($createResult['data']) . "\n";
        }
    }
}

echo "\n    Total color options: " . count($colorOptionIds) . "\n";

// Step 3: Get all products with snigel_color_options
echo "\n[3] Finding products with color options...\n";

$page = 1;
$limit = 50;
$totalUpdated = 0;
$totalSkipped = 0;

do {
    $result = apiRequest($API_URL, $token, 'POST', 'search/product', [
        'page' => $page,
        'limit' => $limit,
        'filter' => [
            ['type' => 'equals', 'field' => 'parentId', 'value' => null] // Only main products
        ],
        'includes' => [
            'product' => ['id', 'productNumber', 'name', 'customFields', 'propertyIds']
        ]
    ]);

    $products = $result['data']['data'] ?? [];
    $total = $result['data']['total'] ?? 0;

    echo "    Processing page $page (" . count($products) . " products, total: $total)...\n";

    foreach ($products as $product) {
        $productId = $product['id'];
        $productNumber = $product['productNumber'] ?? '';
        $customFields = $product['customFields'] ?? [];
        $currentPropertyIds = $product['propertyIds'] ?? [];

        // Get colors from custom fields
        $snigelColors = $customFields['snigel_color_options'] ?? [];
        $ravenColors = $customFields['raven_variant_options'] ?? [];

        // Parse color names
        $productColors = [];

        // From snigel_color_options (array of objects with 'name')
        if (is_array($snigelColors)) {
            foreach ($snigelColors as $colorData) {
                if (is_array($colorData) && isset($colorData['name'])) {
                    $productColors[] = $colorData['name'];
                } elseif (is_string($colorData)) {
                    $productColors[] = $colorData;
                }
            }
        }

        // From raven_variant_options
        if (is_array($ravenColors)) {
            foreach ($ravenColors as $variantData) {
                if (is_array($variantData) && isset($variantData['name'])) {
                    $productColors[] = $variantData['name'];
                }
            }
        }

        // Filter to only whitelisted colors
        $validColors = array_filter($productColors, function($c) use ($COLOR_WHITELIST) {
            return in_array($c, $COLOR_WHITELIST);
        });

        if (empty($validColors)) {
            $totalSkipped++;
            continue;
        }

        // Get property option IDs for these colors
        $newPropertyIds = [];
        foreach ($validColors as $colorName) {
            if (isset($colorOptionIds[$colorName])) {
                $newPropertyIds[] = $colorOptionIds[$colorName];
            }
        }

        // Merge with existing property IDs (keep non-color properties)
        $mergedPropertyIds = array_unique(array_merge($currentPropertyIds, $newPropertyIds));

        // Update product
        $updateResult = apiRequest($API_URL, $token, 'PATCH', 'product/' . $productId, [
            'propertyIds' => array_values($mergedPropertyIds)
        ]);

        if ($updateResult['code'] >= 200 && $updateResult['code'] < 300) {
            $totalUpdated++;
            echo "      [OK] $productNumber: " . implode(', ', $validColors) . "\n";
        } else {
            echo "      [ERROR] $productNumber: " . json_encode($updateResult['data']) . "\n";
        }
    }

    $page++;

} while (count($products) >= $limit);

echo "\n=== SUMMARY ===\n";
echo "Products updated: $totalUpdated\n";
echo "Products skipped (no colors): $totalSkipped\n";
echo "Color options available: " . count($colorOptionIds) . "\n";
echo "\nDone! Clear cache: bin/console cache:clear\n";
