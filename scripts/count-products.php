<?php
/**
 * Count products in both installations
 */

$OLD_URL = 'https://ortak.ch';
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

function countProducts($baseUrl, $token) {
    // Use search endpoint with total-count-mode
    $ch = curl_init($baseUrl . '/api/search/product');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'limit' => 1,
            'total-count-mode' => 1
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['meta']['total'] ?? $data['total'] ?? 0;
}

function getProductsByCategory($baseUrl, $token) {
    $ch = curl_init($baseUrl . '/api/search/product');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'limit' => 500,
            'includes' => [
                'product' => ['id', 'productNumber', 'name']
            ]
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['data'] ?? [];
}

echo "═══════════════════════════════════════════════════════════\n";
echo "              PRODUCT COUNT COMPARISON                      \n";
echo "═══════════════════════════════════════════════════════════\n\n";

$oldToken = getToken($OLD_URL);
$newToken = getToken($NEW_URL);

$oldCount = 0;
$newCount = 0;

if ($oldToken) {
    $oldCount = countProducts($OLD_URL, $oldToken);
    echo "📦 OLD site (ortak.ch):           {$oldCount} products\n";

    // Get sample products
    $oldProducts = getProductsByCategory($OLD_URL, $oldToken);
    echo "   Sample products:\n";
    $count = 0;
    foreach ($oldProducts as $p) {
        $name = $p['name'] ?? $p['attributes']['name'] ?? 'Unknown';
        $sku = $p['productNumber'] ?? $p['attributes']['productNumber'] ?? 'N/A';
        echo "   - {$sku}: {$name}\n";
        $count++;
        if ($count >= 10) {
            echo "   ... and " . (count($oldProducts) - 10) . " more\n";
            break;
        }
    }
} else {
    echo "❌ Could not connect to OLD site\n";
}

echo "\n";

if ($newToken) {
    $newCount = countProducts($NEW_URL, $newToken);
    echo "📦 NEW site (77.42.19.154:8080):  {$newCount} products\n";

    // Get sample products
    $newProducts = getProductsByCategory($NEW_URL, $newToken);
    echo "   Sample products:\n";
    $count = 0;
    foreach ($newProducts as $p) {
        $name = $p['name'] ?? $p['attributes']['name'] ?? 'Unknown';
        $sku = $p['productNumber'] ?? $p['attributes']['productNumber'] ?? 'N/A';
        echo "   - {$sku}: {$name}\n";
        $count++;
        if ($count >= 5) {
            echo "   ... and " . (count($newProducts) - 5) . " more\n";
            break;
        }
    }
} else {
    echo "❌ Could not connect to NEW site\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
$missing = $oldCount - $newCount;
if ($missing > 0) {
    echo "   ⚠️ Missing products to import: {$missing}\n";
} elseif ($missing < 0) {
    echo "   ℹ️ NEW has " . abs($missing) . " more products than OLD\n";
} else {
    echo "   ✅ Both sites have same number of products\n";
}
echo "═══════════════════════════════════════════════════════════\n";
