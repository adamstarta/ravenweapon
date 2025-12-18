<?php
/**
 * Fix ALL Category SEO URLs
 *
 * Problem: Categories use navigation IDs instead of proper SEO URLs in menus
 * Solution: Create/update SEO URLs for ALL categories with full paths
 *
 * This script will:
 * 1. Get all categories with their breadcrumb paths
 * 2. Delete any existing SEO URLs (to avoid duplicates)
 * 3. Create new SEO URLs with full paths for each category
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
    // Convert German umlauts and special characters
    $replacements = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
        ' & ' => '-', '&' => '-', ' ' => '-', ',' => '', '/' => '-',
        '(' => '', ')' => '', "'" => '', '"' => '', '.' => ''
    ];

    $text = str_replace(array_keys($replacements), array_values($replacements), $text);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9-]/', '', $text);
    $text = preg_replace('/-+/', '-', $text); // Remove multiple dashes
    $text = trim($text, '-');

    return $text;
}

echo "=== Fix ALL Category SEO URLs ===\n\n";

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "Got API token\n\n";

// Step 1: Get the sales channel ID
echo "1. Getting sales channel...\n";
$salesChannelResponse = apiRequest($baseUrl, $token, 'POST', '/search/sales-channel', [
    'limit' => 1,
    'includes' => ['sales_channel' => ['id', 'name']]
]);
$salesChannelId = $salesChannelResponse['body']['data'][0]['id'] ?? null;

if (!$salesChannelId) {
    echo "Error: Could not find sales channel\n";
    exit(1);
}
echo "   Sales Channel ID: $salesChannelId\n\n";

// Step 2: Get ALL categories with breadcrumb
echo "2. Fetching all categories...\n";
$catResponse = apiRequest($baseUrl, $token, 'POST', '/search/category', [
    'limit' => 500,
    'includes' => [
        'category' => ['id', 'name', 'parentId', 'breadcrumb', 'translated', 'active', 'type', 'level']
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
    $level = $attrs['level'] ?? 1;

    // Skip root category and non-page categories
    if (empty($breadcrumb) || count($breadcrumb) <= 1 || $type !== 'page') {
        continue;
    }

    // Build full SEO path from breadcrumb (skip first item which is root "Katalog #1")
    $pathParts = [];
    for ($i = 1; $i < count($breadcrumb); $i++) {
        $pathParts[] = slugify($breadcrumb[$i]);
    }

    // Create the SEO path with trailing slash
    $fullPath = implode('/', $pathParts) . '/';

    $categories[$cat['id']] = [
        'id' => $cat['id'],
        'name' => $name,
        'breadcrumb' => $breadcrumb,
        'fullPath' => $fullPath,
        'depth' => count($pathParts),
        'level' => $level
    ];
}

echo "   Found " . count($categories) . " navigable categories\n\n";

// Step 3: Get existing SEO URLs for categories
echo "3. Fetching existing category SEO URLs...\n";
$existingSeoUrls = [];
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
        break;
    }

    $batch = $seoResponse['body']['data'];
    foreach ($batch as $url) {
        $attrs = $url['attributes'] ?? $url;
        $categoryId = $attrs['foreignKey'] ?? $url['foreignKey'] ?? null;
        $path = $attrs['seoPathInfo'] ?? '';
        $isDeleted = $attrs['isDeleted'] ?? false;

        if ($categoryId && !$isDeleted) {
            if (!isset($existingSeoUrls[$categoryId])) {
                $existingSeoUrls[$categoryId] = [];
            }
            $existingSeoUrls[$categoryId][] = [
                'id' => $url['id'],
                'path' => $path
            ];
        }
    }

    if (count($batch) < $limit) break;
    $page++;
}

echo "   Found existing SEO URLs for " . count($existingSeoUrls) . " categories\n\n";

// Step 4: Analyze and categorize
echo "4. Analyzing categories...\n\n";

$toCreate = [];
$toDelete = [];
$alreadyCorrect = [];

foreach ($categories as $catId => $cat) {
    $existing = $existingSeoUrls[$catId] ?? [];
    $desiredPath = $cat['fullPath'];

    $hasCorrectUrl = false;
    foreach ($existing as $url) {
        if ($url['path'] === $desiredPath) {
            $hasCorrectUrl = true;
            $alreadyCorrect[] = $cat['name'];
        } else {
            // Mark incorrect URLs for deletion
            $toDelete[] = [
                'id' => $url['id'],
                'path' => $url['path'],
                'categoryName' => $cat['name']
            ];
        }
    }

    if (!$hasCorrectUrl) {
        $toCreate[] = [
            'categoryId' => $catId,
            'categoryName' => $cat['name'],
            'path' => $desiredPath
        ];
    }
}

echo "=== SUMMARY ===\n";
echo "Categories with correct SEO URLs: " . count($alreadyCorrect) . "\n";
echo "SEO URLs to CREATE: " . count($toCreate) . "\n";
echo "Incorrect URLs to DELETE: " . count($toDelete) . "\n\n";

// Step 5: Delete incorrect URLs first
if (count($toDelete) > 0) {
    echo "5. Deleting incorrect/duplicate URLs...\n\n";
    $deleted = 0;

    foreach ($toDelete as $url) {
        $response = apiRequest($baseUrl, $token, 'DELETE', '/seo-url/' . $url['id']);
        if ($response['code'] >= 200 && $response['code'] < 300) {
            echo "   ✓ Deleted: " . $url['path'] . " (" . $url['categoryName'] . ")\n";
            $deleted++;
        } else {
            echo "   ✗ Failed to delete: " . $url['path'] . " (HTTP " . $response['code'] . ")\n";
        }
    }
    echo "\n   Deleted $deleted URLs\n\n";
}

// Step 6: Create new SEO URLs
if (count($toCreate) > 0) {
    echo "6. Creating correct SEO URLs...\n\n";
    $created = 0;
    $errors = 0;

    foreach ($toCreate as $item) {
        $seoUrlData = [
            'foreignKey' => $item['categoryId'],
            'routeName' => 'frontend.navigation.page',
            'pathInfo' => '/navigation/' . $item['categoryId'],
            'seoPathInfo' => $item['path'],
            'salesChannelId' => $salesChannelId,
            'isCanonical' => true,
            'isModified' => true, // Prevent Shopware from overwriting
            'isDeleted' => false
        ];

        $response = apiRequest($baseUrl, $token, 'POST', '/seo-url', $seoUrlData);

        if ($response['code'] >= 200 && $response['code'] < 300) {
            echo "   ✓ Created: " . $item['path'] . " (" . $item['categoryName'] . ")\n";
            $created++;
        } else {
            $error = $response['body']['errors'][0]['detail'] ?? 'Unknown error';
            // If duplicate, try to update instead
            if (strpos($error, 'Duplicate') !== false) {
                echo "   ~ Exists:  " . $item['path'] . " (already exists)\n";
            } else {
                echo "   ✗ Failed:  " . $item['path'] . " - " . $error . "\n";
                $errors++;
            }
        }
    }

    echo "\n   Created: $created | Errors: $errors\n\n";
}

// Step 7: List all categories with their SEO URLs
echo "=== CATEGORY SEO URL MAPPING ===\n\n";

// Sort categories by depth for cleaner output
uasort($categories, function($a, $b) {
    return $a['depth'] - $b['depth'];
});

foreach ($categories as $catId => $cat) {
    $status = in_array($cat['name'], $alreadyCorrect) ? '✓' : '→';
    echo "$status " . str_repeat('  ', $cat['depth'] - 1) . $cat['name'] . "\n";
    echo "  " . str_repeat('  ', $cat['depth'] - 1) . "URL: /" . $cat['fullPath'] . "\n";
    echo "  " . str_repeat('  ', $cat['depth'] - 1) . "ID:  " . $catId . "\n\n";
}

echo "=== COMPLETE ===\n\n";
echo "Next steps:\n";
echo "1. Clear cache on server:\n";
echo "   docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'\n\n";
echo "2. Test navigation menus to verify SEO URLs work\n";
