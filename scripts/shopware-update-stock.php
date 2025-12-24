<?php
/**
 * Shopware Stock Updater
 * Syncs stock levels from Snigel B2B scraper to Shopware
 *
 * Usage: php shopware-update-stock.php
 */

$config = [
    // Use localhost when running inside Docker container (bypasses Cloudflare)
    'shopware_url' => 'http://localhost',
    'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
    'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg',
    'stock_file' => '/tmp/snigel-stock-data/stock-2025-12-23.json',
];

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║       SHOPWARE STOCK UPDATER - SNIGEL PRODUCTS             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Check if stock file exists
if (!file_exists($config['stock_file'])) {
    die("ERROR: Stock file not found: {$config['stock_file']}\n");
}

// Load stock data
$stockData = json_decode(file_get_contents($config['stock_file']), true);
echo "Loaded stock data: {$stockData['totalProducts']} products, {$stockData['totalVariants']} variants\n";
echo "Scraped at: {$stockData['scrapedAt']}\n\n";

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
            'grant_type' => 'client_credentials',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
        ]),
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "CURL Error: $error\n";
        return null;
    }

    $data = json_decode($response, true);
    if (!isset($data['access_token'])) {
        echo "Auth Error: " . print_r($data, true) . "\n";
        return null;
    }

    $GLOBALS['token_data']['token'] = $data['access_token'];
    $GLOBALS['token_data']['expires_at'] = time() + ($data['expires_in'] ?? 600);

    return $GLOBALS['token_data']['token'];
}

function apiRequest($method, $endpoint, $data, $config) {
    $token = getAccessToken($config);
    if (!$token) return ['code' => 0, 'body' => null];

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . '/api/' . ltrim($endpoint, '/'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    if ($data && in_array($method, ['POST', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

function findProductBySKU($sku, $config) {
    $result = apiRequest('POST', '/search/product', [
        'filter' => [['type' => 'equals', 'field' => 'productNumber', 'value' => $sku]],
        'includes' => ['product' => ['id', 'productNumber', 'name', 'stock', 'availableStock']]
    ], $config);

    return $result['body']['data'][0] ?? null;
}

// Authenticate
echo "Authenticating with Shopware API...\n";
$token = getAccessToken($config);
if (!$token) {
    die("Failed to authenticate with Shopware API\n");
}
echo "✓ Authenticated successfully\n\n";

// Process all products and variants
$stats = [
    'updated' => 0,
    'not_found' => 0,
    'errors' => 0,
    'skipped' => 0,
];

$notFoundSkus = [];
$productCount = count($stockData['products']);

echo "Processing {$productCount} products...\n";
echo str_repeat("-", 60) . "\n";

foreach ($stockData['products'] as $idx => $product) {
    $progress = "[" . ($idx + 1) . "/$productCount]";
    $productName = substr($product['name'], 0, 35);

    foreach ($product['variants'] as $variant) {
        $sku = $variant['sku'];

        // Skip if SKU is empty or just base SKU
        if (empty($sku) || strlen($sku) < 5) {
            $stats['skipped']++;
            continue;
        }

        // Determine stock value
        $newStock = $variant['stock'];

        // Handle special statuses
        if ($variant['status'] === 'out_of_stock') {
            $newStock = 0;
        } elseif ($variant['status'] === 'no_info') {
            // Skip products with no stock info - don't update
            $stats['skipped']++;
            continue;
        }

        // Find product in Shopware
        $shopwareProduct = findProductBySKU($sku, $config);

        if (!$shopwareProduct) {
            $notFoundSkus[] = $sku;
            $stats['not_found']++;
            continue;
        }

        $productId = $shopwareProduct['id'];
        $currentStock = $shopwareProduct['stock'] ?? 0;

        // Determine isCloseout based on canBackorder
        // canBackorder = true → isCloseout = false (allow purchase when stock = 0)
        // canBackorder = false/missing + out_of_stock → isCloseout = true (stop selling at 0)
        $canBackorder = $variant['canBackorder'] ?? false;
        $isCloseout = !$canBackorder && ($variant['status'] === 'out_of_stock' || $newStock == 0);

        // Update stock and isCloseout
        $updateData = [
            'stock' => (int) $newStock,
            'isCloseout' => $isCloseout,
        ];

        $result = apiRequest('PATCH', "/product/$productId", $updateData, $config);

        if ($result['code'] === 204 || $result['code'] === 200) {
            $stockChange = $newStock - $currentStock;
            $changeStr = $stockChange >= 0 ? "+$stockChange" : "$stockChange";
            $closeoutStr = $isCloseout ? " [CLOSEOUT]" : "";
            echo "$progress $sku: $currentStock → $newStock ($changeStr)$closeoutStr\n";
            $stats['updated']++;
        } else {
            echo "$progress $sku: ERROR ({$result['code']})\n";
            $stats['errors']++;
        }

        // Small delay to avoid rate limiting
        usleep(100000); // 100ms
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                  STOCK UPDATE COMPLETE                     ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
printf("║  Products updated:     %-35s ║\n", $stats['updated']);
printf("║  Not found in Shopware: %-34s ║\n", $stats['not_found']);
printf("║  Skipped (no info):    %-35s ║\n", $stats['skipped']);
printf("║  Errors:               %-35s ║\n", $stats['errors']);
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Show some not found SKUs
if (!empty($notFoundSkus)) {
    echo "Sample SKUs not found in Shopware (first 10):\n";
    foreach (array_slice($notFoundSkus, 0, 10) as $sku) {
        echo "  - $sku\n";
    }
    if (count($notFoundSkus) > 10) {
        echo "  ... and " . (count($notFoundSkus) - 10) . " more\n";
    }
    echo "\n";
}

echo "Done!\n";
