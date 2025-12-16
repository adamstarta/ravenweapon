<?php
/**
 * Check Ausrüstung subcategories for products and SEO URLs
 */

$config = [
    'base_url' => 'http://localhost',
    'client_id' => 'SWIARAVEN03399CEA2C931269',
    'client_secret' => 'RavenNavbarUpdate2025!'
];

function getAccessToken($config) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['base_url'] . '/api/oauth/token',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'client_credentials',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret']
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function apiRequest($method, $endpoint, $data, $token, $config) {
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $config['base_url'] . '/api' . $endpoint,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true
    ];
    if ($data !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$token = getAccessToken($config);
if (!$token) {
    die("Failed to get token\n");
}
echo "Got token\n\n";

// Find Ausrüstung category
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Ausrüstung']],
    'includes' => ['category' => ['id', 'name', 'productCount']],
    'associations' => ['seoUrls' => []]
], $token, $config);

if (!isset($result['data'][0])) {
    die("Ausrüstung not found\n");
}

$ausruestung = $result['data'][0];
echo "=== AUSRÜSTUNG ===\n";
echo "ID: " . $ausruestung['id'] . "\n";
echo "Name: " . $ausruestung['name'] . "\n\n";

// Get SEO URL for main category
$seoResult = apiRequest('POST', '/search/seo-url', [
    'filter' => [['type' => 'equals', 'field' => 'foreignKey', 'value' => $ausruestung['id']]],
    'includes' => ['seo_url' => ['seoPathInfo', 'isCanonical']]
], $token, $config);
echo "Main category SEO URLs:\n";
if (isset($seoResult['data'])) {
    foreach ($seoResult['data'] as $seo) {
        echo "  - /" . $seo['seoPathInfo'] . ($seo['isCanonical'] ? ' (canonical)' : '') . "\n";
    }
}
if (empty($seoResult['data'])) {
    echo "  NO SEO URL SET!\n";
}

// Get subcategories with product counts
echo "\n=== SUBCATEGORIES ===\n";
$subResult = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $ausruestung['id']]],
    'includes' => ['category' => ['id', 'name', 'productCount', 'active', 'visible']],
    'limit' => 50
], $token, $config);

$totalProducts = 0;
$emptyCategories = [];
$categoriesWithProducts = [];

foreach ($subResult['data'] ?? [] as $sub) {
    // Get product count for this category
    $productResult = apiRequest('POST', '/search/product', [
        'filter' => [['type' => 'equals', 'field' => 'categoryIds', 'value' => $sub['id']]],
        'limit' => 1,
        'total-count-mode' => 1
    ], $token, $config);

    $productCount = $productResult['total'] ?? 0;
    $totalProducts += $productCount;

    // Get SEO URL
    $seoResult = apiRequest('POST', '/search/seo-url', [
        'filter' => [['type' => 'equals', 'field' => 'foreignKey', 'value' => $sub['id']]],
        'includes' => ['seo_url' => ['seoPathInfo', 'isCanonical']]
    ], $token, $config);

    $seoUrl = 'NO SEO URL';
    if (isset($seoResult['data'][0])) {
        $seoUrl = '/' . $seoResult['data'][0]['seoPathInfo'];
    }

    $status = $productCount > 0 ? "✓" : "✗";
    echo sprintf("%s %-25s | %3d products | %s\n",
        $status,
        $sub['name'],
        $productCount,
        $seoUrl
    );

    if ($productCount == 0) {
        $emptyCategories[] = $sub['name'];
    } else {
        $categoriesWithProducts[] = ['name' => $sub['name'], 'count' => $productCount];
    }
}

echo "\n=== SUMMARY ===\n";
echo "Total subcategories: " . count($subResult['data'] ?? []) . "\n";
echo "Categories with products: " . count($categoriesWithProducts) . "\n";
echo "Empty categories: " . count($emptyCategories) . "\n";
echo "Total products in subcategories: " . $totalProducts . "\n";

if (count($emptyCategories) > 0) {
    echo "\nEmpty categories:\n";
    foreach ($emptyCategories as $cat) {
        echo "  - $cat\n";
    }
}

// Check if products are in parent category instead
echo "\n=== CHECKING PARENT CATEGORY ===\n";
$parentProductResult = apiRequest('POST', '/search/product', [
    'filter' => [['type' => 'equals', 'field' => 'categoryIds', 'value' => $ausruestung['id']]],
    'limit' => 1,
    'total-count-mode' => 1
], $token, $config);
echo "Products directly in Ausrüstung (parent): " . ($parentProductResult['total'] ?? 0) . "\n";
