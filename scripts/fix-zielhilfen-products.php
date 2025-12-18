<?php
/**
 * Fix products incorrectly assigned to "Zielhilfen, Optik & Zubehör"
 *
 * This script removes Magpul accessories and Muzzle attachments from the
 * Zielhilfen parent category and assigns them to the correct Zubehör subcategories.
 *
 * Usage:
 *   php fix-zielhilfen-products.php              # Dry run (preview)
 *   php fix-zielhilfen-products.php --execute    # Apply changes
 */

$baseUrl = 'https://ortak.ch/api';
$clientId = 'SWIAC3HJVHFJMHQYRWRUM1E1SG';
$clientSecret = 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg';

$dryRun = !in_array('--execute', $argv);

function getAccessToken($baseUrl, $clientId, $clientSecret) {
    $ch = curl_init($baseUrl . '/oauth/token');
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
    return json_decode($response, true)['access_token'];
}

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init($baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
        'sw-language-id: 2fbb5fe2e29a4d70aa5854ce7ce3e20b'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($response, true)];
}

echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║   FIX PRODUCTS IN 'Zielhilfen, Optik & Zubehör' CATEGORY                     ║\n";
echo "╠══════════════════════════════════════════════════════════════════════════════╣\n";
echo "║   Mode: " . ($dryRun ? "DRY RUN (preview only)        " : "EXECUTE (applying changes)    ") . "                           ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

if ($dryRun) {
    echo "Run with --execute flag to apply changes.\n\n";
}

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "✓ Got API token\n\n";

// Category IDs
$zielhilfenParentId = '019adeff65f97225927586968691dc02'; // Wrong category to remove from

// Correct target categories in Zubehör
$targetCategories = [
    'muendung' => [
        'id' => 'b2f4bafcda154b899c22bfb74496d140',
        'name' => 'Mündungsaufsätze',
        'path' => 'Zubehör > Mündungsaufsätze'
    ],
    'magazine' => [
        'id' => '00a19869155b4c0d9508dfcfeeaf93d7',
        'name' => 'Magazine',
        'path' => 'Zubehör > Magazine'
    ],
    'griffe' => [
        'id' => '6aa33f0f12e543fbb28d2bd7ede4dbf2',
        'name' => 'Griffe & Handschutz',
        'path' => 'Zubehör > Griffe & Handschutz'
    ],
    'zweibeine' => [
        'id' => '17d31faee72a4f0eb9863adf8bab9b00',
        'name' => 'Zweibeine',
        'path' => 'Zubehör > Zweibeine'
    ],
    'schienen' => [
        'id' => '2d9fa9cea22f4d8e8c80fc059f8fc47d',
        'name' => 'Schienen & Zubehör',
        'path' => 'Zubehör > Schienen & Zubehör'
    ]
];

// Get products currently in Zielhilfen parent category
echo "1. Fetching products in 'Zielhilfen, Optik & Zubehör' parent category...\n";
$response = apiRequest($baseUrl, $token, 'POST', '/search/product', [
    'limit' => 500,
    'filter' => [
        ['type' => 'equals', 'field' => 'categories.id', 'value' => $zielhilfenParentId]
    ],
    'associations' => ['categories' => []]
]);

$productsToFix = [];

foreach ($response['body']['data'] ?? [] as $prod) {
    $attrs = $prod['attributes'] ?? $prod;
    $prodId = $prod['id'];
    $name = $attrs['translated']['name'] ?? $attrs['name'] ?? '';
    $sku = $attrs['productNumber'] ?? '';

    // Determine correct category based on product type
    $correctCategory = null;
    $reason = '';

    // Muzzle attachments
    if (stripos($name, 'Flash') !== false || stripos($name, 'Hider') !== false ||
        stripos($name, 'Muzzle') !== false || stripos($name, 'brake') !== false ||
        stripos($sku, 'MDB') !== false || stripos($sku, 'FSD') !== false) {
        $correctCategory = $targetCategories['muendung'];
        $reason = 'Muzzle attachment';
    }
    // Magazines (PMAG, TMAG)
    elseif (stripos($name, 'PMAG') !== false || stripos($name, 'TMAG') !== false ||
            stripos($sku, 'MGP-MG') !== false) {
        $correctCategory = $targetCategories['magazine'];
        $reason = 'Magazine';
    }
    // Stocks
    elseif (stripos($name, 'Stock') !== false || stripos($name, 'PRS') !== false) {
        $correctCategory = $targetCategories['schienen'];
        $reason = 'Stock → Schienen & Zubehör';
    }
    // Grips (AFG, RVG, MVG)
    elseif (stripos($name, 'Grip') !== false || stripos($name, 'AFG') !== false ||
            stripos($name, 'RVG') !== false || stripos($name, 'MVG') !== false ||
            stripos($name, 'Hand Stop') !== false || stripos($name, 'XTM') !== false) {
        $correctCategory = $targetCategories['griffe'];
        $reason = 'Grip / Hand Stop';
    }
    // Bipods
    elseif (stripos($name, 'Bipod') !== false) {
        $correctCategory = $targetCategories['zweibeine'];
        $reason = 'Bipod';
    }

    if ($correctCategory) {
        $productsToFix[$prodId] = [
            'id' => $prodId,
            'name' => $name,
            'sku' => $sku,
            'reason' => $reason,
            'correctCategory' => $correctCategory
        ];
    }
}

