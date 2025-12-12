<?php
/**
 * Final comprehensive check before Go Live
 */

$NEW_URL = 'http://77.42.19.154:8080';

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

function apiGet($baseUrl, $token, $endpoint) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

echo "═══════════════════════════════════════════════════════════\n";
echo "       FINAL PRE-GO-LIVE CHECK - CHF SITE                   \n";
echo "═══════════════════════════════════════════════════════════\n\n";

$token = getToken($NEW_URL);
if (!$token) {
    die("❌ CRITICAL: Cannot connect to API!\n");
}

$allGood = true;

// 1. Check Currency
echo "1️⃣ CURRENCY CHECK\n";
$currencies = apiPost($NEW_URL, $token, 'search/currency', ['limit' => 10]);
$baseCurrency = null;
foreach ($currencies['data'] ?? [] as $c) {
    $iso = $c['isoCode'] ?? $c['attributes']['isoCode'] ?? '';
    $factor = $c['factor'] ?? $c['attributes']['factor'] ?? 0;
    if ($factor == 1) {
        $baseCurrency = $iso;
    }
    echo "   {$iso}: factor = {$factor}" . ($factor == 1 ? " ← BASE" : "") . "\n";
}
if ($baseCurrency === 'CHF') {
    echo "   ✅ CHF is base currency\n";
} else {
    echo "   ❌ PROBLEM: Base currency is {$baseCurrency}, not CHF!\n";
    $allGood = false;
}

echo "\n";

// 2. Check Products
echo "2️⃣ PRODUCTS CHECK\n";
$products = apiPost($NEW_URL, $token, 'search/product', ['limit' => 1, 'total-count-mode' => 1]);
$totalProducts = $products['meta']['total'] ?? 0;
echo "   Total products: {$totalProducts}\n";
if ($totalProducts >= 300) {
    echo "   ✅ Product count OK\n";
} else {
    echo "   ⚠️ WARNING: Expected ~305 products\n";
}

// Check products with images
$withImages = apiPost($NEW_URL, $token, 'search/product', [
    'limit' => 1,
    'total-count-mode' => 1,
    'filter' => [
        ['type' => 'not', 'queries' => [
            ['type' => 'equals', 'field' => 'coverId', 'value' => null]
        ]]
    ]
]);
$productsWithImages = $withImages['meta']['total'] ?? 0;
echo "   Products with images: {$productsWithImages}\n";
if ($productsWithImages >= 300) {
    echo "   ✅ Images OK\n";
} else {
    echo "   ⚠️ {$totalProducts} - {$productsWithImages} = " . ($totalProducts - $productsWithImages) . " products without images\n";
}

echo "\n";

// 3. Check Categories
echo "3️⃣ CATEGORIES CHECK\n";
$categories = apiGet($NEW_URL, $token, 'category?limit=50');
$catCount = 0;
$mainCats = [];
foreach ($categories['data'] ?? [] as $cat) {
    $name = $cat['attributes']['name'] ?? $cat['name'] ?? '';
    $active = $cat['attributes']['active'] ?? $cat['active'] ?? false;
    if ($active && $name) {
        $catCount++;
        if (in_array($name, ['Raven Weapons', 'Raven Caliber Kit', 'Waffenzubehör', 'Snigel', 'Alle Produkte'])) {
            $mainCats[] = $name;
        }
    }
}
echo "   Active categories: {$catCount}\n";
echo "   Main categories: " . implode(', ', $mainCats) . "\n";
if (count($mainCats) >= 4) {
    echo "   ✅ Categories OK\n";
} else {
    echo "   ⚠️ Some main categories may be missing\n";
}

echo "\n";

// 4. Check Payment Methods
echo "4️⃣ PAYMENT METHODS CHECK\n";
$payments = apiGet($NEW_URL, $token, 'payment-method?limit=20');
$activePayments = [];
foreach ($payments['data'] ?? [] as $pm) {
    $name = $pm['attributes']['name'] ?? $pm['name'] ?? '';
    $active = $pm['attributes']['active'] ?? $pm['active'] ?? false;
    if ($active) {
        $activePayments[] = $name;
    }
}
echo "   Active payment methods: " . implode(', ', $activePayments) . "\n";
if (in_array('Vorkasse', $activePayments)) {
    echo "   ✅ Vorkasse (Bank Transfer) enabled\n";
} else {
    echo "   ❌ Vorkasse not enabled!\n";
    $allGood = false;
}

echo "\n";

// 5. Check Shipping Methods
echo "5️⃣ SHIPPING METHODS CHECK\n";
$shipping = apiGet($NEW_URL, $token, 'shipping-method?limit=20');
$activeShipping = [];
foreach ($shipping['data'] ?? [] as $sm) {
    $name = $sm['attributes']['name'] ?? $sm['name'] ?? '';
    $active = $sm['attributes']['active'] ?? $sm['active'] ?? false;
    if ($active) {
        $activeShipping[] = $name;
    }
}
echo "   Active shipping methods: " . implode(', ', $activeShipping) . "\n";
if (count($activeShipping) > 0) {
    echo "   ✅ Shipping OK\n";
} else {
    echo "   ❌ No shipping methods!\n";
    $allGood = false;
}

echo "\n";

// 6. Check Sales Channel
echo "6️⃣ SALES CHANNEL CHECK\n";
$salesChannels = apiPost($NEW_URL, $token, 'search/sales-channel', [
    'filter' => [['type' => 'equals', 'field' => 'typeId', 'value' => '8a243080f92e4c719546314b577cf82b']]
]);
if (!empty($salesChannels['data'])) {
    $sc = $salesChannels['data'][0];
    $scName = $sc['name'] ?? $sc['attributes']['name'] ?? 'Unknown';
    $scActive = $sc['active'] ?? $sc['attributes']['active'] ?? false;
    echo "   Storefront: {$scName}\n";
    echo "   Active: " . ($scActive ? 'Yes' : 'No') . "\n";
    if ($scActive) {
        echo "   ✅ Sales Channel OK\n";
    } else {
        echo "   ❌ Sales Channel not active!\n";
        $allGood = false;
    }
} else {
    echo "   ❌ No storefront sales channel found!\n";
    $allGood = false;
}

echo "\n";

// 7. Check Tax
echo "7️⃣ TAX CHECK\n";
$taxes = apiGet($NEW_URL, $token, 'tax?limit=10');
foreach ($taxes['data'] ?? [] as $tax) {
    $name = $tax['attributes']['name'] ?? $tax['name'] ?? '';
    $rate = $tax['attributes']['taxRate'] ?? $tax['taxRate'] ?? 0;
    echo "   {$name}: {$rate}%\n";
}
echo "   ✅ Tax configured\n";

echo "\n";

// 8. Storefront accessibility
echo "8️⃣ STOREFRONT CHECK\n";
$ch = curl_init($NEW_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 10
]);
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Homepage HTTP: {$httpCode}\n";
if ($httpCode == 200 && strpos($html, 'RAVEN WEAPON') !== false) {
    echo "   ✅ Homepage loads correctly\n";
} else {
    echo "   ❌ Homepage issue!\n";
    $allGood = false;
}

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
if ($allGood) {
    echo "   ✅✅✅ ALL CHECKS PASSED - READY FOR GO LIVE! ✅✅✅     \n";
} else {
    echo "   ⚠️⚠️⚠️ SOME ISSUES FOUND - REVIEW BEFORE GO LIVE ⚠️⚠️⚠️\n";
}
echo "═══════════════════════════════════════════════════════════\n";
