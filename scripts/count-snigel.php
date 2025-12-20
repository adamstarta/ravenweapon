<?php
$ch = curl_init('https://ortak.ch/api/oauth/token');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['grant_type' => 'client_credentials', 'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG', 'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'])]);
$token = json_decode(curl_exec($ch), true)['access_token'];
curl_close($ch);

// Get all manufacturers with their names (include translations)
$ch = curl_init('https://ortak.ch/api/search/product-manufacturer');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode([
    'limit' => 50,
    'includes' => [
        'product_manufacturer' => ['id', 'name', 'translated']
    ]
])]);
$mfrResult = json_decode(curl_exec($ch), true);
curl_close($ch);

$manufacturers = [];
echo "All manufacturers:\n";

foreach ($mfrResult['data'] as $m) {
    // Handle JSON:API format (attributes) or flat format
    $attrs = $m['attributes'] ?? $m;
    $name = $attrs['name'] ?? $attrs['translated']['name'] ?? 'Unknown';
    $manufacturers[$m['id']] = $name;
    echo "  - " . $m['id'] . " => " . $name . "\n";
}

// Find Snigel manufacturer ID
$snigelMfrId = null;
foreach ($manufacturers as $id => $name) {
    if (stripos($name, 'Snigel') !== false) {
        $snigelMfrId = $id;
        echo "\n✓ Found Snigel manufacturer: $id\n";
        break;
    }
}

// Get Snigel products directly using manufacturer ID filter
if ($snigelMfrId) {
    $ch = curl_init('https://ortak.ch/api/search/product');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode([
        'limit' => 500,
        'filter' => [
            ['type' => 'equals', 'field' => 'manufacturerId', 'value' => $snigelMfrId],
            ['type' => 'equals', 'field' => 'parentId', 'value' => null]
        ]
    ])]);
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);

    $snigelProducts = $result['data'] ?? [];
    $total = $result['total'] ?? count($snigelProducts);
    echo "\nTotal Snigel main products: " . $total . " (returned: " . count($snigelProducts) . ")\n\n";
} else {
    echo "\n✗ Snigel manufacturer not found!\n";
    $snigelProducts = [];
}

// Check for potential duplicates by name
$names = [];
$duplicates = [];
foreach ($snigelProducts as $p) {
    $name = strtolower(trim($p['name'] ?? ''));
    if (isset($names[$name])) {
        $duplicates[] = ['name' => $p['name'], 'pn' => $p['productNumber']];
    } else {
        $names[$name] = $p['productNumber'];
    }
}

if (count($duplicates) > 0) {
    echo "Potential duplicates found:\n";
    foreach ($duplicates as $d) {
        echo "  - " . $d['name'] . " (" . $d['pn'] . ")\n";
    }
} else {
    echo "No duplicates found by name.\n";
}

// List the 2 newest products
echo "\nNewest Snigel products (last 5):\n";
$recent = array_slice($snigelProducts, -5);
foreach ($recent as $p) {
    echo "  - " . $p['productNumber'] . ": " . $p['name'] . "\n";
}
