<?php
/**
 * Check all Lockhart Tactical products and their cover photos
 */

$API_URL = 'https://ortak.ch';

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'client_credentials',
            'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
            'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
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

echo "=== LOCKHART TACTICAL PRODUCTS CHECK ===\n\n";

$token = getToken($API_URL);
if (!$token) {
    die("Failed to authenticate!\n");
}
echo "✓ Authenticated\n\n";

// Find Lockhart Tactical manufacturer ID
$result = apiRequest($API_URL, $token, 'POST', 'search/product-manufacturer', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'Lockhart']
    ]
]);

$manufacturerId = null;
foreach ($result['data'] ?? [] as $m) {
    $mName = $m['attributes']['name'] ?? $m['name'] ?? '';
    echo "Found manufacturer: $mName\n";
    $manufacturerId = $m['id'];
}

if (!$manufacturerId) {
    die("Lockhart Tactical manufacturer not found!\n");
}

echo "\nManufacturer ID: $manufacturerId\n\n";

// Get all products by this manufacturer
$result = apiRequest($API_URL, $token, 'POST', 'search/product', [
    'filter' => [
        ['type' => 'equals', 'field' => 'manufacturerId', 'value' => $manufacturerId]
    ],
    'associations' => [
        'media' => [
            'associations' => ['media' => []]
        ],
        'cover' => [
            'associations' => ['media' => []]
        ]
    ],
    'limit' => 100
]);

// Index included items
$included = [];
if (isset($result['included'])) {
    foreach ($result['included'] as $item) {
        $included[$item['id']] = $item;
    }
}

echo "=== LOCKHART TACTICAL PRODUCTS ===\n";
echo "Found: " . count($result['data'] ?? []) . " products\n\n";

foreach ($result['data'] ?? [] as $p) {
    $attrs = $p['attributes'] ?? $p;
    $name = $attrs['name'] ?? 'Unknown';
    $sku = $attrs['productNumber'] ?? 'Unknown';
    $productId = $p['id'];
    $coverId = $attrs['coverId'] ?? 'None';
    $relationships = $p['relationships'] ?? [];

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Product: $name\n";
    echo "SKU: $sku\n";
    echo "ID: $productId\n";

    // Cover info
    if (isset($relationships['cover']['data']['id'])) {
        $coverPmId = $relationships['cover']['data']['id'];
        $coverPm = $included[$coverPmId] ?? null;
        if ($coverPm) {
            $coverAttrs = $coverPm['attributes'] ?? [];
            if (isset($coverPm['relationships']['media']['data']['id'])) {
                $mediaId = $coverPm['relationships']['media']['data']['id'];
                $media = $included[$mediaId] ?? null;
                if ($media) {
                    $mediaAttrs = $media['attributes'] ?? [];
                    $fileName = $mediaAttrs['fileName'] ?? 'unknown';
                    $fileExt = $mediaAttrs['fileExtension'] ?? '';
                    echo "Cover: $fileName.$fileExt\n";
                    echo "Cover URL: " . ($mediaAttrs['url'] ?? 'N/A') . "\n";
                }
            }
        }
    } else {
        echo "Cover: NONE\n";
    }

    // Media count
    $mediaRels = $relationships['media']['data'] ?? [];
    echo "Gallery Images: " . count($mediaRels) . "\n";

    // List all gallery images
    foreach ($mediaRels as $i => $mRel) {
        $pmId = $mRel['id'];
        $pm = $included[$pmId] ?? null;
        if ($pm) {
            $pmAttrs = $pm['attributes'] ?? [];
            $position = $pmAttrs['position'] ?? 'N/A';
            $mediaId = $pm['relationships']['media']['data']['id'] ?? 'N/A';
            $media = $included[$mediaId] ?? null;
            if ($media) {
                $mediaAttrs = $media['attributes'] ?? [];
                $fileName = $mediaAttrs['fileName'] ?? 'unknown';
                echo "  [$position] $fileName\n";
            }
        }
    }

    echo "\n";
}

echo "=== DONE ===\n";
