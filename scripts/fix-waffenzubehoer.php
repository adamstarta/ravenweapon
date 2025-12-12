<?php
/**
 * Fix Waffenzubehör category - assign accessory products to correct category
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

function apiPatch($baseUrl, $token, $endpoint, $data) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

echo "═══════════════════════════════════════════════════════════\n";
echo "       FIX WAFFENZUBEHÖR CATEGORY                           \n";
echo "═══════════════════════════════════════════════════════════\n\n";

$token = getToken($NEW_URL);
if (!$token) {
    die("❌ Failed to get token\n");
}

// Find the correct Waffenzubehör category (the one linked to navigation)
echo "📂 Finding Waffenzubehör category...\n";
$cats = apiGet($NEW_URL, $token, 'category?limit=100');

$waffenCatId = null;
foreach ($cats['data'] ?? [] as $cat) {
    $name = $cat['attributes']['name'] ?? '';
    // Use the first Waffenzubehör (should be the one in navigation)
    if ($name === 'Waffenzubehör' && !$waffenCatId) {
        $waffenCatId = $cat['id'];
        echo "   Found: {$waffenCatId}\n";
        break;
    }
}

if (!$waffenCatId) {
    die("❌ Waffenzubehör category not found\n");
}

// Get all products that should be in Waffenzubehör
// These are: Zerotech (ZRT-), Magpul (MGP-), Aimpact (AIM-), Acheron (ACH-)
echo "\n📦 Finding accessory products...\n";

$products = [];
$page = 1;
$limit = 100;

do {
    $response = apiPost($NEW_URL, $token, 'search/product', [
        'limit' => $limit,
        'page' => $page,
        'total-count-mode' => 1
    ]);

    if (!empty($response['data']['data'])) {
        foreach ($response['data']['data'] as $p) {
            $sku = $p['productNumber'] ?? $p['attributes']['productNumber'] ?? '';
            $name = $p['name'] ?? $p['attributes']['name'] ?? '';
            $id = $p['id'];

            // Check if this is an accessory
            if (preg_match('/^(ZRT-|MGP-|AIM-|ACH-)/', $sku)) {
                $products[] = [
                    'id' => $id,
                    'sku' => $sku,
                    'name' => $name
                ];
            }
        }
    }

    $total = $response['data']['meta']['total'] ?? 0;
    $page++;
} while (count($products) < 200 && $page <= 5);

echo "   Found " . count($products) . " accessory products\n\n";

// Assign products to Waffenzubehör category
echo "🔧 Assigning products to Waffenzubehör...\n\n";
$updated = 0;
$errors = 0;

foreach ($products as $product) {
    // Add category to product
    $result = apiPatch($NEW_URL, $token, 'product/' . $product['id'], [
        'categories' => [
            ['id' => $waffenCatId]
        ]
    ]);

    if ($result['code'] >= 200 && $result['code'] < 300 || $result['code'] == 204) {
        $updated++;
        echo "   ✅ {$product['sku']}: {$product['name']}\n";
    } else {
        $errors++;
        echo "   ❌ {$product['sku']}: " . json_encode($result['data']) . "\n";
    }

    if ($updated % 20 == 0) {
        usleep(300000);
    }
}

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "                        DONE!                               \n";
echo "═══════════════════════════════════════════════════════════\n";
echo "   ✅ Updated: {$updated} products\n";
echo "   ❌ Errors: {$errors}\n";
echo "═══════════════════════════════════════════════════════════\n";
