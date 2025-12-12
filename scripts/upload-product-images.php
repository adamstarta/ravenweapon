<?php
/**
 * Upload product images from OLD site to NEW CHF site
 * Matches products by SKU and copies their images
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
echo "       UPLOAD PRODUCT IMAGES: OLD → NEW                     \n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Get tokens
echo "🔑 Getting API tokens...\n";
$oldToken = getToken($OLD_URL);
$newToken = getToken($NEW_URL);

if (!$oldToken || !$newToken) {
    die("❌ Failed to get tokens\n");
}
echo "   ✅ Tokens obtained\n\n";

// Get all products from OLD with their images
echo "📦 Fetching products with images from OLD site...\n";
$oldProducts = [];
$page = 1;
$limit = 100;

do {
    $response = apiPost($OLD_URL, $oldToken, 'search/product', [
        'limit' => $limit,
        'page' => $page,
        'total-count-mode' => 1,
        'associations' => [
            'cover' => [
                'associations' => [
                    'media' => []
                ]
            ]
        ]
    ]);

    if (!empty($response['data']['data'])) {
        // Build media lookup from included data
        $mediaLookup = [];
        if (!empty($response['data']['included'])) {
            foreach ($response['data']['included'] as $item) {
                if ($item['type'] === 'media' && !empty($item['attributes']['url'])) {
                    $mediaLookup[$item['id']] = $item['attributes']['url'];
                }
            }
        }

        foreach ($response['data']['data'] as $p) {
            $sku = $p['productNumber'] ?? $p['attributes']['productNumber'] ?? '';
            $coverUrl = null;
            $name = $p['name'] ?? $p['attributes']['name'] ?? 'Unknown';

            // Try to get cover image URL from different sources
            // 1. Direct cover.media.url
            if (!empty($p['cover']['media']['url'])) {
                $coverUrl = $p['cover']['media']['url'];
            }
            // 2. From attributes
            elseif (!empty($p['attributes']['cover']['media']['url'])) {
                $coverUrl = $p['attributes']['cover']['media']['url'];
            }
            // 3. From included data via coverId
            elseif (!empty($p['coverId']) || !empty($p['attributes']['coverId'])) {
                $coverId = $p['coverId'] ?? $p['attributes']['coverId'];
                // coverId is product_media id, need to get mediaId from it
                if (!empty($response['data']['included'])) {
                    foreach ($response['data']['included'] as $item) {
                        if ($item['type'] === 'product_media' && $item['id'] === $coverId) {
                            $mediaId = $item['attributes']['mediaId'] ?? null;
                            if ($mediaId && isset($mediaLookup[$mediaId])) {
                                $coverUrl = $mediaLookup[$mediaId];
                            }
                            break;
                        }
                    }
                }
            }

            if ($sku) {
                if ($coverUrl) {
                    $oldProducts[$sku] = [
                        'coverUrl' => $coverUrl,
                        'name' => $name
                    ];
                } else {
                    // Try to fetch media directly via API if we have a coverId
                    $coverId = $p['coverId'] ?? $p['attributes']['coverId'] ?? null;
                    if ($coverId) {
                        // Get product media to find media ID
                        $pmResponse = apiGet($OLD_URL, $oldToken, 'product-media/' . $coverId . '?associations[media]=[]');
                        if (!empty($pmResponse['data']['attributes']['media']['url'])) {
                            $coverUrl = $pmResponse['data']['attributes']['media']['url'];
                        } elseif (!empty($pmResponse['data']['relationships']['media']['data']['id'])) {
                            $mediaId = $pmResponse['data']['relationships']['media']['data']['id'];
                            $mediaResponse = apiGet($OLD_URL, $oldToken, 'media/' . $mediaId);
                            $coverUrl = $mediaResponse['data']['attributes']['url'] ?? null;
                        }
                        if ($coverUrl) {
                            $oldProducts[$sku] = [
                                'coverUrl' => $coverUrl,
                                'name' => $name
                            ];
                        }
                    }
                }
            }
        }
        echo "   Page {$page}: " . count($response['data']['data']) . " products\n";
    }

    $total = $response['data']['meta']['total'] ?? 0;
    $page++;
} while ($page <= ceil($total / $limit) && $page <= 10);

echo "   Products with images found: " . count($oldProducts) . "\n\n";

// Get all products from NEW that need images
echo "📦 Fetching products from NEW site...\n";
$newProducts = [];
$page = 1;

do {
    $response = apiPost($NEW_URL, $newToken, 'search/product', [
        'limit' => $limit,
        'page' => $page,
        'total-count-mode' => 1,
        'associations' => [
            'cover' => []
        ]
    ]);

    if (!empty($response['data']['data'])) {
        foreach ($response['data']['data'] as $p) {
            $sku = $p['productNumber'] ?? $p['attributes']['productNumber'] ?? '';
            $hasCover = !empty($p['coverId']) || !empty($p['attributes']['coverId']);

            if ($sku) {
                $newProducts[$sku] = [
                    'id' => $p['id'],
                    'hasCover' => $hasCover,
                    'name' => $p['name'] ?? $p['attributes']['name'] ?? 'Unknown'
                ];
            }
        }
    }

    $total = $response['data']['meta']['total'] ?? 0;
    $page++;
} while (count($newProducts) < $total && $page <= 10);

echo "   Total products in NEW: " . count($newProducts) . "\n\n";

// Find products needing images
$needsImage = [];
foreach ($newProducts as $sku => $product) {
    if (!$product['hasCover'] && isset($oldProducts[$sku])) {
        $needsImage[$sku] = [
            'productId' => $product['id'],
            'name' => $product['name'],
            'imageUrl' => $oldProducts[$sku]['coverUrl']
        ];
    }
}

echo "📷 Products needing images: " . count($needsImage) . "\n\n";

if (empty($needsImage)) {
    echo "✅ All products already have images!\n";
    exit;
}

// Upload images
echo "🚀 Uploading images...\n\n";
$uploaded = 0;
$errors = 0;

foreach ($needsImage as $sku => $product) {
    $productId = $product['productId'];
    $imageUrl = $product['imageUrl'];
    $productName = $product['name'];

    // Extract file extension
    $pathInfo = pathinfo(parse_url($imageUrl, PHP_URL_PATH));
    $extension = $pathInfo['extension'] ?? 'jpg';
    $fileName = $pathInfo['filename'] ?? $sku;

    // 1. Create media entry
    $mediaId = bin2hex(random_bytes(16));
    $result = apiPost($NEW_URL, $newToken, 'media', ['id' => $mediaId]);

    if ($result['code'] < 200 || ($result['code'] >= 300 && $result['code'] != 204)) {
        $errors++;
        echo "   ❌ {$sku}: Failed to create media entry\n";
        continue;
    }

    // 2. Upload image from URL
    $uploadUrl = $NEW_URL . '/api/_action/media/' . $mediaId . '/upload?extension=' . $extension . '&fileName=' . urlencode($fileName);

    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $newToken,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode(['url' => $imageUrl])
    ]);
    $uploadResponse = curl_exec($ch);
    $uploadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($uploadCode < 200 || ($uploadCode >= 300 && $uploadCode != 204)) {
        $errors++;
        echo "   ❌ {$sku}: Failed to upload image (HTTP {$uploadCode})\n";
        continue;
    }

    // 3. Create product media relation
    $productMediaId = bin2hex(random_bytes(16));
    $result = apiPost($NEW_URL, $newToken, 'product-media', [
        'id' => $productMediaId,
        'productId' => $productId,
        'mediaId' => $mediaId,
        'position' => 1
    ]);

    if ($result['code'] < 200 || ($result['code'] >= 300 && $result['code'] != 204)) {
        $errors++;
        echo "   ❌ {$sku}: Failed to link media to product\n";
        continue;
    }

    // 4. Set as cover image
    $result = apiPatch($NEW_URL, $newToken, 'product/' . $productId, [
        'coverId' => $productMediaId
    ]);

    if ($result['code'] >= 200 && $result['code'] < 300 || $result['code'] == 204) {
        $uploaded++;
        echo "   ✅ [{$uploaded}] {$sku}: {$productName}\n";
    } else {
        $errors++;
        echo "   ⚠️ {$sku}: Image uploaded but cover not set\n";
    }

    // Rate limit
    if (($uploaded + $errors) % 10 == 0) {
        usleep(500000); // 0.5 second pause

        // Refresh token if needed (every 50 products)
        if (($uploaded + $errors) % 50 == 0) {
            $newToken = getToken($NEW_URL);
        }
    }
}

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "                    UPLOAD COMPLETE!                        \n";
echo "═══════════════════════════════════════════════════════════\n";
echo "   ✅ Uploaded: {$uploaded} images\n";
echo "   ❌ Errors: {$errors}\n";
echo "═══════════════════════════════════════════════════════════\n";
