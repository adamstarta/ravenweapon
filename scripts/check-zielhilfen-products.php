<?php
/**
 * Check which products are in Zielhilfen, Optik & Zubehör categories
 * Uses direct category filter approach
 */

$baseUrl = 'https://ortak.ch/api';
$clientId = 'SWIAC3HJVHFJMHQYRWRUM1E1SG';
$clientSecret = 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg';

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

function apiRequest($baseUrl, $token, $endpoint, $data = null) {
    $ch = curl_init($baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
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
    return json_decode($response, true);
}

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "CHECK PRODUCTS IN 'Zielhilfen, Optik & Zubehör' CATEGORIES\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n\n";

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "✓ Got API token\n\n";

// Zielhilfen category and subcategories
$zielhilfenCategories = [
    '019adeff65f97225927586968691dc02' => 'Zielhilfen, Optik & Zubehör (Parent)',
    'b47d4447067c45aaa0aed7081ac465c4' => 'Ferngläser',
    'c34b9bb9a7a6473d928dd9c7c0f10f8b' => 'Rotpunktvisiere',
    '461b4f23bcd74823abbb55550af5008c' => 'Spektive',
    '499800fd06224f779acc5c4ac243d2e8' => 'Zielfernrohre',
];

// Correct categories for different products
$correctCategories = [
    'Mündungsaufsätze' => [
        'id' => 'b2f4bafcda154b899c22bfb74496d140',
        'path' => 'Zubehör > Mündungsaufsätze'
    ],
    'Magazine' => [
        'id' => '00a19869155b4c0d9508dfcfeeaf93d7',
        'path' => 'Zubehör > Magazine'
    ],
    'Griffe & Handschutz' => [
        'id' => '6aa33f0f12e543fbb28d2bd7ede4dbf2',
        'path' => 'Zubehör > Griffe & Handschutz'
    ]
];

// For each Zielhilfen category, fetch products directly
$allProductsInZielhilfen = [];
$incorrectProducts = [];

foreach ($zielhilfenCategories as $catId => $catName) {
    echo "Checking: $catName\n";

    // Fetch products in this category
    $response = apiRequest($baseUrl, $token, '/search/product', [
        'limit' => 500,
        'filter' => [
            [
                'type' => 'equals',
                'field' => 'categories.id',
                'value' => $catId
            ]
        ],
        'associations' => [
            'categories' => []
        ]
    ]);

    $count = $response['meta']['total'] ?? count($response['data'] ?? []);
    echo "   Found $count products\n";

    if (isset($response['data']) && !empty($response['data'])) {
        foreach ($response['data'] as $prod) {
            $attrs = $prod['attributes'] ?? $prod;
            $prodId = $prod['id'];
            $name = $attrs['translated']['name'] ?? $attrs['name'] ?? '';
            $productNumber = $attrs['productNumber'] ?? '';

            // Get all category IDs for this product
            $productCats = [];
            if (isset($prod['relationships']['categories']['data'])) {
                foreach ($prod['relationships']['categories']['data'] as $catRef) {
                    $productCats[] = $catRef['id'];
                }
            }

            if (!isset($allProductsInZielhilfen[$prodId])) {
                $allProductsInZielhilfen[$prodId] = [
                    'id' => $prodId,
                    'name' => $name,
                    'productNumber' => $productNumber,
                    'allCategories' => $productCats,
                    'foundInZielhilfen' => []
                ];
            }

            $allProductsInZielhilfen[$prodId]['foundInZielhilfen'][$catId] = $catName;

            // Check if this product should NOT be in Zielhilfen
            $isMuzzle = stripos($name, 'Flash') !== false ||
                        stripos($name, 'Muzzle') !== false ||
                        stripos($name, 'Mündung') !== false ||
                        stripos($name, 'Brake') !== false ||
                        stripos($name, 'Hider') !== false ||
                        stripos($name, 'HexaLug') !== false;

            $isMagpulAccessory = stripos($productNumber, 'MGP') !== false &&
                                  (stripos($name, 'Stock') !== false ||
                                   stripos($name, 'Grip') !== false ||
                                   stripos($name, 'MOE') !== false ||
                                   stripos($name, 'PMAG') !== false ||
                                   stripos($name, 'XTM') !== false ||
                                   stripos($name, 'Bipod') !== false ||
                                   stripos($name, 'AFG') !== false ||
                                   stripos($name, 'RVG') !== false ||
                                   stripos($name, 'MVG') !== false);

            if ($isMuzzle || $isMagpulAccessory) {
                if (!isset($incorrectProducts[$prodId])) {
                    $incorrectProducts[$prodId] = $allProductsInZielhilfen[$prodId];
                    $incorrectProducts[$prodId]['reason'] = $isMuzzle ? 'Muzzle attachment' : 'Magpul accessory';
                }
            }
        }
    }
    echo "\n";
}

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "SUMMARY: Found " . count($allProductsInZielhilfen) . " total products in Zielhilfen categories\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n\n";

// Display all products grouped by category
foreach ($zielhilfenCategories as $catId => $catName) {
    $productsInCat = array_filter($allProductsInZielhilfen, fn($p) => isset($p['foundInZielhilfen'][$catId]));

    if (!empty($productsInCat)) {
        echo "📁 $catName (" . count($productsInCat) . " products)\n";
        echo str_repeat('-', 70) . "\n";

        foreach ($productsInCat as $product) {
            $marker = isset($incorrectProducts[$product['id']]) ? '❌' : '✓';
            $reason = isset($incorrectProducts[$product['id']]) ? " [{$incorrectProducts[$product['id']]['reason']}]" : '';
            echo "  $marker {$product['name']}$reason\n";
            echo "    SKU: {$product['productNumber']}\n";
        }
        echo "\n";
    }
}

// Show incorrect products
if (!empty($incorrectProducts)) {
    echo "\n═══════════════════════════════════════════════════════════════════════════════\n";
    echo "⚠️  PRODUCTS THAT SHOULD NOT BE IN 'Zielhilfen, Optik & Zubehör':\n";
    echo "    (These should be in Zubehör subcategories instead)\n";
    echo "═══════════════════════════════════════════════════════════════════════════════\n\n";

    foreach ($incorrectProducts as $product) {
        echo "❌ {$product['name']}\n";
        echo "   SKU: {$product['productNumber']}\n";
        echo "   Reason: {$product['reason']}\n";
        echo "   Wrong categories (Zielhilfen):\n";
        foreach ($product['foundInZielhilfen'] as $catId => $catName) {
            echo "      ✗ $catName\n";
        }
        echo "\n";
    }

    echo "\n═══════════════════════════════════════════════════════════════════════════════\n";
    echo "TO FIX: Run fix-zielhilfen-products.php --execute\n";
    echo "═══════════════════════════════════════════════════════════════════════════════\n";
} else {
    echo "✓ All products in Zielhilfen categories appear to be correct.\n\n";
}

// Save report
file_put_contents(__DIR__ . '/zielhilfen-products-report.json', json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'totalInZielhilfen' => count($allProductsInZielhilfen),
    'incorrectCount' => count($incorrectProducts),
    'allProducts' => $allProductsInZielhilfen,
    'incorrectProducts' => $incorrectProducts
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\nReport saved to zielhilfen-products-report.json\n";
