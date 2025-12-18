<?php
/**
 * Fix SEO URL canonical status
 * - Delete old incorrect URLs
 * - Set correct URLs as canonical
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

function generateSeoPath($seoBreadcrumb) {
    $sliced = array_slice($seoBreadcrumb, 1);

    $parts = [];
    foreach ($sliced as $part) {
        $slug = $part;
        $slug = str_replace(
            ['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'],
            ['ae', 'oe', 'ue', 'ss', 'ae', 'oe', 'ue'],
            $slug
        );
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        $parts[] = $slug;
    }

    return implode('/', $parts) . '/';
}

echo "=== SEO URL Canonical Fix ===\n\n";

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "Got token\n\n";

// Step 1: Get all categories with their seoBreadcrumb
echo "1. Fetching all categories...\n";
$response = apiRequest($baseUrl, $token, 'POST', '/search/category', [
    'limit' => 500
]);

if (!isset($response['body']['data'])) {
    echo "Error:\n";
    print_r($response);
    exit(1);
}

$categories = [];
foreach ($response['body']['data'] as $cat) {
    $attrs = $cat['attributes'] ?? $cat;
    $seoBreadcrumb = $attrs['translated']['breadcrumb'] ?? $attrs['breadcrumb'] ?? [];

    $categories[$cat['id']] = [
        'id' => $cat['id'],
        'name' => $attrs['translated']['name'] ?? $attrs['name'] ?? 'Unknown',
        'level' => $attrs['level'] ?? 0,
        'seoBreadcrumb' => $seoBreadcrumb
    ];
}
echo "   Found " . count($categories) . " categories\n\n";

// Build expected paths for all categories
$expectedPaths = [];
foreach ($categories as $catId => $cat) {
    if ($cat['level'] >= 2 && !empty($cat['seoBreadcrumb'])) {
        $expectedPaths[$catId] = generateSeoPath($cat['seoBreadcrumb']);
    }
}

// Step 2: Get all category SEO URLs
echo "2. Fetching all category SEO URLs...\n";
$seoResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
    'limit' => 500,
    'filter' => [
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
    ]
]);

$seoUrls = [];
if (isset($seoResponse['body']['data'])) {
    foreach ($seoResponse['body']['data'] as $url) {
        $attrs = $url['attributes'] ?? $url;
        $seoUrls[] = [
            'id' => $url['id'],
            'foreignKey' => $attrs['foreignKey'] ?? '',
            'seoPathInfo' => $attrs['seoPathInfo'] ?? '',
            'isCanonical' => $attrs['isCanonical'] ?? false,
            'isDeleted' => $attrs['isDeleted'] ?? false
        ];
    }
}
echo "   Found " . count($seoUrls) . " category SEO URLs\n\n";

// Step 3: Determine which URLs to delete and which to keep
echo "3. Analyzing URLs...\n\n";

$toDelete = [];
$toSetCanonical = [];

foreach ($seoUrls as $url) {
    $catId = $url['foreignKey'];

    if (!isset($expectedPaths[$catId])) {
        continue; // Skip root categories
    }

    $expectedPath = $expectedPaths[$catId];
    $catName = $categories[$catId]['name'] ?? 'Unknown';

    if ($url['seoPathInfo'] === $expectedPath) {
        // This is the correct URL
        if (!$url['isCanonical']) {
            $toSetCanonical[] = [
                'id' => $url['id'],
                'category' => $catName,
                'path' => $url['seoPathInfo']
            ];
        }
    } else {
        // This is an incorrect URL - delete it
        $toDelete[] = [
            'id' => $url['id'],
            'category' => $catName,
            'path' => $url['seoPathInfo'],
            'expected' => $expectedPath
        ];
    }
}

echo "   URLs to delete: " . count($toDelete) . "\n";
echo "   URLs to set as canonical: " . count($toSetCanonical) . "\n\n";

// Step 4: Delete incorrect URLs
if (!empty($toDelete)) {
    echo "4. Deleting incorrect URLs...\n\n";

    $deleted = 0;
    $deleteErrors = 0;

    foreach ($toDelete as $url) {
        echo "   Deleting: {$url['category']} ({$url['path']})\n";
        echo "            Expected: {$url['expected']}\n";

        $deleteResponse = apiRequest($baseUrl, $token, 'DELETE', '/seo-url/' . $url['id']);

        if ($deleteResponse['code'] === 204) {
            $deleted++;
            echo "      OK\n";
        } else {
            $deleteErrors++;
            echo "      ERROR: " . substr($deleteResponse['raw'], 0, 100) . "\n";
        }
    }

    echo "\n   Deleted: {$deleted}, Errors: {$deleteErrors}\n\n";
} else {
    echo "4. No URLs to delete\n\n";
}

// Step 5: Set correct URLs as canonical
if (!empty($toSetCanonical)) {
    echo "5. Setting canonical URLs...\n\n";

    $updated = 0;
    $updateErrors = 0;

    foreach ($toSetCanonical as $url) {
        echo "   Setting canonical: {$url['category']} ({$url['path']})\n";

        $updateResponse = apiRequest($baseUrl, $token, 'PATCH', '/seo-url/' . $url['id'], [
            'isCanonical' => true
        ]);

        if ($updateResponse['code'] === 204 || $updateResponse['code'] === 200) {
            $updated++;
            echo "      OK\n";
        } else {
            $updateErrors++;
            echo "      ERROR: " . substr($updateResponse['raw'], 0, 100) . "\n";
        }
    }

    echo "\n   Updated: {$updated}, Errors: {$updateErrors}\n\n";
} else {
    echo "5. No URLs to set as canonical\n\n";
}

echo "=== Done ===\n";
echo "\nPlease clear the cache: docker exec shopware-chf bin/console cache:clear\n";
