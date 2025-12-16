<?php
/**
 * Fix Ausrüstung Products with Special Characters
 *
 * Problem: Products with × (multiplication sign) and special Scandinavian characters
 * fail SEO URL creation due to encoding issues.
 *
 * Solution: Proper character transliteration before slug generation
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

echo "\n======================================================================\n";
echo "     FIX AUSRÜSTUNG PRODUCTS - SPECIAL CHARACTERS\n";
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
 * Create a clean URL slug from product name
 * Handles special characters properly:
 * - × (multiplication sign) -> x
 * - Scandinavian characters (Å, Ö, etc.) -> ASCII equivalents
 * - German umlauts -> ASCII equivalents
 */
function createSlug($text) {
    // Character replacement map - BEFORE lowercasing for special chars
    $replacements = [
        // Multiplication sign (NOT letter x)
        '×' => 'x',
        // Scandinavian characters
        'Å' => 'A', 'å' => 'a',
        'Ä' => 'Ae', 'ä' => 'ae',
        'Ö' => 'Oe', 'ö' => 'oe',
        'Ø' => 'O', 'ø' => 'o',
        'Æ' => 'Ae', 'æ' => 'ae',
        // German umlauts
        'Ü' => 'Ue', 'ü' => 'ue',
        'ß' => 'ss',
        // Other common special characters
        '–' => '-', '—' => '-',
        "\xe2\x80\x98" => '', "\xe2\x80\x99" => '', "\xe2\x80\x9c" => '', "\xe2\x80\x9d" => '',
        '«' => '', '»' => '',
        '©' => '', '®' => '', '™' => '',
        '°' => '', '±' => '',
        '¼' => '1-4', '½' => '1-2', '¾' => '3-4',
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
    ];

    // Apply character replacements
    $text = str_replace(array_keys($replacements), array_values($replacements), $text);

    // Convert to lowercase
    $slug = strtolower($text);

    // Keep only alphanumeric, spaces, and hyphens
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);

    // Replace spaces with hyphens
    $slug = preg_replace('/\s+/', '-', $slug);

    // Remove multiple consecutive hyphens
    $slug = preg_replace('/-+/', '-', $slug);

    // Trim hyphens from start and end
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

// Products that need fixing (based on error analysis)
$problemProducts = [
    'HUNDFÖRARE',
    '8×19',
    '34×64',
    '30×61',
    '34×100',
];

echo "Finding products with special characters...\n\n";

// Get Ausrüstung category and its subcategories
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Ausrüstung']]
], $config);
$ausruestungId = $result['body']['data'][0]['id'] ?? null;
echo "Ausrüstung ID: $ausruestungId\n";

// Get subcategories
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $ausruestungId]]
], $config);
$subcategories = $result['body']['data'] ?? [];
$subcategoryMap = [];
foreach ($subcategories as $sub) {
    $subcategoryMap[$sub['id']] = $sub['name'];
}
echo "Found " . count($subcategories) . " subcategories\n\n";

$fixedCount = 0;
$errorCount = 0;

// Search for products containing special characters
foreach ($problemProducts as $searchTerm) {
    echo "Searching for products containing: '$searchTerm'\n";

    $result = apiRequest('POST', '/search/product', [
        'filter' => [
            ['type' => 'contains', 'field' => 'name', 'value' => $searchTerm]
        ],
        'associations' => [
            'categories' => [],
            'seoUrls' => []
        ],
        'limit' => 50
    ], $config);

    $products = $result['body']['data'] ?? [];
    echo "  Found " . count($products) . " products\n";

    foreach ($products as $product) {
        $productId = $product['id'];
        $productName = $product['name'];
        $productNumber = $product['productNumber'] ?? '';
        $categories = $product['categories'] ?? [];

        // Find which Ausrüstung subcategory this product belongs to
        $ausruestungSubId = null;
        $ausruestungSubName = null;
        foreach ($categories as $cat) {
            if (isset($subcategoryMap[$cat['id']])) {
                $ausruestungSubId = $cat['id'];
                $ausruestungSubName = $subcategoryMap[$cat['id']];
                break;
            }
        }

        if (!$ausruestungSubId) {
            echo "    SKIP: '$productName' - not in Ausrüstung subcategory\n";
            continue;
        }

        echo "\n  Processing: $productName\n";
        echo "    Product Number: $productNumber\n";
        echo "    Category: $ausruestungSubName\n";

        // Create slugs with proper character handling
        $productSlug = createSlug($productName);
        if ($productNumber) {
            $productSlug .= '-' . createSlug($productNumber);
        }

        $categorySlug = createSlug($ausruestungSubName);

        // Build the new SEO path
        $newSeoPath = "Ausruestung/$categorySlug/$productSlug";

        echo "    Product slug: $productSlug\n";
        echo "    Category slug: $categorySlug\n";
        echo "    New SEO path: /$newSeoPath\n";

        // Delete existing SEO URLs for this product
        $existingUrls = $product['seoUrls'] ?? [];
        $deletedCount = 0;
        foreach ($existingUrls as $url) {
            if ($url['routeName'] === 'frontend.detail.page') {
                apiRequest('DELETE', "/seo-url/{$url['id']}", null, $config);
                $deletedCount++;
            }
        }
        echo "    Deleted $deletedCount existing SEO URLs\n";

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
            echo "    SEO URL: OK\n";
            $fixedCount++;
        } else {
            echo "    SEO URL: ERROR - " . json_encode($result['body']) . "\n";
            $errorCount++;
        }

        // Also set main category for breadcrumbs
        $result = apiRequest('POST', '/_action/sync', [
            [
                'action' => 'upsert',
                'entity' => 'main_category',
                'payload' => [
                    [
                        'productId' => $productId,
                        'categoryId' => $ausruestungSubId,
                        'salesChannelId' => $salesChannelId,
                    ]
                ]
            ]
        ], $config);

        if ($result['code'] === 200) {
            echo "    Main category: OK\n";
        } else {
            echo "    Main category: (already set or error)\n";
        }
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
echo "Errors: $errorCount\n\n";
