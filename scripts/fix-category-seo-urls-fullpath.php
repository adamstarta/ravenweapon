<?php
/**
 * Fix Category SEO URLs - FULL PATH Version
 *
 * Problem: SEO URLs have short paths like /behoerden-dienst/polizeiausruestung/
 * Should be: /ausruestung/behoerden-dienst/polizeiausruestung/
 *
 * Solution: Delete ALL existing category SEO URLs and create new ones with FULL paths
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
    $replacements = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
        ' & ' => '-', '&' => '-', ' ' => '-', ',' => '', '/' => '-',
        '(' => '', ')' => '', "'" => '', '"' => '', '.' => ''
    ];

    $text = str_replace(array_keys($replacements), array_values($replacements), $text);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9-]/', '', $text);
    $text = preg_replace('/-+/', '-', $text);
    $text = trim($text, '-');

    return $text;
}

echo "=== Fix Category SEO URLs - FULL PATH ===\n\n";

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "Got API token\n\n";

// Step 1: Get sales channel
echo "1. Getting sales channel...\n";
$salesChannelResponse = apiRequest($baseUrl, $token, 'POST', '/search/sales-channel', [
    'limit' => 1,
    'includes' => ['sales_channel' => ['id', 'name']]
]);
$salesChannelId = $salesChannelResponse['body']['data'][0]['id'] ?? null;
echo "   Sales Channel: $salesChannelId\n\n";

// Step 2: Get ALL categories with breadcrumb
echo "2. Fetching all categories with breadcrumbs...\n";
$catResponse = apiRequest($baseUrl, $token, 'POST', '/search/category', [
    'limit' => 500,
    'includes' => [
        'category' => ['id', 'name', 'parentId', 'breadcrumb', 'translated', 'active', 'type', 'level']
    ]
]);

$categories = [];
foreach ($catResponse['body']['data'] as $cat) {
    $attrs = $cat['attributes'] ?? $cat;
    $breadcrumb = $attrs['translated']['breadcrumb'] ?? $attrs['breadcrumb'] ?? [];
    $name = $attrs['translated']['name'] ?? $attrs['name'] ?? '';
    $type = $attrs['type'] ?? 'page';

    // Skip root and non-page categories
    if (empty($breadcrumb) || count($breadcrumb) <= 1 || $type !== 'page') {
        continue;
    }

    // Build FULL path from breadcrumb (skip first item "Katalog #1")
    $pathParts = [];
    for ($i = 1; $i < count($breadcrumb); $i++) {
        $pathParts[] = slugify($breadcrumb[$i]);
    }

    $fullPath = implode('/', $pathParts) . '/';

    $categories[$cat['id']] = [
        'id' => $cat['id'],
        'name' => $name,
        'breadcrumb' => $breadcrumb,
        'fullPath' => $fullPath
    ];
}

echo "   Found " . count($categories) . " categories\n\n";

// Step 3: Delete ALL existing category SEO URLs
echo "3. Deleting ALL existing category SEO URLs...\n";
$page = 1;
$totalDeleted = 0;

while (true) {
    $seoResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
        'limit' => 100,
        'page' => 1, // Always page 1 since we're deleting
        'filter' => [
            ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
        ],
        'includes' => [
            'seo_url' => ['id', 'seoPathInfo', 'foreignKey']
        ]
    ]);

    if (!isset($seoResponse['body']['data']) || count($seoResponse['body']['data']) === 0) {
        break;
    }

    foreach ($seoResponse['body']['data'] as $url) {
        $response = apiRequest($baseUrl, $token, 'DELETE', '/seo-url/' . $url['id']);
        if ($response['code'] >= 200 && $response['code'] < 300) {
            $totalDeleted++;
        }
    }

    echo "   Deleted batch... (total: $totalDeleted)\n";

    // Safety check
    if ($totalDeleted > 500) {
        echo "   Safety limit reached\n";
        break;
    }
}

echo "   Total deleted: $totalDeleted\n\n";

// Step 4: Create new SEO URLs with FULL paths
echo "4. Creating SEO URLs with FULL paths...\n\n";
$created = 0;
$errors = 0;

foreach ($categories as $catId => $cat) {
    $seoUrlData = [
        'foreignKey' => $catId,
        'routeName' => 'frontend.navigation.page',
        'pathInfo' => '/navigation/' . $catId,
        'seoPathInfo' => $cat['fullPath'],
        'salesChannelId' => $salesChannelId,
        'isCanonical' => true,
        'isModified' => true,
        'isDeleted' => false
    ];

    $response = apiRequest($baseUrl, $token, 'POST', '/seo-url', $seoUrlData);

    if ($response['code'] >= 200 && $response['code'] < 300) {
        echo "   ✓ /" . $cat['fullPath'] . " (" . $cat['name'] . ")\n";
        $created++;
    } else {
        $error = $response['body']['errors'][0]['detail'] ?? 'Unknown';
        echo "   ✗ /" . $cat['fullPath'] . " - " . $error . "\n";
        $errors++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Deleted: $totalDeleted old URLs\n";
echo "Created: $created new URLs\n";
echo "Errors: $errors\n\n";

echo "=== SAMPLE URLS ===\n";
$samples = ['Polizeiausrüstung', 'Westen & Chest Rigs', 'K9 Ausrüstung', 'RX Sport', 'Zielfernrohre'];
foreach ($categories as $cat) {
    if (in_array($cat['name'], $samples)) {
        echo $cat['name'] . " => /" . $cat['fullPath'] . "\n";
    }
}

echo "\n=== NEXT STEPS ===\n";
echo "1. Clear cache:\n";
echo "   docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'\n\n";
echo "2. Test URLs like:\n";
echo "   https://ortak.ch/ausruestung/behoerden-dienst/polizeiausruestung/\n";
