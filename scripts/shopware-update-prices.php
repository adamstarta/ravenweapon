<?php
/**
 * Shopware Price Updater
 * Updates product prices with correct B2B EUR prices
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
    'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg',
    'json_input' => __DIR__ . '/snigel-merged-products.json',
    'price_markup' => 40, // 40% markup from B2B to selling price
    'eur_to_chf_rate' => 0.94, // EUR to CHF conversion
];

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║       SHOPWARE PRICE UPDATER FOR SNIGEL PRODUCTS           ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Load products
$products = json_decode(file_get_contents($config['json_input']), true);
$withPrices = array_filter($products, fn($p) => !empty($p['has_b2b_price']) && !empty($p['b2b_price_eur']));
echo "Loaded " . count($products) . " products (" . count($withPrices) . " with B2B prices)\n\n";

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
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $GLOBALS['token_data']['token'] = $data['access_token'] ?? null;
    $GLOBALS['token_data']['expires_at'] = time() + ($data['expires_in'] ?? 600);

    return $GLOBALS['token_data']['token'];
}

function apiRequest($method, $endpoint, $data, $config) {
    $token = getAccessToken($config);
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
    ]);

    if ($data && in_array($method, ['POST', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

function findProduct($productNumber, $config) {
    $result = apiRequest('POST', '/search/product', [
        'filter' => [['type' => 'equals', 'field' => 'productNumber', 'value' => $productNumber]],
        'includes' => ['product' => ['id', 'productNumber', 'name']]
    ], $config);

    return $result['body']['data'][0] ?? null;
}

// Get EUR and CHF currency IDs
echo "Getting currency IDs...\n";
$result = apiRequest('POST', '/search/currency', [
    'filter' => [
        ['type' => 'equalsAny', 'field' => 'isoCode', 'value' => ['EUR', 'CHF']]
    ]
], $config);

$eurCurrencyId = null;
$chfCurrencyId = null;

foreach ($result['body']['data'] ?? [] as $currency) {
    $attrs = $currency['attributes'] ?? $currency;
    if (($attrs['isoCode'] ?? '') === 'EUR') {
        $eurCurrencyId = $currency['id'];
    } elseif (($attrs['isoCode'] ?? '') === 'CHF') {
        $chfCurrencyId = $currency['id'];
    }
}

echo "  EUR Currency ID: " . ($eurCurrencyId ?? 'NOT FOUND') . "\n";
echo "  CHF Currency ID: " . ($chfCurrencyId ?? 'NOT FOUND') . "\n\n";

if (!$eurCurrencyId) {
    die("ERROR: EUR currency not found!\n");
}

// Get token
echo "Authenticating...\n";
$token = getAccessToken($config);
if (!$token) {
    die("Failed to authenticate\n");
}
echo "✓ Authenticated\n\n";

// Process products with prices
$count = 0;
$total = count($withPrices);
$updated = 0;
$notFound = 0;
$errors = 0;

foreach ($withPrices as $product) {
    $count++;
    $productNumber = !empty($product['article_no']) ? $product['article_no'] : 'SN-' . $product['slug'];

    echo "[$count/$total] " . substr($product['name'], 0, 40) . "... ";

    // Find product in Shopware
    $shopwareProduct = findProduct($productNumber, $config);
    if (!$shopwareProduct) {
        echo "NOT FOUND\n";
        $notFound++;
        continue;
    }

    $productId = $shopwareProduct['id'];

    // Calculate prices
    $purchasePriceEUR = round($product['b2b_price_eur'], 2);

    // Use RRP if available, otherwise calculate with markup
    if (!empty($product['rrp_eur'])) {
        $sellingPriceEUR = round($product['rrp_eur'], 2);
    } else {
        $sellingPriceEUR = round($purchasePriceEUR * (1 + $config['price_markup'] / 100), 2);
    }

    // Convert to CHF
    $sellingPriceCHF = round($sellingPriceEUR / $config['eur_to_chf_rate'], 2);

    // Build price array
    $prices = [
        [
            'currencyId' => $eurCurrencyId,
            'gross' => $sellingPriceEUR,
            'net' => round($sellingPriceEUR / 1.077, 2), // Swiss VAT 7.7%
            'linked' => false,
        ]
    ];

    // Add CHF price if currency exists
    if ($chfCurrencyId) {
        $prices[] = [
            'currencyId' => $chfCurrencyId,
            'gross' => $sellingPriceCHF,
            'net' => round($sellingPriceCHF / 1.077, 2),
            'linked' => false,
        ];
    }

    // Update product
    $updateData = [
        'price' => $prices,
        'purchasePrices' => [[
            'currencyId' => $eurCurrencyId,
            'gross' => $purchasePriceEUR,
            'net' => round($purchasePriceEUR / 1.077, 2),
            'linked' => false,
        ]],
    ];

    $result = apiRequest('PATCH', "/product/$productId", $updateData, $config);

    if ($result['code'] === 204 || $result['code'] === 200) {
        echo "€$sellingPriceEUR / CHF $sellingPriceCHF\n";
        $updated++;
    } else {
        echo "ERROR (" . $result['code'] . ")\n";
        $errors++;
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                    UPDATE COMPLETE                         ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
echo "║  Products updated:                " . str_pad($updated, 23) . "║\n";
echo "║  Products not found:              " . str_pad($notFound, 23) . "║\n";
echo "║  Errors:                          " . str_pad($errors, 23) . "║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";
