<?php
/**
 * Compare products from old shop with ortak.ch
 * Reads scraped data and compares with Shopware API
 */

$config = [
    'base_url' => 'https://ortak.ch',
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
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
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
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ];
    if ($data !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function normalizeProductName($name) {
    // Decode HTML entities
    $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Convert to lowercase
    $name = mb_strtolower($name, 'UTF-8');
    // Remove special characters but keep letters and numbers
    $name = preg_replace('/[^a-z0-9\s]/', '', $name);
    // Replace multiple spaces with single space
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

function similarityScore($str1, $str2) {
    similar_text($str1, $str2, $percent);
    return $percent;
}

echo "=== Product Comparison: Old Shop vs Ortak.ch ===\n\n";

// Load old shop products
$oldShopFile = __DIR__ . '/old-shop-data/all-products.json';
if (!file_exists($oldShopFile)) {
    die("Error: Old shop data not found. Run scraper first.\n");
}

$oldProducts = json_decode(file_get_contents($oldShopFile), true);
echo "Loaded " . count($oldProducts) . " products from old shop\n";

// Get access token
$token = getAccessToken($config);
if (!$token) {
    die("Failed to get API token\n");
}
echo "Got API token\n\n";

// Fetch all products from ortak.ch
echo "Fetching products from ortak.ch...\n";
$allOrtakProducts = [];
$page = 1;
$limit = 500;

do {
    $result = apiRequest('POST', '/search/product', [
        'page' => $page,
        'limit' => $limit,
        'includes' => [
            'product' => ['id', 'name', 'productNumber', 'price', 'active']
        ]
    ], $token, $config);

    $products = $result['data'] ?? [];
    $allOrtakProducts = array_merge($allOrtakProducts, $products);

    echo "  Page $page: " . count($products) . " products\n";

    $hasMore = count($products) === $limit;
    $page++;
} while ($hasMore && $page <= 20);

echo "\nTotal ortak.ch products: " . count($allOrtakProducts) . "\n\n";

// Create normalized lookup for ortak products
$ortakNormalized = [];
foreach ($allOrtakProducts as $product) {
    $normalized = normalizeProductName($product['name']);
    $ortakNormalized[$normalized] = $product;
}

// Compare products
$found = [];
$notFound = [];
$partialMatches = [];

foreach ($oldProducts as $oldProduct) {
    $oldName = $oldProduct['name'];
    $oldNormalized = normalizeProductName($oldName);

    // Exact match
    if (isset($ortakNormalized[$oldNormalized])) {
        $ortakProduct = $ortakNormalized[$oldNormalized];
        $found[] = [
            'old' => $oldProduct,
            'ortak' => $ortakProduct,
            'match' => 'exact'
        ];
        continue;
    }

    // Partial match - check similarity
    $bestMatch = null;
    $bestScore = 0;

    foreach ($ortakNormalized as $ortakNorm => $ortakProduct) {
        $score = similarityScore($oldNormalized, $ortakNorm);
        if ($score > $bestScore && $score >= 70) {
            $bestScore = $score;
            $bestMatch = $ortakProduct;
        }
    }

    if ($bestMatch) {
        $partialMatches[] = [
            'old' => $oldProduct,
            'ortak' => $bestMatch,
            'score' => $bestScore,
            'match' => 'partial'
        ];
    } else {
        $notFound[] = $oldProduct;
    }
}

// Generate report
echo "=== COMPARISON RESULTS ===\n\n";

echo "EXACT MATCHES: " . count($found) . "\n";
echo str_repeat('-', 60) . "\n";
foreach ($found as $item) {
    $oldPrice = $item['old']['price'];
    $ortakPrice = isset($item['ortak']['price'][0]['gross'])
        ? number_format($item['ortak']['price'][0]['gross'], 2)
        : 'N/A';
    echo "  ✓ " . $item['old']['name'] . "\n";
    echo "    Old: CHF $oldPrice | Ortak: CHF $ortakPrice\n";
}

echo "\n\nPARTIAL MATCHES (" . count($partialMatches) . "):\n";
echo str_repeat('-', 60) . "\n";
foreach ($partialMatches as $item) {
    echo "  ~ " . $item['old']['name'] . "\n";
    echo "    -> " . $item['ortak']['name'] . " ({$item['score']}% match)\n";
}

echo "\n\nNOT FOUND ON ORTAK.CH (" . count($notFound) . "):\n";
echo str_repeat('-', 60) . "\n";
foreach ($notFound as $product) {
    echo "  ✗ " . $product['name'] . "\n";
    echo "    Category: " . $product['category'] . " > " . $product['subcategory'] . "\n";
    echo "    Price: CHF " . $product['price'] . "\n";
    echo "    URL: " . $product['url'] . "\n\n";
}

// Summary by category
echo "\n=== SUMMARY BY CATEGORY ===\n\n";

$categoryStats = [];
foreach ($oldProducts as $product) {
    $cat = $product['category'];
    if (!isset($categoryStats[$cat])) {
        $categoryStats[$cat] = ['total' => 0, 'found' => 0, 'partial' => 0, 'missing' => 0];
    }
    $categoryStats[$cat]['total']++;
}

foreach ($found as $item) {
    $cat = $item['old']['category'];
    $categoryStats[$cat]['found']++;
}

foreach ($partialMatches as $item) {
    $cat = $item['old']['category'];
    $categoryStats[$cat]['partial']++;
}

foreach ($notFound as $product) {
    $cat = $product['category'];
    $categoryStats[$cat]['missing']++;
}

foreach ($categoryStats as $cat => $stats) {
    echo "$cat:\n";
    echo "  Total: {$stats['total']} | Found: {$stats['found']} | Partial: {$stats['partial']} | Missing: {$stats['missing']}\n";
}

// Save detailed report
$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'summary' => [
        'old_shop_total' => count($oldProducts),
        'ortak_total' => count($allOrtakProducts),
        'exact_matches' => count($found),
        'partial_matches' => count($partialMatches),
        'not_found' => count($notFound)
    ],
    'exact_matches' => $found,
    'partial_matches' => $partialMatches,
    'not_found' => $notFound,
    'category_stats' => $categoryStats
];

$reportFile = __DIR__ . '/old-shop-data/comparison-report.json';
file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n\nDetailed report saved to: comparison-report.json\n";

echo "\n=== DONE ===\n";
