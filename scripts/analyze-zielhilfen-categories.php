<?php
/**
 * Analyze products in "Zielhilfen, Optik & Zubehör" category
 *
 * This script finds all products assigned to this category and its subcategories,
 * then identifies products that shouldn't be there (like Muzzle attachments).
 *
 * Usage:
 *   php analyze-zielhilfen-categories.php              # Analyze only
 *   php analyze-zielhilfen-categories.php --fix        # Fix incorrect assignments
 */

$baseUrl = 'https://ortak.ch/api';
$clientId = 'SWIAC3HJVHFJMHQYRWRUM1E1SG';
$clientSecret = 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg';

$fixMode = in_array('--fix', $argv);

function getAccessToken($baseUrl, $clientId, $clientSecret) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/oauth/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response
    ];
}

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║   ANALYZE PRODUCTS IN 'Zielhilfen, Optik & Zubehör' CATEGORY      ║\n";
echo "╠════════════════════════════════════════════════════════════════════╣\n";
echo "║  Mode: " . ($fixMode ? "FIX MODE (will remove incorrect assignments)" : "ANALYZE ONLY (preview)                   ") . "    ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// Get API token
$token = getAccessToken($baseUrl, $clientId, $clientSecret);
if (!$token) {
    echo "ERROR: Could not get API token\n";
    exit(1);
}
echo "✓ Got API token\n\n";

// Step 1: Get all categories
echo "1. Fetching all categories...\n";
$catResponse = apiRequest($baseUrl, $token, 'POST', '/search/category', ['limit' => 500]);
$categories = [];
$categoryById = [];

if (isset($catResponse['body']['data'])) {
    foreach ($catResponse['body']['data'] as $cat) {
        $name = $cat['translated']['name'] ?? $cat['name'] ?? '';
        $categories[$cat['id']] = [
            'id' => $cat['id'],
            'name' => $name,
            'parentId' => $cat['parentId'] ?? null,
            'breadcrumb' => $cat['translated']['breadcrumb'] ?? $cat['breadcrumb'] ?? [],
            'path' => $cat['path'] ?? ''
        ];
        $categoryById[$cat['id']] = $name;
    }
}
echo "   Found " . count($categories) . " categories\n\n";

// Step 2: Find "Zielhilfen, Optik & Zubehör" and its subcategories
echo "2. Finding 'Zielhilfen, Optik & Zubehör' category...\n";
$zielhilfenId = null;
$zielhilfenSubcategories = [];

foreach ($categories as $id => $cat) {
    if (stripos($cat['name'], 'Zielhilfen') !== false || stripos($cat['name'], 'Optik') !== false) {
        $zielhilfenId = $id;
        echo "   Found: {$cat['name']} (ID: $id)\n";

        // Find subcategories (Spektive, Zielfernrohre, Ferngläser, Rotpunktvisiere)
        foreach ($categories as $subId => $subCat) {
            if ($subCat['parentId'] === $id) {
                $zielhilfenSubcategories[$subId] = $subCat['name'];
                echo "   ↳ Subcategory: {$subCat['name']} (ID: $subId)\n";
            }
        }
        break;
    }
}

if (!$zielhilfenId) {
    echo "   ERROR: Could not find 'Zielhilfen, Optik & Zubehör' category!\n";

    // List categories containing relevant keywords
    echo "\n   Available categories with similar names:\n";
    foreach ($categories as $id => $cat) {
        if (stripos($cat['name'], 'Optik') !== false ||
            stripos($cat['name'], 'Ziel') !== false ||
            stripos($cat['name'], 'Spektiv') !== false ||
            stripos($cat['name'], 'Fernglas') !== false) {
            $path = implode(' > ', array_slice($cat['breadcrumb'], 1));
            echo "   - {$cat['name']} (ID: $id)\n     Path: $path\n";
        }
    }
    exit(1);
}

echo "\n";

// Step 3: Get products assigned to these categories
echo "3. Fetching products in these categories...\n\n";

$allCategoryIds = array_merge([$zielhilfenId], array_keys($zielhilfenSubcategories));
$productsInZielhilfen = [];

