<?php
/**
 * Fix Price Mismatches: Update Shopware prices to match Excel
 *
 * Updates gross prices in Shopware for products where Excel prices differ.
 * Uses selling_price from Excel as the correct gross price (includes VAT).
 *
 * Usage:
 *   php fix-price-mismatches.php              # Dry run (preview)
 *   php fix-price-mismatches.php --execute    # Apply changes
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
echo "║           FIX PRICE MISMATCHES: EXCEL → SHOPWARE                             ║\n";
echo "╠══════════════════════════════════════════════════════════════════════════════╣\n";
echo "║   Mode: " . ($dryRun ? "DRY RUN (preview only)        " : "EXECUTE (applying changes)    ") . "                           ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

if ($dryRun) {
    echo "Run with --execute flag to apply changes.\n\n";
}

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
$excelProducts = array_values($excelProducts);

echo "   After excluding Raven Swiss: " . count($excelProducts) . " products\n\n";

// Build lookup by SKU
$excelBySku = [];
foreach ($excelProducts as $p) {
    $sku = $p['number'] ?? '';
    if ($sku) {
        $excelBySku[$sku] = [
            'sku' => $sku,
            'name' => $p['name_de'] ?? '',
            'selling_price' => floatval($p['selling_price'] ?? 0), // Gross price (with VAT)
            'vat_amount' => floatval($p['vat_amount'] ?? 8.1),
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
    $response = apiRequest($baseUrl, $token, 'POST', '/search/product', [
        'limit' => $limit,
        'page' => $page,
        'associations' => [
            'prices' => []
        ]
    ]);

    if (isset($response['body']['data'])) {
        foreach ($response['body']['data'] as $prod) {
            $attrs = $prod['attributes'] ?? $prod;
            $sku = $attrs['productNumber'] ?? '';
            $name = $attrs['translated']['name'] ?? $attrs['name'] ?? '';

            // Skip Raven Swiss
            if (stripos($name, 'Raven Swiss') !== false) {
                continue;
            }

            // Get price data
            $grossPrice = 0;
            $netPrice = 0;
            $currencyId = null;
            if (isset($attrs['price']) && is_array($attrs['price'])) {
                foreach ($attrs['price'] as $priceData) {
                    $grossPrice = floatval($priceData['gross'] ?? 0);
                    $netPrice = floatval($priceData['net'] ?? 0);
                    $currencyId = $priceData['currencyId'] ?? null;
                    break;
                }
            }

            $shopwareProducts[$sku] = [
                'id' => $prod['id'],
                'sku' => $sku,
                'name' => $name,
                'gross_price' => $grossPrice,
                'net_price' => $netPrice,
                'currency_id' => $currencyId,
            ];
        }
    }

    $page++;
} while (count($response['body']['data'] ?? []) === $limit);

echo "   Found " . count($shopwareProducts) . " products in Shopware\n\n";

// Find mismatches
echo "4. Finding price mismatches...\n\n";

$mismatches = [];
foreach ($excelBySku as $sku => $excelProd) {
    if (!isset($shopwareProducts[$sku])) {
        continue;
    }

    $shopwareProd = $shopwareProducts[$sku];
    $excelGross = $excelProd['selling_price'];
    $shopwareGross = $shopwareProd['gross_price'];

    // Compare with tolerance of 0.01 CHF
    $diff = abs($excelGross - $shopwareGross);

    if ($diff >= 0.01) {
        // Calculate net price from gross (divide by 1 + VAT rate)
        $vatRate = $excelProd['vat_amount'] / 100; // 8.1% = 0.081
        $newNetPrice = round($excelGross / (1 + $vatRate), 2);

        $mismatches[] = [
            'id' => $shopwareProd['id'],
            'sku' => $sku,
            'name' => $excelProd['name'],
            'excel_gross' => $excelGross,
            'shopware_gross' => $shopwareGross,
            'difference' => $excelGross - $shopwareGross,
            'new_net_price' => $newNetPrice,
            'new_gross_price' => $excelGross,
            'currency_id' => $shopwareProd['currency_id'],
        ];
    }
}

echo "   Found " . count($mismatches) . " products with price differences\n\n";

if (empty($mismatches)) {
    echo "✓ All prices match! No updates needed.\n";
    exit(0);
}

// Display mismatches
echo str_repeat('═', 90) . "\n";
echo "PRICE MISMATCHES TO FIX:\n";
echo str_repeat('═', 90) . "\n\n";

echo sprintf("%-45s %12s %12s %12s\n", "Product", "Excel CHF", "Shopware CHF", "Diff CHF");
echo str_repeat('-', 90) . "\n";

// Sort by difference (largest first)
usort($mismatches, fn($a, $b) => abs($b['difference']) <=> abs($a['difference']));

foreach ($mismatches as $m) {
    $name = mb_substr($m['name'], 0, 43);
    $diffSign = $m['difference'] > 0 ? '+' : '';
    echo sprintf("%-45s %12.2f %12.2f %12s\n",
        $name,
        $m['excel_gross'],
        $m['shopware_gross'],
        $diffSign . number_format($m['difference'], 2)
    );
}
echo "\n";

// Apply fixes
if (!$dryRun) {
    echo str_repeat('═', 90) . "\n";
    echo "5. Applying price updates...\n";
    echo str_repeat('═', 90) . "\n\n";

    $success = 0;
    $errors = 0;

    foreach ($mismatches as $m) {
        echo "Updating: {$m['name']}...\n";
        echo "   SKU: {$m['sku']}\n";
        echo "   Old price: {$m['shopware_gross']} CHF → New price: {$m['new_gross_price']} CHF\n";

        // Update product price via PATCH
        $updateData = [
            'price' => [
                [
                    'currencyId' => $m['currency_id'],
                    'gross' => $m['new_gross_price'],
                    'net' => $m['new_net_price'],
                    'linked' => true
                ]
            ]
        ];

        $result = apiRequest($baseUrl, $token, 'PATCH', '/product/' . $m['id'], $updateData);

        if ($result['code'] === 200 || $result['code'] === 204) {
            echo "   ✓ Price updated successfully\n";
            $success++;
        } else {
            $errorMsg = $result['body']['errors'][0]['detail'] ?? 'Unknown error';
            echo "   ✗ ERROR: $errorMsg\n";
            $errors++;
        }
        echo "\n";
    }

    echo str_repeat('=', 90) . "\n";
    echo "SUMMARY:\n";
    echo "  ✓ Updated: $success products\n";
    echo "  ✗ Errors: $errors\n";
    echo str_repeat('=', 90) . "\n\n";

    echo "Run on server to refresh caches:\n";
    echo "  docker exec shopware-chf bin/console cache:clear\n\n";
} else {
    echo str_repeat('═', 90) . "\n";
    echo "5. To apply these price updates, run:\n";
    echo "   php fix-price-mismatches.php --execute\n";
    echo str_repeat('═', 90) . "\n\n";
}

// Save log
$logFile = __DIR__ . '/fix-prices-log-' . date('Y-m-d-His') . '.json';
file_put_contents($logFile, json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'dryRun' => $dryRun,
    'mismatches' => $mismatches,
    'summary' => [
        'total' => count($mismatches),
        'totalDifference' => array_sum(array_column($mismatches, 'difference'))
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Log saved to: $logFile\n";
