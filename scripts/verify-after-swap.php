<?php
/**
 * Verify CHF site after swap (now on port 80)
 */

$MAIN_URL = 'http://77.42.19.154'; // Now the CHF site on port 80

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
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
    return json_decode($response, true)['access_token'] ?? null;
}

function apiPost($baseUrl, $token, $endpoint, $data) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

echo "═══════════════════════════════════════════════════════════\n";
echo "     VERIFICATION AFTER SWAP - CHF SITE ON PORT 80          \n";
echo "═══════════════════════════════════════════════════════════\n\n";

$token = getToken($MAIN_URL);
if (!$token) {
    die("❌ Cannot connect to API on port 80!\n");
}
echo "✅ API connection OK\n\n";

// 1. Check Currency - THIS IS THE MOST IMPORTANT CHECK
echo "1️⃣ CURRENCY CHECK (CRITICAL)\n";
$currencies = apiPost($MAIN_URL, $token, 'search/currency', ['limit' => 10]);
$baseCurrency = null;
$chfFactor = null;
$eurFactor = null;

foreach ($currencies['data'] ?? [] as $c) {
    $iso = $c['isoCode'] ?? $c['attributes']['isoCode'] ?? '';
    $factor = $c['factor'] ?? $c['attributes']['factor'] ?? 0;

    if ($factor == 1) {
        $baseCurrency = $iso;
    }
    if ($iso === 'CHF') {
        $chfFactor = $factor;
    }
    if ($iso === 'EUR') {
        $eurFactor = $factor;
    }
}

echo "   Base Currency: {$baseCurrency}\n";
echo "   CHF Factor: {$chfFactor}\n";
echo "   EUR Factor: {$eurFactor}\n";

if ($baseCurrency === 'CHF' && $chfFactor == 1) {
    echo "   ✅✅✅ CHF IS BASE CURRENCY (factor = 1) ✅✅✅\n";
} else {
    echo "   ❌❌❌ PROBLEM: CHF is NOT base currency! ❌❌❌\n";
}

echo "\n";

// 2. Check Products
echo "2️⃣ PRODUCTS CHECK\n";
$products = apiPost($MAIN_URL, $token, 'search/product', ['limit' => 1, 'total-count-mode' => 1]);
$totalProducts = $products['meta']['total'] ?? 0;
echo "   Total products: {$totalProducts}\n";

// Get a sample product price
$sampleProduct = apiPost($MAIN_URL, $token, 'search/product', [
    'limit' => 1,
    'filter' => [
        ['type' => 'contains', 'field' => 'productNumber', 'value' => 'RAVEN']
    ]
]);

if (!empty($sampleProduct['data'])) {
    $p = $sampleProduct['data'][0];
    $name = $p['name'] ?? $p['attributes']['name'] ?? '';
    $sku = $p['productNumber'] ?? $p['attributes']['productNumber'] ?? '';
    $priceData = $p['price'] ?? $p['attributes']['price'] ?? [];
    $price = $priceData[0]['gross'] ?? 0;
    $currencyId = $priceData[0]['currencyId'] ?? '';

    echo "   Sample: {$sku} - {$name}\n";
    echo "   Price: CHF {$price}\n";
}

echo "\n";

// 3. Check Payment Method
echo "3️⃣ PAYMENT CHECK\n";
$payments = apiPost($MAIN_URL, $token, 'search/payment-method', [
    'filter' => [['type' => 'equals', 'field' => 'active', 'value' => true]]
]);

foreach ($payments['data'] ?? [] as $pm) {
    $name = $pm['name'] ?? $pm['attributes']['name'] ?? '';
    echo "   Active: {$name}\n";
}

echo "\n";

// 4. Storefront Check
echo "4️⃣ STOREFRONT CHECK\n";
$ch = curl_init($MAIN_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 10
]);
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Status: {$httpCode}\n";
if ($httpCode == 200) {
    if (strpos($html, 'RAVEN WEAPON') !== false) {
        echo "   ✅ Homepage shows RAVEN WEAPON\n";
    }
    if (strpos($html, 'CHF') !== false) {
        echo "   ✅ Homepage shows CHF prices\n";
    }
}

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "   URL: http://77.42.19.154 (port 80)                       \n";
echo "   URL: https://ortak.ch (after DNS propagation)            \n";
echo "═══════════════════════════════════════════════════════════\n";
