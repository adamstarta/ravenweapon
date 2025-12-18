<?php
/**
 * Debug SEO URL state - check for duplicate URLs and canonical status
 */

$baseUrl = 'https://ortak.ch/api';
$clientId = 'SWIAC3HJVHFJMHQYRWRUM1E1SG';
$clientSecret = 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg';

function getAccessToken($baseUrl, $clientId, $clientSecret) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/oauth/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['access_token'];
}

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

echo "=== SEO URL Debug ===\n\n";

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "Got token\n\n";

// Get all category SEO URLs
echo "1. Fetching all category SEO URLs...\n";
$response = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
    'limit' => 500,
    'filter' => [
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
    ],
    'sort' => [
        ['field' => 'foreignKey', 'order' => 'ASC']
    ]
]);

if (!isset($response['data'])) {
    echo "Error fetching SEO URLs\n";
    print_r($response);
    exit(1);
}

$seoUrls = $response['data'];
echo "   Found " . count($seoUrls) . " category SEO URLs\n\n";

// Group by foreignKey (category ID)
$byCategory = [];
foreach ($seoUrls as $url) {
    $attrs = $url['attributes'] ?? $url;
    $foreignKey = $attrs['foreignKey'] ?? 'unknown';
    if (!isset($byCategory[$foreignKey])) {
        $byCategory[$foreignKey] = [];
    }
    $byCategory[$foreignKey][] = [
        'id' => $url['id'],
        'seoPathInfo' => $attrs['seoPathInfo'] ?? '',
        'isCanonical' => $attrs['isCanonical'] ?? false,
        'isDeleted' => $attrs['isDeleted'] ?? false,
        'salesChannelId' => $attrs['salesChannelId'] ?? ''
    ];
}

// Find categories with multiple URLs or non-canonical issues
echo "2. Analyzing categories with multiple or problematic URLs...\n\n";

$problemCategories = [];
foreach ($byCategory as $catId => $urls) {
    $hasMultiple = count($urls) > 1;
    $canonicalCount = 0;
    foreach ($urls as $u) {
        if ($u['isCanonical']) $canonicalCount++;
    }

    if ($hasMultiple || $canonicalCount != 1) {
        $problemCategories[$catId] = $urls;
    }
}

echo "   Categories with issues: " . count($problemCategories) . "\n\n";

// Get category names for context
$catNames = [];
if (!empty($problemCategories)) {
    $catIds = array_keys($problemCategories);
    $catResponse = apiRequest($baseUrl, $token, 'POST', '/search/category', [
        'limit' => 500,
        'filter' => [
            ['type' => 'equalsAny', 'field' => 'id', 'value' => $catIds]
        ]
    ]);
    if (isset($catResponse['data'])) {
        foreach ($catResponse['data'] as $cat) {
            $attrs = $cat['attributes'] ?? $cat;
            $catNames[$cat['id']] = $attrs['translated']['name'] ?? $attrs['name'] ?? 'Unknown';
        }
    }
}

foreach ($problemCategories as $catId => $urls) {
    $name = $catNames[$catId] ?? 'Unknown';
    echo "   [{$name}] (ID: {$catId})\n";
    foreach ($urls as $u) {
        $canonical = $u['isCanonical'] ? ' [CANONICAL]' : '';
        $deleted = $u['isDeleted'] ? ' [DELETED]' : '';
        echo "      - {$u['seoPathInfo']}{$canonical}{$deleted}\n";
        echo "        ID: {$u['id']}\n";
    }
    echo "\n";
}

// Check specific categories we know had issues
echo "3. Checking specific categories...\n\n";

$checkCategories = ['Körperschutz', 'Westen & Chest Rigs', 'Ausrüstung'];
$allCatsResponse = apiRequest($baseUrl, $token, 'POST', '/search/category', [
    'limit' => 500
]);

$targetCatIds = [];
if (isset($allCatsResponse['data'])) {
    foreach ($allCatsResponse['data'] as $cat) {
        $attrs = $cat['attributes'] ?? $cat;
        $name = $attrs['translated']['name'] ?? $attrs['name'] ?? '';
        if (in_array($name, $checkCategories)) {
            $targetCatIds[$cat['id']] = $name;
        }
    }
}

foreach ($targetCatIds as $catId => $catName) {
    echo "   [{$catName}]\n";

    // Get all SEO URLs for this category
    $urlResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
        'filter' => [
            ['type' => 'equals', 'field' => 'foreignKey', 'value' => $catId],
            ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
        ]
    ]);

    if (isset($urlResponse['data'])) {
        foreach ($urlResponse['data'] as $url) {
            $attrs = $url['attributes'] ?? $url;
            $canonical = ($attrs['isCanonical'] ?? false) ? ' [CANONICAL]' : '';
            $deleted = ($attrs['isDeleted'] ?? false) ? ' [DELETED]' : '';
            echo "      - {$attrs['seoPathInfo']}{$canonical}{$deleted}\n";
        }
    } else {
        echo "      No URLs found\n";
    }
    echo "\n";
}

// Check product SEO URL for our test product
echo "4. Checking test product SEO URLs...\n\n";
$productId = 'b044f1a17fdc110c7ed94d6123c51bf7'; // 3 row cummerbund 1.0

$productUrlResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
    'filter' => [
        ['type' => 'equals', 'field' => 'foreignKey', 'value' => $productId],
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.detail.page']
    ]
]);

echo "   Product: 3 row cummerbund 1.0\n";
if (isset($productUrlResponse['data'])) {
    foreach ($productUrlResponse['data'] as $url) {
        $attrs = $url['attributes'] ?? $url;
        $canonical = ($attrs['isCanonical'] ?? false) ? ' [CANONICAL]' : '';
        echo "      - {$attrs['seoPathInfo']}{$canonical}\n";
    }
} else {
    echo "      No URLs found\n";
}

echo "\n=== Done ===\n";
