<?php
/**
 * Fix Category SEO URLs - Delete short/duplicate URLs
 *
 * Problem: Categories have duplicate SEO URLs:
 *   - Full path: /ausruestung/behoerden-dienst/polizeiausruestung/
 *   - Short path: /behoerden-dienst/polizeiausruestung/
 *
 * Solution: Delete short URLs, keep only full path URLs
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

echo "=== Fix Category SEO URLs ===\n\n";

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "Got token\n\n";

// Step 1: Get all category SEO URLs (with pagination)
echo "1. Fetching all category SEO URLs...\n";
$seoUrls = [];
$page = 1;
$limit = 500;

while (true) {
    $seoResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
        'limit' => $limit,
        'page' => $page,
        'filter' => [
            ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
        ],
        'includes' => [
            'seo_url' => ['id', 'seoPathInfo', 'foreignKey', 'isCanonical', 'isDeleted']
        ]
    ]);

    if (!isset($seoResponse['body']['data'])) {
        echo "Error fetching SEO URLs: " . print_r($seoResponse, true) . "\n";
        exit(1);
    }

    $batch = $seoResponse['body']['data'];
    $seoUrls = array_merge($seoUrls, $batch);
    echo "   Page $page: fetched " . count($batch) . " URLs\n";

    if (count($batch) < $limit) {
        break; // Last page
    }
    $page++;
}

echo "   Total: " . count($seoUrls) . " category SEO URLs\n\n";

// Step 2: Group by category ID (foreignKey)
$urlsByCategory = [];
foreach ($seoUrls as $url) {
    $attrs = $url['attributes'] ?? $url;
    $categoryId = $attrs['foreignKey'] ?? $url['foreignKey'] ?? null;
    $path = $attrs['seoPathInfo'] ?? '';
    $isDeleted = $attrs['isDeleted'] ?? false;

    if (!$categoryId || $isDeleted) continue;

    if (!isset($urlsByCategory[$categoryId])) {
        $urlsByCategory[$categoryId] = [];
    }

    $urlsByCategory[$categoryId][] = [
        'id' => $url['id'],
        'path' => $path,
        'segments' => count(array_filter(explode('/', trim($path, '/'))))
    ];
}

echo "2. Found " . count($urlsByCategory) . " categories with SEO URLs\n\n";

// Step 3: Find categories with multiple URLs (duplicates)
$duplicates = [];
foreach ($urlsByCategory as $catId => $urls) {
    if (count($urls) > 1) {
        $duplicates[$catId] = $urls;
    }
}

echo "3. Categories with duplicate URLs: " . count($duplicates) . "\n\n";

if (count($duplicates) === 0) {
    echo "No duplicates found. Nothing to fix.\n";
    exit(0);
}

// Step 4: For each category with duplicates, keep longest path, delete shorter ones
echo "4. Analyzing duplicates and marking for deletion...\n\n";

$toDelete = [];
$toKeep = [];

foreach ($duplicates as $catId => $urls) {
    // Sort by segment count descending (longest first)
    usort($urls, function($a, $b) {
        return $b['segments'] - $a['segments'];
    });

    // Keep the longest (first after sort)
    $keep = array_shift($urls);
    $toKeep[] = $keep;

    // Delete the rest
    foreach ($urls as $url) {
        $toDelete[] = $url;
    }

    echo "   Category $catId:\n";
    echo "     KEEP:   " . $keep['path'] . " (" . $keep['segments'] . " segments)\n";
    foreach ($urls as $url) {
        echo "     DELETE: " . $url['path'] . " (" . $url['segments'] . " segments)\n";
    }
    echo "\n";
}

echo "=== SUMMARY ===\n";
echo "URLs to KEEP:   " . count($toKeep) . "\n";
echo "URLs to DELETE: " . count($toDelete) . "\n\n";

if (count($toDelete) === 0) {
    echo "Nothing to delete.\n";
    exit(0);
}

// Step 5: Delete the short URLs
echo "5. Deleting short/duplicate URLs...\n\n";

$deleted = 0;
$errors = 0;

foreach ($toDelete as $url) {
    $response = apiRequest($baseUrl, $token, 'DELETE', '/seo-url/' . $url['id']);

    if ($response['code'] >= 200 && $response['code'] < 300) {
        echo "   ✓ Deleted: " . $url['path'] . "\n";
        $deleted++;
    } else {
        echo "   ✗ Failed to delete: " . $url['path'] . " (HTTP " . $response['code'] . ")\n";
        $errors++;
    }
}

echo "\n=== COMPLETE ===\n";
echo "Deleted: $deleted\n";
echo "Errors: $errors\n";
echo "\nRemember to clear cache on the server:\n";
echo "  docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'\n";
