<?php
/**
 * Check which products have images vs which don't
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

echo "═══════════════════════════════════════════════════════════\n";
echo "           CHECK PRODUCT IMAGES IN NEW SITE                 \n";
echo "═══════════════════════════════════════════════════════════\n\n";

$token = getToken($NEW_URL);
if (!$token) {
    die("❌ Failed to get token\n");
}

$withImages = 0;
$withoutImages = 0;
$missingList = [];

$page = 1;
$limit = 100;

do {
    $response = apiPost($NEW_URL, $token, 'search/product', [
        'limit' => $limit,
        'page' => $page,
        'total-count-mode' => 1
    ]);

    if (!empty($response['data'])) {
        foreach ($response['data'] as $p) {
            $sku = $p['productNumber'] ?? $p['attributes']['productNumber'] ?? '';
            $name = $p['name'] ?? $p['attributes']['name'] ?? 'Unknown';
            $hasCover = !empty($p['coverId']) || !empty($p['attributes']['coverId']);

            if ($hasCover) {
                $withImages++;
            } else {
                $withoutImages++;
                $missingList[] = "$sku: $name";
            }
        }
    }

    $total = $response['meta']['total'] ?? 0;
    $page++;
} while (($withImages + $withoutImages) < $total && $page <= 10);

echo "📊 SUMMARY:\n";
echo "   ✅ Products WITH images: {$withImages}\n";
echo "   ❌ Products WITHOUT images: {$withoutImages}\n\n";

if ($withoutImages > 0) {
    echo "📋 Products missing images:\n";
    foreach (array_slice($missingList, 0, 20) as $item) {
        echo "   - {$item}\n";
    }
    if (count($missingList) > 20) {
        echo "   ... and " . (count($missingList) - 20) . " more\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════\n";
