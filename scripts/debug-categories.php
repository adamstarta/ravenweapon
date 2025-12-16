<?php
/**
 * Debug categories - fetch each by ID to get full data
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

// Get token
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/oauth/token',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'grant_type' => 'password',
        'client_id' => 'administration',
        'username' => $config['api_user'],
        'password' => $config['api_password'],
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);
$token = $data['access_token'] ?? null;

// Category IDs found in previous run - children of Zielhilfen
$categoryIds = [
    '461b4f23bcd74823abbb55550af5008c',
    '499800fd06224f779acc5c4ac243d2e8',
    'b47d4447067c45aaa0aed7081ac465c4',
    'c34b9bb9a7a6473d928dd9c7c0f10f8b',
];

echo "=== Fetching each category by ID ===\n\n";

foreach ($categoryIds as $catId) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . '/api/category/' . $catId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $cat = json_decode($response, true);

    echo "ID: $catId\n";
    echo "  HTTP: $httpCode\n";

    if ($httpCode == 200 && !empty($cat['data'])) {
        $name = $cat['data']['translated']['name'] ?? $cat['data']['name'] ?? 'UNNAMED';
        echo "  Name: $name\n";
        echo "  Active: " . ($cat['data']['active'] ? 'YES' : 'NO') . "\n";
    } else {
        echo "  Error fetching category\n";
        if (!empty($cat['errors'])) {
            print_r($cat['errors']);
        }
    }
    echo "\n";
}

// Also check products in Ausrüstung to see which ones should be moved
echo "=== Products in Ausrüstung category ===\n\n";
$ausruestungId = '019b0857613474e6a799cfa07d143c76';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/search/product',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'filter' => [
            ['type' => 'equals', 'field' => 'categories.id', 'value' => $ausruestungId]
        ],
        'limit' => 50
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);

echo "Total products in Ausrüstung: " . ($result['total'] ?? 0) . "\n\n";

if (!empty($result['data'])) {
    foreach ($result['data'] as $product) {
        $name = $product['translated']['name'] ?? $product['name'] ?? 'UNNAMED';
        $price = 'N/A';
        if (!empty($product['price'][0]['gross'])) {
            $price = number_format($product['price'][0]['gross'], 2) . ' CHF';
        }
        echo "  - $name ($price)\n";
        echo "    ID: {$product['id']}\n";

        // Check if this is a spotting scope / spektiv
        if (stripos($name, 'scope') !== false ||
            stripos($name, 'spektiv') !== false ||
            stripos($name, 'zerotech') !== false) {
            echo "    >>> SHOULD BE IN SPEKTIVE! <<<\n";
        }
        echo "\n";
    }
}
