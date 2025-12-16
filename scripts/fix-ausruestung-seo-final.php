<?php
/**
 * FINAL FIX: Ausrüstung SEO URLs with Proper Category Slug Transliteration
 *
 * Root cause of 404 errors:
 * - Category names like "Gürtel", "Polizeiausrüstung" contain special chars
 * - These become /Ausruestung/Gürtel/... which browsers can't resolve
 * - Need to transliterate to /Ausruestung/Guertel/...
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

echo "\n======================================================================\n";
echo "     FINAL FIX: AUSRÜSTUNG SEO URLS (PROPER TRANSLITERATION)\n";
echo "======================================================================\n\n";

$GLOBALS['token_data'] = ['token' => null, 'expires_at' => 0];

function getAccessToken($config, $forceRefresh = false) {
    if (!$forceRefresh && $GLOBALS['token_data']['token'] && $GLOBALS['token_data']['expires_at'] > time() + 60) {
        return $GLOBALS['token_data']['token'];
    }
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) return null;
    $data = json_decode($response, true);
    $GLOBALS['token_data']['token'] = $data['access_token'] ?? null;
    $GLOBALS['token_data']['expires_at'] = time() + ($data['expires_in'] ?? 600);
    return $GLOBALS['token_data']['token'];
}

function apiRequest($method, $endpoint, $data, $config, $retry = true) {
    $token = getAccessToken($config);
    if (!$token) return ['code' => 0, 'body' => null];
    $ch = curl_init();
    $url = $config['shopware_url'] . '/api/' . ltrim($endpoint, '/');
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    if ($data && in_array($method, ['POST', 'PATCH', 'PUT'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 401 && $retry) {
        $GLOBALS['token_data']['token'] = null;
        return apiRequest($method, $endpoint, $data, $config, false);
    }
    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

/**
 * Create ASCII-safe slug - THIS is the key fix
 */
function createSlug($text) {
    // Character replacement map for German/Scandinavian chars
    $replacements = [
        // German umlauts - use 'e' form
        'Ä' => 'Ae', 'ä' => 'ae',
        'Ö' => 'Oe', 'ö' => 'oe',
        'Ü' => 'Ue', 'ü' => 'ue',
        'ß' => 'ss',
        // Scandinavian
        'Å' => 'A', 'å' => 'a',
        'Ø' => 'O', 'ø' => 'o',
        'Æ' => 'Ae', 'æ' => 'ae',
        // Multiplication sign
        '×' => 'x',
        // Dashes
        '–' => '-', '—' => '-',
        // Accented vowels
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u',
        'Ñ' => 'N', 'ñ' => 'n',
        'Ç' => 'C', 'ç' => 'c',
        // Ampersand
        '&' => 'und',
    ];

    // Apply replacements
    $text = str_replace(array_keys($replacements), array_values($replacements), $text);

    // Lowercase
    $slug = strtolower($text);

    // Keep only alphanumeric, spaces, hyphens
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);

    // Replace spaces with hyphens
    $slug = preg_replace('/\s+/', '-', $slug);

    // Remove multiple hyphens
    $slug = preg_replace('/-+/', '-', $slug);

    // Trim hyphens
    $slug = trim($slug, '-');

    return $slug;
}

// Authenticate
$token = getAccessToken($config);
if (!$token) die("ERROR: Failed to authenticate!\n");
echo "Authenticated OK\n\n";

// Get sales channel
$result = apiRequest('POST', '/search/sales-channel', [
    'filter' => [['type' => 'contains', 'field' => 'name', 'value' => 'Storefront']]
], $config);
$salesChannelId = $result['body']['data'][0]['id'] ?? null;
echo "Sales Channel ID: $salesChannelId\n";

// Get language ID
$result = apiRequest('POST', '/search/language', ['limit' => 1], $config);
$languageId = $result['body']['data'][0]['id'] ?? null;
echo "Language ID: $languageId\n\n";

// Get Ausrüstung category
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Ausrüstung']]
], $config);
$ausruestungId = $result['body']['data'][0]['id'] ?? null;
echo "Ausrüstung ID: $ausruestungId\n";

// Get all subcategories
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $ausruestungId]]
], $config);
$subcategories = $result['body']['data'] ?? [];
echo "Found " . count($subcategories) . " subcategories\n\n";

// Create category slug map
$categorySlugMap = [];
echo "Category slug translations:\n";
foreach ($subcategories as $sub) {
    $originalName = $sub['name'];
    $slug = createSlug($originalName);
    $categorySlugMap[$sub['id']] = [
        'name' => $originalName,
        'slug' => $slug,
    ];
    echo "  {$originalName} -> {$slug}\n";
}
echo "\n";

$fixedCount = 0;
$errorCount = 0;
$skippedCount = 0;

echo "======================================================================\n";
echo "     PROCESSING ALL PRODUCTS\n";
echo "======================================================================\n\n";

