<?php
/**
 * Compare Raven Swiss Rifle Prices
 * ravenweapon.ch vs ortak.ch
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

// Prices from shop.ravenweapon.ch (manual entry from web fetch)
$ravenweaponPrices = [
    ['name' => 'Raven Swiss, .223 Rem., FDE', 'caliber' => '.223', 'color' => 'FDE', 'price' => 3850.00],
    ['name' => 'Raven Swiss, .223 Rem., OD-Green', 'caliber' => '.223', 'color' => 'OD-Green', 'price' => 3850.00],
    ['name' => 'Raven Swiss, .223 Rem., Sniper Grey', 'caliber' => '.223', 'color' => 'Sniper Grey', 'price' => 3850.00],
    ['name' => 'Raven Swiss, .223 Rem., Northern Light', 'caliber' => '.223', 'color' => 'Northern Light', 'price' => 3850.00],
    ['name' => 'Raven Swiss, .223 Rem., black', 'caliber' => '.223', 'color' => 'black', 'price' => 3850.00],
    ['name' => 'Raven Swiss, .22 LR, black', 'caliber' => '.22 LR', 'color' => 'black', 'price' => 3850.00],
    ['name' => 'Raven Swiss, 300AAC Blackout, black', 'caliber' => '300 AAC', 'color' => 'black', 'price' => 3950.00],
    ['name' => 'Raven Swiss, 300AAC Blackout, FDE', 'caliber' => '300 AAC', 'color' => 'FDE', 'price' => 3950.00],
    ['name' => 'Raven Swiss, 300AAC Blackout, OD-Green', 'caliber' => '300 AAC', 'color' => 'OD-Green', 'price' => 3950.00],
    ['name' => 'Raven Swiss, 300AAC Blackout, Sniper Grey', 'caliber' => '300 AAC', 'color' => 'Sniper Grey', 'price' => 3950.00],
    ['name' => 'Raven Swiss, 300AAC Blackout, Northern Light', 'caliber' => '300 AAC', 'color' => 'Northern Light', 'price' => 3950.00],
    ['name' => 'Raven Swiss, 7.62x39, black', 'caliber' => '7.62x39', 'color' => 'black', 'price' => 3950.00],
];

echo "╔══════════════════════════════════════════════════════════════════════════════════════════════════╗\n";
echo "║           RAVEN SWISS RIFLE PRICE COMPARISON                                                    ║\n";
echo "║           shop.ravenweapon.ch vs ortak.ch                                                       ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════════════════════════╝\n\n";

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "✓ Got API token for ortak.ch\n\n";

// Fetch ALL products from ortak.ch and find RAVEN rifles
echo "Fetching products from ortak.ch...\n";
$ortakProducts = [];
$page = 1;
$limit = 100;

do {
    $response = apiRequest($baseUrl, $token, '/search/product', [
        'limit' => $limit,
        'page' => $page
    ]);

    if (isset($response['data'])) {
        foreach ($response['data'] as $prod) {
            $attrs = $prod['attributes'] ?? $prod;
            $sku = $attrs['productNumber'] ?? '';
            $name = $attrs['translated']['name'] ?? $attrs['name'] ?? '';

            // Check if it's a RAVEN rifle
            if (stripos($sku, 'RAVEN') !== false ||
                (stripos($name, 'RAVEN') !== false && stripos($name, 'Raven Swiss') === false &&
                 (stripos($name, '.223') !== false || stripos($name, '300 AAC') !== false ||
                  stripos($name, '7.62') !== false || stripos($name, '.22 LR') !== false || stripos($name, '9mm') !== false))) {

                $grossPrice = 0;
                if (isset($attrs['price']) && is_array($attrs['price'])) {
                    foreach ($attrs['price'] as $priceData) {
                        $grossPrice = floatval($priceData['gross'] ?? 0);
                        break;
                    }
                }

                $ortakProducts[$sku] = [
                    'id' => $prod['id'],
                    'sku' => $sku,
                    'name' => $name,
                    'price' => $grossPrice,
                ];
            }
        }
    }

    $page++;
} while (count($response['data'] ?? []) === $limit);

echo "Found " . count($ortakProducts) . " RAVEN rifle products on ortak.ch\n\n";

// Display ortak.ch RAVEN products
echo str_repeat('═', 95) . "\n";
echo "ORTAK.CH - RAVEN RIFLES:\n";
echo str_repeat('═', 95) . "\n\n";

echo sprintf("%-20s %-55s %15s\n", "SKU", "Product Name", "Price CHF");
echo str_repeat('─', 95) . "\n";

foreach ($ortakProducts as $prod) {
    echo sprintf("%-20s %-55s %15.2f\n", $prod['sku'], mb_substr($prod['name'], 0, 53), $prod['price']);
}

echo "\n";

// Display ravenweapon.ch prices
echo str_repeat('═', 95) . "\n";
echo "SHOP.RAVENWEAPON.CH - RAVEN SWISS RIFLES:\n";
echo str_repeat('═', 95) . "\n\n";

echo sprintf("%-55s %15s\n", "Product Name", "Price CHF");
echo str_repeat('─', 75) . "\n";

foreach ($ravenweaponPrices as $prod) {
    echo sprintf("%-55s %15.2f\n", $prod['name'], $prod['price']);
}

echo "\n";

// Price comparison summary
echo str_repeat('═', 95) . "\n";
echo "PRICE COMPARISON SUMMARY:\n";
echo str_repeat('═', 95) . "\n\n";

// Group by caliber for comparison
$calibers = ['.223', '.22 LR', '300 AAC', '7.62x39', '9mm'];

foreach ($calibers as $cal) {
    $rwPrice = null;
    $ortakPrice = null;

    // Find ravenweapon.ch price for this caliber
    foreach ($ravenweaponPrices as $p) {
        if ($p['caliber'] === $cal) {
            $rwPrice = $p['price'];
            break;
        }
    }

    // Find ortak.ch price for this caliber
    foreach ($ortakProducts as $p) {
        if (stripos($p['name'], $cal) !== false ||
            ($cal === '.223' && stripos($p['name'], '.223') !== false) ||
            ($cal === '9mm' && stripos($p['name'], '9mm') !== false)) {
            $ortakPrice = $p['price'];
            break;
        }
    }

    if ($rwPrice || $ortakPrice) {
        $rwStr = $rwPrice ? number_format($rwPrice, 2) : 'N/A';
        $ortakStr = $ortakPrice ? number_format($ortakPrice, 2) : 'N/A';
        $diff = '';
        if ($rwPrice && $ortakPrice) {
            $diffVal = $ortakPrice - $rwPrice;
            $diff = ($diffVal >= 0 ? '+' : '') . number_format($diffVal, 2);
        }

        echo sprintf("%-15s  ravenweapon.ch: CHF %10s  |  ortak.ch: CHF %10s  |  Diff: %s\n",
            $cal . ' RAVEN', $rwStr, $ortakStr, $diff);
    }
}

echo "\n";
