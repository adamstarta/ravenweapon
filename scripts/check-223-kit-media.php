<?php
/**
 * Check .223 CALIBER KIT product media
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
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "cURL Error: $error\n";
        return null;
    }

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

echo "=== .223 CALIBER KIT MEDIA CHECK ===\n\n";

$token = getToken($API_URL);
if (!$token) {
    die("Failed to authenticate!\n");
}
echo "✓ Authenticated\n\n";

// Find the .223 CALIBER KIT product
$result = apiRequest($API_URL, $token, 'POST', 'search/product', [
    'filter' => [
        ['type' => 'equals', 'field' => 'productNumber', 'value' => 'KIT-223']
    ],
    'associations' => [
        'media' => [
            'associations' => ['media' => []]
        ],
        'cover' => [
            'associations' => ['media' => []]
        ]
    ]
]);

if (empty($result['data'])) {
    // Try by name
    $result = apiRequest($API_URL, $token, 'POST', 'search/product', [
        'filter' => [
            ['type' => 'contains', 'field' => 'name', 'value' => '223 CALIBER KIT']
        ],
        'associations' => [
            'media' => [
                'associations' => ['media' => []]
            ],
            'cover' => [
                'associations' => ['media' => []]
            ]
        ]
    ]);
}

// Debug: show raw response structure
echo "Raw response keys: " . implode(', ', array_keys($result)) . "\n";
if (isset($result['data'])) {
    echo "Data count: " . count($result['data']) . "\n";
}
if (isset($result['included'])) {
    echo "Included count: " . count($result['included']) . "\n\n";

    // Index included items by ID for easy lookup
    $included = [];
    foreach ($result['included'] as $item) {
        $included[$item['id']] = $item;
    }
}

foreach ($result['data'] ?? [] as $p) {
    // Handle Shopware API response format (data can be flat or nested in attributes)
    $attrs = $p['attributes'] ?? $p;
    $name = $attrs['name'] ?? $p['name'] ?? 'Unknown';
    $sku = $attrs['productNumber'] ?? $p['productNumber'] ?? 'Unknown';
    $productId = $p['id'] ?? 'Unknown';
    $coverId = $attrs['coverId'] ?? $p['coverId'] ?? 'None';

    echo "Product: $name\n";
    echo "SKU: $sku\n";
    echo "Product ID: $productId\n";
    echo "Cover ID: $coverId\n\n";

    // Check relationships for media and cover
    $relationships = $p['relationships'] ?? [];
    echo "Relationships: " . implode(', ', array_keys($relationships)) . "\n\n";

    // Cover from relationships
    if (isset($relationships['cover']['data']['id']) && isset($included)) {
        $coverPmId = $relationships['cover']['data']['id'];
        $coverPm = $included[$coverPmId] ?? null;
        if ($coverPm) {
            $coverAttrs = $coverPm['attributes'] ?? [];
            echo "=== COVER IMAGE ===\n";
            echo "  Product Media ID: $coverPmId\n";
            echo "  Position: " . ($coverAttrs['position'] ?? 'N/A') . "\n";

            // Get the actual media from relationships
            if (isset($coverPm['relationships']['media']['data']['id'])) {
                $mediaId = $coverPm['relationships']['media']['data']['id'];
                $media = $included[$mediaId] ?? null;
                if ($media) {
                    $mediaAttrs = $media['attributes'] ?? [];
                    echo "  Media ID: $mediaId\n";
                    echo "  File: " . ($mediaAttrs['fileName'] ?? 'N/A') . "." . ($mediaAttrs['fileExtension'] ?? '') . "\n";
                    echo "  URL: " . ($mediaAttrs['url'] ?? 'N/A') . "\n";
                }
            }
            echo "\n";
        }
    } else {
        echo "Cover relationship not found\n\n";
    }

    // All media from relationships
    $mediaRels = $relationships['media']['data'] ?? [];
    echo "=== ALL MEDIA (" . count($mediaRels) . " images) ===\n";

    if (empty($mediaRels)) {
        echo "  Media array is empty\n";
    }

    foreach ($mediaRels as $i => $mRel) {
        $pmId = $mRel['id'];
        $pm = $included[$pmId] ?? null;
        if ($pm) {
            $pmAttrs = $pm['attributes'] ?? [];
            $position = $pmAttrs['position'] ?? 'N/A';

            $isCover = ($pmId === $coverId) ? ' [COVER]' : '';

            // Get actual media
            $mediaId = $pm['relationships']['media']['data']['id'] ?? 'N/A';
            $media = $included[$mediaId] ?? null;
            $fileName = 'unknown';
            $fileExt = '';
            $url = 'N/A';
            if ($media) {
                $mediaAttrs = $media['attributes'] ?? [];
                $fileName = $mediaAttrs['fileName'] ?? 'unknown';
                $fileExt = $mediaAttrs['fileExtension'] ?? '';
                $url = $mediaAttrs['url'] ?? 'N/A';
            }

            echo "  [$i] $fileName.$fileExt (pos: $position)$isCover\n";
            echo "      Product Media ID: $pmId\n";
            echo "      Media ID: $mediaId\n";
            echo "      URL: $url\n";
        }
    }
}

echo "\n=== DONE ===\n";
