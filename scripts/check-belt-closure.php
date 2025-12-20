<?php
/**
 * Check Belt closure pack product media
 */

$ch = curl_init('https://ortak.ch/api/oauth/token');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['grant_type' => 'client_credentials', 'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG', 'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'])]);
$token = json_decode(curl_exec($ch), true)['access_token'];
curl_close($ch);

echo "✓ Got token\n\n";

// Search for Belt closure pack products
$ch = curl_init('https://ortak.ch/api/search/product');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode([
    'limit' => 10,
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'Belt closure pack']
    ],
    'associations' => [
        'media' => [],
        'cover' => []
    ]
])]);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);

echo "Found " . count($result['data']) . " products:\n\n";

foreach ($result['data'] as $p) {
    $attrs = $p['attributes'] ?? $p;
    $name = $attrs['name'] ?? 'Unknown';
    $productNumber = $attrs['productNumber'] ?? 'N/A';
    $coverId = $attrs['coverId'] ?? 'NULL';
    $price = $attrs['price'][0]['gross'] ?? 'N/A';

    echo "═══════════════════════════════════════════════════════════\n";
    echo "  Name: $name\n";
    echo "  Product #: $productNumber\n";
    echo "  Price: CHF $price\n";
    echo "  Product ID: " . $p['id'] . "\n";
    echo "  Cover ID: " . ($coverId ?: 'NULL') . "\n";

    // Check media associations
    if (!empty($p['relationships']['media']['data'])) {
        echo "  Media count: " . count($p['relationships']['media']['data']) . "\n";
    } else {
        echo "  Media: NO MEDIA ASSOCIATIONS\n";
    }
    echo "\n";
}