foreach ($allCategoryIds as $catId) {
    $catName = $categoryById[$catId] ?? $catId;

    $prodResponse = apiRequest($baseUrl, $token, 'POST', '/search/product', [
        'limit' => 500,
        'filter' => [
            [
                'type' => 'equals',
                'field' => 'categories.id',
                'value' => $catId
            ]
        ],
        'associations' => [
            'categories' => [],
            'manufacturer' => []
        ],
        'includes' => [
            'product' => ['id', 'name', 'productNumber', 'categories', 'manufacturer', 'translated'],
            'category' => ['id', 'name', 'translated'],
            'product_manufacturer' => ['id', 'name', 'translated']
        ]
    ]);

    if (isset($prodResponse['body']['data']) && !empty($prodResponse['body']['data'])) {
        echo "   Category: $catName\n";

        foreach ($prodResponse['body']['data'] as $product) {
            $prodId = $product['id'];
            $prodName = $product['translated']['name'] ?? $product['name'] ?? 'Unknown';
            $prodNumber = $product['productNumber'] ?? '';
            $manufacturer = '';

            if (isset($product['manufacturer'])) {
                $manufacturer = $product['manufacturer']['translated']['name'] ??
                               $product['manufacturer']['name'] ?? '';
            }

            // Get all categories for this product
            $prodCategories = [];
            if (isset($product['categories'])) {
                foreach ($product['categories'] as $pCat) {
                    $pCatName = $pCat['translated']['name'] ?? $pCat['name'] ?? '';
                    $prodCategories[$pCat['id']] = $pCatName;
                }
            }

            if (!isset($productsInZielhilfen[$prodId])) {
                $productsInZielhilfen[$prodId] = [
                    'id' => $prodId,
                    'name' => $prodName,
                    'productNumber' => $prodNumber,
                    'manufacturer' => $manufacturer,
                    'allCategories' => $prodCategories,
                    'foundInCategories' => []
                ];
            }

            $productsInZielhilfen[$prodId]['foundInCategories'][$catId] = $catName;
        }

        echo "   → Found " . count($prodResponse['body']['data']) . " products\n\n";
    }
}

// Step 4: Analyze which products shouldn't be here
echo "4. Analyzing products that may be incorrectly assigned...\n\n";

// Products in Zielhilfen should ONLY be optics-related:
// - Spektive (spotting scopes)
// - Zielfernrohre (rifle scopes)
// - Ferngläser (binoculars)
// - Rotpunktvisiere (red dot sights)

// Keywords that indicate a product belongs to Zielhilfen
$opticKeywords = [
    'scope', 'Zielfernrohr', 'Fernglas', 'Spektiv', 'Rotpunkt', 'Red Dot',
    'sight', 'optic', 'Optik', 'binocular', 'monocular', 'Visier', 'Holographic',
    'Aimpoint', 'ACOG', 'EOTech', 'Vortex', 'Leupold', 'Kahles', 'Schmidt', 'Swarovski',
    'magnifier', 'reflex'
];

// Keywords that indicate a product does NOT belong (Muzzle, tactical gear, etc.)
$nonOpticKeywords = [
    'Muzzle', 'Brake', 'Flash', 'Suppressor', 'Silencer', 'Compensator',
    'Pouch', 'Holster', 'Vest', 'Belt', 'Bag', 'Pack', 'Sling',
    'Magazine', 'Mag', 'Grip', 'Stock', 'Handguard', 'Rail',
    'Tactical', 'Taktisch', 'Ausrüstung', 'Gear'
];

$correctProducts = [];
$incorrectProducts = [];

foreach ($productsInZielhilfen as $prodId => $product) {
    $productNameLower = strtolower($product['name']);
    $isOptic = false;
    $isNonOptic = false;

    // Check if it's an optic
    foreach ($opticKeywords as $keyword) {
        if (stripos($product['name'], $keyword) !== false) {
            $isOptic = true;
            break;
        }
    }

    // Check if it's clearly not an optic
    foreach ($nonOpticKeywords as $keyword) {
        if (stripos($product['name'], $keyword) !== false) {
            $isNonOptic = true;
            break;
        }
    }

    // Also check if it's in a non-optic category
    foreach ($product['allCategories'] as $catId => $catName) {
        if (stripos($catName, 'Muzzle') !== false ||
            stripos($catName, 'Taktisch') !== false ||
            stripos($catName, 'Ausrüstung') !== false) {
            $isNonOptic = true;
        }
    }

    if ($isNonOptic && !$isOptic) {
        $incorrectProducts[$prodId] = $product;
    } else {
        $correctProducts[$prodId] = $product;
    }
}

// Step 5: Display results
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║                         ANALYSIS RESULTS                          ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

