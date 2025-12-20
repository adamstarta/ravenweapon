<?php
/**
 * Fix product media associations for the 2 missing Snigel products
 */

$API_URL = 'https://ortak.ch';

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'client_credentials',
            'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
            'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
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
echo "     FIX PRODUCT MEDIA ASSOCIATIONS                        \n";
echo "═══════════════════════════════════════════════════════════\n\n";

$token = getToken($API_URL);
if (!$token) {
    die("Failed to get token\n");
}
echo "✓ Got API token\n\n";

// Products to fix
$products = [
    [
        'name' => 'COVERT EQUIPMENT VEST -12 FIN',
        'mediaPattern' => '18-00343'
    ],
    [
        'name' => 'EQUIPMENT BELT HARNESS -10',
        'mediaPattern' => '15-00607'
    ]
];

foreach ($products as $p) {
    echo "═══════════════════════════════════════════════════════════\n";
    echo "  " . $p['name'] . "\n";
    echo "═══════════════════════════════════════════════════════════\n";

    // Find product
    $productResult = apiPost($API_URL, $token, 'search/product', [
        'limit' => 1,
        'filter' => [['type' => 'contains', 'field' => 'name', 'value' => $p['name']]]
    ]);

    if (empty($productResult['data']['data'])) {
        echo "  ✗ Product not found!\n";
        continue;
    }

    $productId = $productResult['data']['data'][0]['id'];
    echo "  Product ID: " . substr($productId, 0, 8) . "...\n";

    // Find media
    $mediaResult = apiPost($API_URL, $token, 'search/media', [
        'limit' => 20,
        'filter' => [['type' => 'contains', 'field' => 'fileName', 'value' => $p['mediaPattern']]]
    ]);

    if (empty($mediaResult['data']['data'])) {
        echo "  ✗ No media found!\n";
        continue;
    }

    $mediaIds = array_column($mediaResult['data']['data'], 'id');
    echo "  Found " . count($mediaIds) . " media files\n";

    // Create product_media associations with unique IDs
    $productMedia = [];
    $firstProductMediaId = null;

    foreach ($mediaIds as $idx => $mediaId) {
        $productMediaId = bin2hex(random_bytes(16));
        if ($idx === 0) {
            $firstProductMediaId = $productMediaId;
        }
        $productMedia[] = [
            'id' => $productMediaId,
            'mediaId' => $mediaId,
            'position' => $idx
        ];
    }

    // Update product with media and cover
    echo "  Associating " . count($productMedia) . " media with product...\n";

    $updateResult = apiPatch($API_URL, $token, 'product/' . $productId, [
        'media' => $productMedia,
        'coverId' => $firstProductMediaId
    ]);

    if ($updateResult['code'] == 204 || $updateResult['code'] == 200) {
        echo "  ✓ SUCCESS! Cover ID: " . substr($firstProductMediaId, 0, 8) . "...\n";
    } else {
        echo "  ✗ Failed: HTTP " . $updateResult['code'] . "\n";
        if (!empty($updateResult['data']['errors'])) {
            foreach ($updateResult['data']['errors'] as $err) {
                echo "    " . ($err['detail'] ?? json_encode($err)) . "\n";
            }
        }
    }
    echo "\n";
}

echo "═══════════════════════════════════════════════════════════\n";
echo "                        COMPLETE!                          \n";
echo "═══════════════════════════════════════════════════════════\n\n";
