<?php
/**
 * Fix shipping method prices - add CHF prices for all shipping methods
 * ERROR: DeliveryCalculator::getPriceForTaxState(): $price is null
 */

$API_URL = 'https://ortak.ch';

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
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

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$token = getToken($API_URL);
if (!$token) {
    die("Failed to get token\n");
}

echo "Token obtained\n\n";

// First get all currencies to find CHF
echo "=== CURRENCIES ===\n";
$currencies = apiRequest($API_URL, $token, 'POST', 'search/currency', [
    'limit' => 10
]);

$chfCurrencyId = null;
foreach ($currencies['data'] ?? [] as $currency) {
    $isoCode = $currency['isoCode'] ?? $currency['attributes']['isoCode'] ?? '';
    $id = $currency['id'];
    echo "Currency: $isoCode (ID: $id)\n";
    if ($isoCode === 'CHF') {
        $chfCurrencyId = $id;
    }
}

if (!$chfCurrencyId) {
    die("\nERROR: CHF currency not found!\n");
}

echo "\nCHF Currency ID: $chfCurrencyId\n\n";

// Get all shipping methods with prices
echo "=== SHIPPING METHODS ===\n";
$shippingMethods = apiRequest($API_URL, $token, 'POST', 'search/shipping-method', [
    'limit' => 50,
    'associations' => [
        'prices' => []
    ]
]);

foreach ($shippingMethods['data'] ?? [] as $method) {
    $name = $method['name'] ?? $method['attributes']['name'] ?? 'Unknown';
    $id = $method['id'];
    $active = $method['active'] ?? $method['attributes']['active'] ?? false;

    echo "\nShipping Method: $name\n";
    echo "  ID: $id\n";
    echo "  Active: " . ($active ? 'Yes' : 'No') . "\n";

    $prices = $method['prices'] ?? [];
    echo "  Prices: " . count($prices) . " configured\n";

    if (!empty($prices)) {
        foreach ($prices as $price) {
            $currencyId = $price['currencyId'] ?? 'unknown';
            $priceVal = $price['currencyPrice'] ?? [];
            echo "    - Currency: $currencyId\n";
            echo "      Price data: " . json_encode($priceVal) . "\n";
        }
    }
}

// Get shipping method prices separately
echo "\n\n=== SHIPPING METHOD PRICES TABLE ===\n";
$shippingPrices = apiRequest($API_URL, $token, 'POST', 'search/shipping-method-price', [
    'limit' => 100
]);

echo "Total shipping method prices: " . count($shippingPrices['data'] ?? []) . "\n\n";

foreach ($shippingPrices['data'] ?? [] as $price) {
    $shippingMethodId = $price['shippingMethodId'] ?? $price['attributes']['shippingMethodId'] ?? '';
    $currencyPrice = $price['currencyPrice'] ?? $price['attributes']['currencyPrice'] ?? [];
    $ruleId = $price['ruleId'] ?? $price['attributes']['ruleId'] ?? 'none';

    echo "Price Entry ID: " . $price['id'] . "\n";
    echo "  Shipping Method ID: $shippingMethodId\n";
    echo "  Rule ID: $ruleId\n";
    echo "  Currency Price: " . json_encode($currencyPrice) . "\n\n";
}

// Check if CHF prices exist
echo "=== CHECKING FOR CHF PRICES ===\n";
$hasCHFPrice = false;
foreach ($shippingPrices['data'] ?? [] as $price) {
    $currencyPrice = $price['currencyPrice'] ?? $price['attributes']['currencyPrice'] ?? [];
    if (isset($currencyPrice[$chfCurrencyId])) {
        echo "Found CHF price in price entry " . $price['id'] . "\n";
        $hasCHFPrice = true;
    }
}

if (!$hasCHFPrice) {
    echo "NO CHF PRICES FOUND! Need to add them.\n";

    // Update each shipping method price to include CHF
    echo "\n=== FIXING SHIPPING METHOD PRICES ===\n";

    foreach ($shippingPrices['data'] ?? [] as $price) {
        $priceId = $price['id'];
        $currencyPrice = $price['currencyPrice'] ?? $price['attributes']['currencyPrice'] ?? [];

        // Get an existing price to copy
        $existingPrice = null;
        foreach ($currencyPrice as $curId => $priceData) {
            $existingPrice = $priceData;
            break;
        }

        if ($existingPrice) {
            // Add CHF with same price structure
            $currencyPrice[$chfCurrencyId] = [
                'net' => $existingPrice['net'] ?? 0,
                'gross' => $existingPrice['gross'] ?? 0,
                'linked' => $existingPrice['linked'] ?? true,
                'currencyId' => $chfCurrencyId
            ];

            echo "Updating price $priceId with CHF: " . json_encode($currencyPrice[$chfCurrencyId]) . "\n";

            $result = apiRequest($API_URL, $token, 'PATCH', 'shipping-method-price/' . $priceId, [
                'currencyPrice' => $currencyPrice
            ]);

            if (isset($result['errors'])) {
                echo "  ERROR: " . json_encode($result['errors']) . "\n";
            } else {
                echo "  SUCCESS!\n";
            }
        }
    }
}

echo "\nDone!\n";
