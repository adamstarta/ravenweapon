<?php
/**
 * Regenerate SEO URLs for all categories using Shopware API
 * This will force Shopware to create new SEO URLs based on the template
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response
    ];
}

echo "=== SEO URL Regenerator ===\n\n";

// Step 1: Get token
echo "1. Getting access token...\n";
$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "   Got token\n\n";

// Step 2: Get all categories with their seoUrls and breadcrumb info
echo "2. Fetching all categories...\n";
$response = apiRequest($baseUrl, $token, 'POST', '/search/category', [
    'limit' => 500,
    'associations' => [
        'seoUrls' => [
            'limit' => 10
        ]
    ]
]);

// Debug: show first category raw data
echo "   Debug - First category structure:\n";
if (!empty($response['body']['data'])) {
    echo "   Keys: " . json_encode(array_keys($response['body']['data'][0]), JSON_PRETTY_PRINT) . "\n";
    if (isset($response['body']['data'][0]['attributes'])) {
        echo "   Attributes keys: " . json_encode(array_keys($response['body']['data'][0]['attributes']), JSON_PRETTY_PRINT) . "\n";
    }
    echo "\n";
}

if (!isset($response['body']['data'])) {
    echo "Error fetching categories:\n";
    print_r($response);
    exit(1);
}

$categories = $response['body']['data'];
echo "   Found " . count($categories) . " categories\n\n";

// Build lookup by ID - handle JSON API format
$byId = [];
foreach ($categories as $cat) {
    // Handle JSON API format where data is in 'attributes'
    $attrs = $cat['attributes'] ?? $cat;

    $name = $attrs['translated']['name'] ?? $attrs['name'] ?? 'Unknown';

    // Get seoUrls - may be nested differently in API response
    $seoUrls = [];
    if (isset($attrs['seoUrls']['elements'])) {
        $seoUrls = $attrs['seoUrls']['elements'];
    } elseif (isset($attrs['seoUrls']) && is_array($attrs['seoUrls'])) {
        $seoUrls = $attrs['seoUrls'];
    }
    // Check relationships too (JSON API format)
    if (empty($seoUrls) && isset($cat['relationships']['seoUrls']['data'])) {
        $seoUrls = $cat['relationships']['seoUrls']['data'];
    }

    $catId = $cat['id'] ?? $attrs['id'];

    $byId[$catId] = [
        'id' => $catId,
        'name' => $name,
        'parentId' => $attrs['parentId'] ?? null,
        'level' => $attrs['level'] ?? 0,
        'path' => $attrs['path'] ?? '',
        'seoUrls' => $seoUrls
    ];
}

// Step 3: Find categories with problematic SEO URLs (missing parent path)
echo "3. Analyzing SEO URLs...\n\n";

// First, show ALL SEO URLs for categories we care about
echo "   === All Category SEO URLs ===\n";
foreach ($byId as $catId => $cat) {
    if ($cat['level'] < 2) continue; // Skip root

    echo "\n   [{$cat['level']}] {$cat['name']}\n";
    echo "       ID: {$cat['id']}\n";

    if (empty($cat['seoUrls'])) {
        echo "       SEO URLs: NONE\n";
    } else {
        foreach ($cat['seoUrls'] as $seoUrl) {
            $canonical = ($seoUrl['isCanonical'] ?? false) ? ' [CANONICAL]' : '';
            echo "       SEO URL: {$seoUrl['seoPathInfo']}$canonical\n";
        }
    }
}
echo "\n   === End of All URLs ===\n\n";

$problematic = [];
foreach ($byId as $catId => $cat) {
    if ($cat['level'] < 3) continue; // Skip root and main categories

    // Get canonical SEO URL
    $canonicalUrl = null;
    foreach ($cat['seoUrls'] as $seoUrl) {
        if ($seoUrl['isCanonical'] ?? false) {
            $canonicalUrl = $seoUrl['seoPathInfo'];
            break;
        }
    }

    if (!$canonicalUrl && !empty($cat['seoUrls'])) {
        $canonicalUrl = $cat['seoUrls'][0]['seoPathInfo'] ?? null;
    }

    if (!$canonicalUrl) {
        echo "   [WARNING] No SEO URL found for: {$cat['name']} (ID: $catId)\n";
        continue;
    }

    // Get parent name from path
    $parentId = $cat['parentId'];
    $parentName = isset($byId[$parentId]) ? $byId[$parentId]['name'] : null;

    // Check if URL contains parent path (lowercase, converted)
    if ($parentName) {
        $parentSlug = strtolower(str_replace([' ', '/', 'ä', 'ö', 'ü', 'ß'], ['-', '-', 'ae', 'oe', 'ue', 'ss'], $parentName));

        if (strpos(strtolower($canonicalUrl), $parentSlug) === false) {
            echo "   [PROBLEM] {$cat['name']}\n";
            echo "             URL: $canonicalUrl\n";
            echo "             Missing parent: $parentName ($parentSlug)\n";
            $problematic[$catId] = $cat;
        } else {
            echo "   [OK] {$cat['name']} -> $canonicalUrl\n";
        }
    }
}

echo "\n";

if (empty($problematic)) {
    echo "=== All SEO URLs look correct! ===\n";
    exit(0);
}

echo "4. Found " . count($problematic) . " categories with problematic URLs\n\n";

// Step 4: Use the _action/index endpoint to regenerate SEO URLs
echo "5. Triggering SEO URL regeneration via indexer...\n";

// The proper way to regenerate SEO URLs is through the indexer API
$indexResponse = apiRequest($baseUrl, $token, 'POST', '/_action/indexing/seo.url.indexer', []);

if ($indexResponse['code'] === 200 || $indexResponse['code'] === 204) {
    echo "   Indexer triggered successfully\n";
} else {
    echo "   Indexer response code: " . $indexResponse['code'] . "\n";
    echo "   Response: " . substr($indexResponse['raw'], 0, 500) . "\n";
}

echo "\n=== Done ===\n";
echo "Please clear the cache and check the URLs again.\n";