echo "Products found in 'Zielhilfen, Optik & Zubehör': " . count($productsInZielhilfen) . "\n";
echo "Products correctly assigned: " . count($correctProducts) . "\n";
echo "Products INCORRECTLY assigned: " . count($incorrectProducts) . "\n\n";

if (!empty($incorrectProducts)) {
    echo "────────────────────────────────────────────────────────────────────\n";
    echo "PRODUCTS THAT SHOULD NOT BE IN 'Zielhilfen, Optik & Zubehör':\n";
    echo "────────────────────────────────────────────────────────────────────\n\n";

    foreach ($incorrectProducts as $prodId => $product) {
        echo "❌ {$product['name']}\n";
        echo "   SKU: {$product['productNumber']}\n";
        echo "   Manufacturer: {$product['manufacturer']}\n";
        echo "   All categories:\n";
        foreach ($product['allCategories'] as $catId => $catName) {
            $isZielhilfen = in_array($catId, $allCategoryIds);
            $marker = $isZielhilfen ? '  ⚠️  ' : '  ✓  ';
            echo "$marker $catName\n";
        }
        echo "   Found in Zielhilfen subcategories:\n";
        foreach ($product['foundInCategories'] as $catId => $catName) {
            echo "      → $catName\n";
        }
        echo "\n";
    }
}

if (!empty($correctProducts)) {
    echo "────────────────────────────────────────────────────────────────────\n";
    echo "PRODUCTS CORRECTLY IN 'Zielhilfen, Optik & Zubehör':\n";
    echo "────────────────────────────────────────────────────────────────────\n\n";

    foreach ($correctProducts as $prodId => $product) {
        echo "✓ {$product['name']}\n";
    }
    echo "\n";
}

// Step 6: Fix mode - remove incorrect assignments
if ($fixMode && !empty($incorrectProducts)) {
    echo "════════════════════════════════════════════════════════════════════\n";
    echo "FIXING INCORRECT CATEGORY ASSIGNMENTS...\n";
    echo "════════════════════════════════════════════════════════════════════\n\n";

    $fixed = 0;
    $errors = 0;

    foreach ($incorrectProducts as $prodId => $product) {
        echo "Fixing: {$product['name']}...\n";

        // Remove from all Zielhilfen categories
        $syncPayload = [];
        foreach ($product['foundInCategories'] as $catId => $catName) {
            $syncPayload[] = [
                'action' => 'delete',
                'entity' => 'product_category',
                'payload' => [
                    ['productId' => $prodId, 'categoryId' => $catId]
                ]
            ];
        }

        if (!empty($syncPayload)) {
            $syncResponse = apiRequest($baseUrl, $token, 'POST', '/_action/sync', $syncPayload);

            if ($syncResponse['code'] === 200 || $syncResponse['code'] === 204) {
                echo "   ✓ Removed from " . count($product['foundInCategories']) . " incorrect categories\n";
                $fixed++;
            } else {
                echo "   ✗ ERROR: " . ($syncResponse['body']['errors'][0]['detail'] ?? 'Unknown error') . "\n";
                $errors++;
            }
        }
        echo "\n";
    }

    echo "════════════════════════════════════════════════════════════════════\n";
    echo "FIX SUMMARY:\n";
    echo "  Fixed: $fixed products\n";
    echo "  Errors: $errors\n";
    echo "════════════════════════════════════════════════════════════════════\n\n";

    echo "Run on server to refresh indexes:\n";
    echo "  docker exec shopware-chf bin/console dal:refresh:index\n";
    echo "  docker exec shopware-chf bin/console cache:clear\n\n";
} elseif (!$fixMode && !empty($incorrectProducts)) {
    echo "────────────────────────────────────────────────────────────────────\n";
    echo "To fix these assignments, run:\n";
    echo "  php analyze-zielhilfen-categories.php --fix\n";
    echo "────────────────────────────────────────────────────────────────────\n";
}

// Save analysis log
$logFile = __DIR__ . '/zielhilfen-analysis-' . date('Y-m-d-His') . '.json';
file_put_contents($logFile, json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'fixMode' => $fixMode,
    'zielhilfenCategory' => [
        'id' => $zielhilfenId,
        'subcategories' => $zielhilfenSubcategories
    ],
    'totalProducts' => count($productsInZielhilfen),
    'correctProducts' => array_keys($correctProducts),
    'incorrectProducts' => $incorrectProducts
], JSON_PRETTY_PRINT));
echo "\nAnalysis saved to: $logFile\n";