foreach ($subcategories as $subcategory) {
    $subId = $subcategory['id'];
    $subName = $subcategory['name'];
    $subSlug = $categorySlugMap[$subId]['slug'];

    echo "Category: $subName (slug: $subSlug)\n";
    echo str_repeat('-', 60) . "\n";

    // Get products in this category
    $result = apiRequest('POST', '/search/product', [
        'filter' => [
            ['type' => 'equals', 'field' => 'categories.id', 'value' => $subId]
        ],
        'associations' => [
            'seoUrls' => [],
            'categories' => []
        ],
        'limit' => 500
    ], $config);

    $products = $result['body']['data'] ?? [];
    echo "  Products: " . count($products) . "\n";

    foreach ($products as $product) {
        $productId = $product['id'];
        $productName = $product['name'];
        $productNumber = $product['productNumber'] ?? '';

        // Check if this product's primary category is an Ausrüstung subcategory
        // Skip products that belong to other main categories (like Waffenzubehoer)
        $categories = $product['categories'] ?? [];
        $belongsToAusruestung = false;
        foreach ($categories as $cat) {
            if (isset($categorySlugMap[$cat['id']])) {
                $belongsToAusruestung = true;
                break;
            }
        }

        // Check if product has a canonical URL pointing to non-Ausruestung path
        $existingUrls = $product['seoUrls'] ?? [];
        $hasNonAusruestungUrl = false;
        foreach ($existingUrls as $url) {
            if ($url['isCanonical'] && $url['routeName'] === 'frontend.detail.page') {
                if (strpos($url['seoPathInfo'], 'Ausruestung') === false &&
                    strpos($url['seoPathInfo'], 'Waffenzubehoer') !== false ||
                    strpos($url['seoPathInfo'], 'Zubehoer') !== false) {
                    $hasNonAusruestungUrl = true;
                    break;
                }
            }
        }

        // Skip products that primarily belong to other categories
        if ($hasNonAusruestungUrl) {
            $skippedCount++;
            continue;
        }

        // Create product slug
        $productSlug = createSlug($productName);
        if ($productNumber) {
            $numSlug = createSlug($productNumber);
            if ($numSlug) {
                $productSlug .= '-' . $numSlug;
            }
        }

        // Build the new SEO path with proper ASCII slugs
        $newSeoPath = "Ausruestung/{$subSlug}/{$productSlug}";

        echo "    {$productName}\n";
        echo "      -> /{$newSeoPath}\n";

        // Delete existing SEO URLs for this product (only Ausruestung ones)
        $deletedCount = 0;
        foreach ($existingUrls as $url) {
            if ($url['routeName'] === 'frontend.detail.page' &&
                strpos($url['seoPathInfo'], 'Ausruestung') !== false) {
                apiRequest('DELETE', "/seo-url/{$url['id']}", null, $config);
                $deletedCount++;
            }
        }

        // Create new SEO URL
        $seoUrlId = bin2hex(random_bytes(16));
        $result = apiRequest('POST', '/seo-url', [
            'id' => $seoUrlId,
            'salesChannelId' => $salesChannelId,
            'languageId' => $languageId,
            'foreignKey' => $productId,
            'routeName' => 'frontend.detail.page',
            'pathInfo' => '/detail/' . $productId,
            'seoPathInfo' => $newSeoPath,
            'isCanonical' => true,
            'isModified' => true,
        ], $config);

        if ($result['code'] === 204 || $result['code'] === 200) {
            echo "      [OK]\n";
            $fixedCount++;
        } else {
            echo "      [ERROR] " . ($result['body']['errors'][0]['detail'] ?? 'Unknown') . "\n";
            $errorCount++;
        }

        // Update main category for breadcrumbs
        $result = apiRequest('POST', '/_action/sync', [
            [
                'action' => 'upsert',
                'entity' => 'main_category',
                'payload' => [
                    [
                        'productId' => $productId,
                        'categoryId' => $subId,
                        'salesChannelId' => $salesChannelId,
                    ]
                ]
            ]
        ], $config);
    }

    echo "\n";
}

// Clear cache
echo "Clearing cache...\n";
apiRequest('DELETE', '/_action/cache', null, $config);
echo "Cache cleared\n";

echo "\n======================================================================\n";
echo "     DONE!\n";
echo "======================================================================\n";
echo "Fixed: $fixedCount products\n";
echo "Skipped (belong to other categories): $skippedCount\n";
echo "Errors: $errorCount\n\n";
echo "Test URLs:\n";
echo "  https://ortak.ch/Ausruestung/Guertel/\n";
echo "  https://ortak.ch/Ausruestung/Polizeiausruestung/\n";
echo "  https://ortak.ch/Ausruestung/Medizinische-Ausruestung/\n";
echo "  https://ortak.ch/Ausruestung/Taschen-und-Rucksaecke/\n";