echo "   Found " . count($productsToFix) . " products to fix\n\n";

if (empty($productsToFix)) {
    echo "✓ No products need fixing. All products in Zielhilfen are correct.\n";
    exit(0);
}

// Show what will be fixed
echo "2. Products to fix:\n";
echo str_repeat('-', 80) . "\n\n";

$byCategory = [];
foreach ($productsToFix as $product) {
    $catName = $product['correctCategory']['name'];
    if (!isset($byCategory[$catName])) {
        $byCategory[$catName] = [];
    }
    $byCategory[$catName][] = $product;
}

foreach ($byCategory as $catName => $products) {
    echo "📁 Move to: $catName (" . count($products) . " products)\n";
    foreach ($products as $product) {
        echo "   → {$product['name']}\n";
        echo "     SKU: {$product['sku']}\n";
    }
    echo "\n";
}

// Apply fixes
if (!$dryRun) {
    echo "3. Applying fixes...\n";
    echo str_repeat('-', 80) . "\n\n";

    $success = 0;
    $errors = 0;

    foreach ($productsToFix as $prodId => $product) {
        echo "Fixing: {$product['name']}...\n";

        // Build sync payload:
        // 1. Remove from Zielhilfen parent
        // 2. Add to correct category (if not already there)
        $syncPayload = [
            [
                'action' => 'delete',
                'entity' => 'product_category',
                'payload' => [
                    ['productId' => $prodId, 'categoryId' => $zielhilfenParentId]
                ]
            ],
            [
                'action' => 'upsert',
                'entity' => 'product_category',
                'payload' => [
                    ['productId' => $prodId, 'categoryId' => $product['correctCategory']['id']]
                ]
            ]
        ];

        $result = apiRequest($baseUrl, $token, 'POST', '/_action/sync', $syncPayload);

        if ($result['code'] === 200 || $result['code'] === 204) {
            echo "   ✓ Removed from Zielhilfen, added to {$product['correctCategory']['name']}\n";

            // Update main category for breadcrumbs
            // First check if main category exists
            $mainCatResponse = apiRequest($baseUrl, $token, 'POST', '/search/main-category', [
                'filter' => [
                    ['type' => 'equals', 'field' => 'productId', 'value' => $prodId]
                ]
            ]);

            $existingMainCat = $mainCatResponse['body']['data'][0]['id'] ?? null;

            if ($existingMainCat) {
                apiRequest($baseUrl, $token, 'PATCH', '/main-category/' . $existingMainCat, [
                    'categoryId' => $product['correctCategory']['id']
                ]);
            }

            $success++;
        } else {
            $errorMsg = $result['body']['errors'][0]['detail'] ?? 'Unknown error';
            echo "   ✗ ERROR: $errorMsg\n";
            $errors++;
        }
        echo "\n";
    }

    echo str_repeat('=', 80) . "\n";
    echo "SUMMARY:\n";
    echo "  ✓ Fixed: $success products\n";
    echo "  ✗ Errors: $errors\n";
    echo str_repeat('=', 80) . "\n\n";

    echo "Run on server to refresh indexes:\n";
    echo "  docker exec shopware-chf bin/console dal:refresh:index\n";
    echo "  docker exec shopware-chf bin/console cache:clear\n\n";
} else {
    echo "3. To apply these changes, run:\n";
    echo "   php fix-zielhilfen-products.php --execute\n\n";
}

// Save log
$logFile = __DIR__ . '/fix-zielhilfen-log-' . date('Y-m-d-His') . '.json';
file_put_contents($logFile, json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'dryRun' => $dryRun,
    'productsToFix' => $productsToFix,
    'summary' => [
        'total' => count($productsToFix),
        'byCategory' => array_map('count', $byCategory)
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Log saved to: $logFile\n";
