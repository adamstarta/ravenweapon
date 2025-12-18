<?php
/**
 * Create Full Path Category SEO URLs
 *
 * Problem: Categories have short SEO URLs or no SEO URLs at all.
 * Solution: Create proper full-path SEO URLs for ALL categories.
 *
 * Example: "Behörden & Dienst" under "Ausrüstung" should have URL:
 *   /ausruestung/behoerden-dienst/
 * Not just:
 *   /behoerden-dienst/
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

function slugify($text) {
    // Convert German umlauts
    $replacements = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
        ' & ' => '-', '&' => '-', ' ' => '-', ',' => '', '/' => '-',
        '(' => '', ')' => '', "'" => '', '"' => ''
    ];

    $text = str_replace(array_keys($replacements), array_values($replacements), $text);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9-]/', '', $text);
    $text = preg_replace('/-+/', '-', $text); // Remove multiple dashes
    $text = trim($text, '-');

    return $text;
}

echo "=== Create Category SEO URLs ===\n\n";

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "Got token\n\n";

// Step 1: Get all categories with their breadcrumb paths
echo "1. Fetching all categories...\n";
$catResponse = apiRequest($baseUrl, $token, 'POST', '/search/category', [
    'limit' => 500,
    'includes' => [
        'category' => ['id', 'name', 'parentId', 'breadcrumb', 'translated', 'active', 'type']
    ]
]);

if (!isset($catResponse['body']['data'])) {
    echo "Error fetching categories: " . print_r($catResponse, true) . "\n";
    exit(1);
}

$categories = [];
foreach ($catResponse['body']['data'] as $cat) {
    $attrs = $cat['attributes'] ?? $cat;
    $breadcrumb = $attrs['translated']['breadcrumb'] ?? $attrs['breadcrumb'] ?? [];
    $name = $attrs['translated']['name'] ?? $attrs['name'] ?? '';
    $type = $attrs['type'] ?? 'page';

    // Skip root category and non-page categories
    if (empty($breadcrumb) || count($breadcrumb) <= 1 || $type !== 'page') {
        continue;
    }

    // Build full SEO path from breadcrumb (skip first item which is root)
    $pathParts = [];
    for ($i = 1; $i < count($breadcrumb); $i++) {
        $pathParts[] = slugify($breadcrumb[$i]);
    }

    $fullPath = implode('/', $pathParts) . '/';

    $categories[$cat['id']] = [
        'id' => $cat['id'],
        'name' => $name,
        'breadcrumb' => $breadcrumb,
        'fullPath' => $fullPath,
        'depth' => count($pathParts)
    ];
}

echo "   Found " . count($categories) . " navigable categories\n\n";

// Step 2: Get existing SEO URLs for categories
echo "2. Fetching existing category SEO URLs...\n";
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
            'seo_url' => ['id', 'seoPathInfo', 'foreignKey', 'isCanonical', 'isDeleted', 'salesChannelId']
        ]
    ]);

    if (!isset($seoResponse['body']['data'])) {
        echo "Error fetching SEO URLs\n";
        break;
    }

    $batch = $seoResponse['body']['data'];
    foreach ($batch as $url) {
        $attrs = $url['attributes'] ?? $url;
        $categoryId = $attrs['foreignKey'] ?? $url['foreignKey'] ?? null;
        $path = $attrs['seoPathInfo'] ?? '';
        $isDeleted = $attrs['isDeleted'] ?? false;
        $salesChannelId = $attrs['salesChannelId'] ?? null;

        if ($categoryId && !$isDeleted) {
            if (!isset($seoUrls[$categoryId])) {
                $seoUrls[$categoryId] = [];
            }
            $seoUrls[$categoryId][] = [
                'id' => $url['id'],
                'path' => $path,
                'salesChannelId' => $salesChannelId
            ];
        }
    }

    if (count($batch) < $limit) break;
    $page++;
}

echo "   Found SEO URLs for " . count($seoUrls) . " categories\n\n";

// Step 3: Identify categories that need SEO URLs created/updated
echo "3. Analyzing categories...\n\n";

$toCreate = [];
$toDelete = [];
$existing = [];

// Get the default sales channel ID (we need this for creating SEO URLs)
$salesChannelResponse = apiRequest($baseUrl, $token, 'POST', '/search/sales-channel', [
    'limit' => 1,
    'includes' => ['sales_channel' => ['id']]
]);
$salesChannelId = $salesChannelResponse['body']['data'][0]['id'] ?? null;

if (!$salesChannelId) {
    echo "Error: Could not find sales channel\n";
    exit(1);
}

echo "   Using Sales Channel: $salesChannelId\n\n";

foreach ($categories as $catId => $cat) {
    $existingUrls = $seoUrls[$catId] ?? [];
    $fullPath = $cat['fullPath'];

    $hasFullPath = false;
    $shortUrls = [];

    foreach ($existingUrls as $url) {
        if ($url['path'] === $fullPath) {
            $hasFullPath = true;
            $existing[] = ['category' => $cat['name'], 'path' => $fullPath];
        } else {
            // This is a short URL that should be deleted
            $shortUrls[] = $url;
        }
    }

    if (!$hasFullPath) {
        $toCreate[] = [
            'categoryId' => $catId,
            'categoryName' => $cat['name'],
            'path' => $fullPath,
            'salesChannelId' => $salesChannelId
        ];
    }

    // Mark short URLs for deletion
    foreach ($shortUrls as $url) {
        $toDelete[] = [
            'id' => $url['id'],
            'path' => $url['path'],
            'categoryName' => $cat['name']
        ];
    }
}

echo "=== SUMMARY ===\n";
echo "Categories with correct full-path URLs: " . count($existing) . "\n";
echo "SEO URLs to CREATE: " . count($toCreate) . "\n";
echo "Short URLs to DELETE: " . count($toDelete) . "\n\n";

if (count($toCreate) === 0 && count($toDelete) === 0) {
    echo "Nothing to do. All categories have correct SEO URLs.\n";
    exit(0);
}

// Step 4: Delete short URLs
if (count($toDelete) > 0) {
    echo "4. Deleting short URLs...\n\n";
    $deleted = 0;
    foreach ($toDelete as $url) {
        $response = apiRequest($baseUrl, $token, 'DELETE', '/seo-url/' . $url['id']);
        if ($response['code'] >= 200 && $response['code'] < 300) {
            echo "   ✓ Deleted: " . $url['path'] . " (" . $url['categoryName'] . ")\n";
            $deleted++;
        } else {
            echo "   ✗ Failed: " . $url['path'] . " (HTTP " . $response['code'] . ")\n";
        }
    }
    echo "\n   Deleted $deleted short URLs\n\n";
}

// Step 5: Create full-path URLs
if (count($toCreate) > 0) {
    echo "5. Creating full-path SEO URLs...\n\n";
    $created = 0;

    foreach ($toCreate as $item) {
        $seoUrlData = [
            'foreignKey' => $item['categoryId'],
            'routeName' => 'frontend.navigation.page',
            'pathInfo' => '/navigation/' . $item['categoryId'],
            'seoPathInfo' => $item['path'],
            'salesChannelId' => $item['salesChannelId'],
            'isCanonical' => true,
            'isModified' => true, // Mark as modified so it won't be overwritten by indexer
            'isDeleted' => false
        ];

        $response = apiRequest($baseUrl, $token, 'POST', '/seo-url', $seoUrlData);

        if ($response['code'] >= 200 && $response['code'] < 300) {
            echo "   ✓ Created: " . $item['path'] . " (" . $item['categoryName'] . ")\n";
            $created++;
        } else {
            echo "   ✗ Failed: " . $item['path'] . " - " . ($response['body']['errors'][0]['detail'] ?? 'Unknown error') . "\n";
        }
    }

    echo "\n   Created $created SEO URLs\n\n";
}

echo "=== COMPLETE ===\n";
echo "\nRemember to clear cache:\n";
echo "  docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'\n";
