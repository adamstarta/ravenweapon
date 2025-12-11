<?php
/**
 * Update prices for the 6 missing products
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
    'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg',
    'eur_to_chf_rate' => 0.94,
];

$missingProducts = json_decode(file_get_contents(__DIR__ . '/snigel-b2b-data/missing-products-prices.json'), true);
$mergedProducts = json_decode(file_get_contents(__DIR__ . '/snigel-merged-products.json'), true);

// Get token
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
$token = json_decode($response, true)['access_token'];

// Get currency IDs
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/search/currency',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'filter' => [['type' => 'equalsAny', 'field' => 'isoCode', 'value' => ['EUR', 'CHF']]]
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$currencies = json_decode($response, true)['data'];

$eurId = null;
$chfId = null;
foreach ($currencies as $c) {
    $attrs = $c['attributes'] ?? $c;
    if (($attrs['isoCode'] ?? '') === 'EUR') $eurId = $c['id'];
    if (($attrs['isoCode'] ?? '') === 'CHF') $chfId = $c['id'];
}

echo "\nUpdating 6 missing products...\n\n";

$updated = 0;
foreach ($missingProducts as $product) {
    // Find in merged data to get article_no
    $merged = null;
    foreach ($mergedProducts as $p) {
        if ($p['slug'] === $product['slug']) {
            $merged = $p;
            break;
        }
    }
    $productNumber = !empty($merged['article_no']) ? $merged['article_no'] : 'SN-' . $product['slug'];

    echo $product['name'] . "... ";

    // Find product in Shopware
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . '/api/search/product',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'filter' => [['type' => 'equals', 'field' => 'productNumber', 'value' => $productNumber]]
        ]),
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);

    if (empty($result['data'])) {
        echo "NOT FOUND\n";
        continue;
    }

    $productId = $result['data'][0]['id'];
    $b2bPrice = $product['b2b_price_eur'];
    $sellingEUR = round($b2bPrice * 1.5, 2);
    $sellingCHF = round($sellingEUR / $config['eur_to_chf_rate'], 2);

    // Update price
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . '/api/product/' . $productId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'price' => [
                ['currencyId' => $eurId, 'gross' => $sellingEUR, 'net' => round($sellingEUR / 1.077, 2), 'linked' => false],
                ['currencyId' => $chfId, 'gross' => $sellingCHF, 'net' => round($sellingCHF / 1.077, 2), 'linked' => false],
            ],
            'purchasePrices' => [
                ['currencyId' => $eurId, 'gross' => $b2bPrice, 'net' => round($b2bPrice / 1.077, 2), 'linked' => false],
            ],
        ]),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 204 || $httpCode === 200) {
        echo "€$sellingEUR / CHF $sellingCHF\n";
        $updated++;
    } else {
        echo "ERROR ($httpCode)\n";
    }
}

echo "\n✓ Updated $updated products\n\n";
