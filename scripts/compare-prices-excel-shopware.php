<?php
/**
 * Compare Product Prices: Excel vs Shopware Storefront
 *
 * Compares selling_price from Excel with gross price in Shopware.
 * Excludes Raven Swiss products.
 *
 * Usage:
 *   php compare-prices-excel-shopware.php
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

echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║           PRICE COMPARISON: EXCEL vs SHOPWARE STOREFRONT                     ║\n";
echo "║                    (Excluding Raven Swiss products)                          ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

// Load Excel data (JSON)
$excelFile = __DIR__ . '/products-excel-data.json';
if (!file_exists($excelFile)) {
    echo "ERROR: Excel data file not found: $excelFile\n";
    exit(1);
}

$excelProducts = json_decode(file_get_contents($excelFile), true);
echo "1. Loaded " . count($excelProducts) . " products from Excel\n";

// Filter out Raven Swiss products
$excelProducts = array_filter($excelProducts, function($p) {
    $name = $p['name_de'] ?? '';
    return stripos($name, 'Raven Swiss') === false;
});
$excelProducts = array_values($excelProducts); // Re-index

echo "   After excluding Raven Swiss: " . count($excelProducts) . " products\n\n";

if (empty($excelProducts)) {
    echo "ERROR: No products found in Excel data\n";
    exit(1);
}

// Build lookup by SKU
$excelBySku = [];
foreach ($excelProducts as $p) {
    $sku = $p['number'] ?? '';
    if ($sku) {
        $excelBySku[$sku] = [
            'sku' => $sku,
            'name' => $p['name_de'] ?? '',
            'selling_price' => floatval($p['selling_price'] ?? 0),
            'purchase_price' => floatval($p['purchase_price'] ?? 0),
            'merchant_price' => floatval($p['merchant_price'] ?? 0),
        ];
    }
}

// Get Shopware products
$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "2. Got API token\n\n";

echo "3. Fetching Shopware products...\n";
$shopwareProducts = [];
$page = 1;
$limit = 100;

do {
    $response = apiRequest($baseUrl, $token, '/search/product', [
        'limit' => $limit,
        'page' => $page,
        'associations' => [
            'prices' => []
        ]
    ]);

    if (isset($response['data'])) {
        foreach ($response['data'] as $prod) {
            $attrs = $prod['attributes'] ?? $prod;
            $sku = $attrs['productNumber'] ?? '';
            $name = $attrs['translated']['name'] ?? $attrs['name'] ?? '';

            // Skip Raven Swiss
            if (stripos($name, 'Raven Swiss') !== false) {
                continue;
            }

            // Get gross price (selling price with tax)
            $grossPrice = 0;
            if (isset($attrs['price']) && is_array($attrs['price'])) {
                foreach ($attrs['price'] as $priceData) {
                    $grossPrice = floatval($priceData['gross'] ?? 0);
                    break;
                }
            }

            $shopwareProducts[$sku] = [
                'id' => $prod['id'],
                'sku' => $sku,
                'name' => $name,
                'gross_price' => $grossPrice,
            ];
        }
    }

    $total = $response['meta']['total'] ?? 0;
    $page++;
} while (count($response['data'] ?? []) === $limit);

echo "   Found " . count($shopwareProducts) . " products in Shopware (excluding Raven Swiss)\n\n";

// Compare prices
echo "4. Comparing prices...\n\n";

$matches = [];
$mismatches = [];
$notInShopware = [];
$notInExcel = [];

// Check Excel products against Shopware
foreach ($excelBySku as $sku => $excelProd) {
    if (!isset($shopwareProducts[$sku])) {
        $notInShopware[] = $excelProd;
        continue;
    }

    $shopwareProd = $shopwareProducts[$sku];
    $excelPrice = $excelProd['selling_price'];
    $shopwarePrice = $shopwareProd['gross_price'];

    // Compare with tolerance of 0.01 CHF
    $diff = abs($excelPrice - $shopwarePrice);

    if ($diff < 0.01) {
        $matches[] = [
            'sku' => $sku,
            'name' => $excelProd['name'],
            'excel_price' => $excelPrice,
            'shopware_price' => $shopwarePrice,
        ];
    } else {
        $mismatches[] = [
            'sku' => $sku,
            'name' => $excelProd['name'],
            'excel_price' => $excelPrice,
            'shopware_price' => $shopwarePrice,
            'difference' => $excelPrice - $shopwarePrice,
        ];
    }
}

// Check for products in Shopware but not in Excel
foreach ($shopwareProducts as $sku => $shopwareProd) {
    if (!isset($excelBySku[$sku])) {
        $notInExcel[] = $shopwareProd;
    }
}

// Display results
echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                              RESULTS                                         ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

echo "SUMMARY:\n";
echo "  ✓ Prices match:          " . count($matches) . "\n";
echo "  ✗ Prices mismatch:       " . count($mismatches) . "\n";
echo "  ⚠ In Excel, not Shopware: " . count($notInShopware) . "\n";
echo "  ⚠ In Shopware, not Excel: " . count($notInExcel) . "\n\n";

// Show mismatches
if (!empty($mismatches)) {
    echo str_repeat('═', 80) . "\n";
    echo "PRICE MISMATCHES (Excel vs Shopware):\n";
    echo str_repeat('═', 80) . "\n\n";

    // Sort by difference (largest first)
    usort($mismatches, fn($a, $b) => abs($b['difference']) <=> abs($a['difference']));

    echo sprintf("%-40s %12s %12s %12s\n", "Product", "Excel CHF", "Shopware CHF", "Diff CHF");
    echo str_repeat('-', 80) . "\n";

    foreach ($mismatches as $m) {
        $name = mb_substr($m['name'], 0, 38);
        $diffSign = $m['difference'] > 0 ? '+' : '';
        echo sprintf("%-40s %12.2f %12.2f %12s\n",
            $name,
            $m['excel_price'],
            $m['shopware_price'],
            $diffSign . number_format($m['difference'], 2)
        );
    }
    echo "\n";
}

// Show products not in Shopware
if (!empty($notInShopware)) {
    echo str_repeat('═', 80) . "\n";
    echo "PRODUCTS IN EXCEL BUT NOT IN SHOPWARE:\n";
    echo str_repeat('═', 80) . "\n\n";

    foreach ($notInShopware as $p) {
        echo "  - [{$p['sku']}] {$p['name']} (CHF {$p['selling_price']})\n";
    }
    echo "\n";
}

// Show products not in Excel
if (!empty($notInExcel)) {
    echo str_repeat('═', 80) . "\n";
    echo "PRODUCTS IN SHOPWARE BUT NOT IN EXCEL (first 20):\n";
    echo str_repeat('═', 80) . "\n\n";

    $count = 0;
    foreach ($notInExcel as $p) {
        if ($count >= 20) {
            echo "  ... and " . (count($notInExcel) - 20) . " more\n";
            break;
        }
        echo "  - [{$p['sku']}] {$p['name']} (CHF {$p['gross_price']})\n";
        $count++;
    }
    echo "\n";
}

// Save detailed report
$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'summary' => [
        'excel_products' => count($excelBySku),
        'shopware_products' => count($shopwareProducts),
        'matches' => count($matches),
        'mismatches' => count($mismatches),
        'not_in_shopware' => count($notInShopware),
        'not_in_excel' => count($notInExcel),
    ],
    'mismatches' => $mismatches,
    'not_in_shopware' => $notInShopware,
    'not_in_excel' => array_slice($notInExcel, 0, 50),
];

$reportFile = __DIR__ . '/price-comparison-report-' . date('Y-m-d-His') . '.json';
file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Detailed report saved to: $reportFile\n";
