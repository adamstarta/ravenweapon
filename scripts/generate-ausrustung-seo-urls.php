<?php
/**
 * Generate SEO URLs for restructured Ausrüstung categories
 */

$API_URL = 'https://ortak.ch';

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'client_credentials',
            'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
            'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

echo "=== GENERATING SEO URLS FOR AUSRÜSTUNG CATEGORIES ===\n\n";

$token = getToken($API_URL);
if (!$token) {
    die("Failed to authenticate!\n");
}
echo "✓ Authenticated\n\n";

// Get all categories with their paths
$result = apiRequest($API_URL, $token, 'POST', 'search/category', [
    'limit' => 500,
    'associations' => [
        'seoUrls' => []
    ]
]);

$categories = [];
$ausrustungId = null;
$parentCategories = [];

foreach ($result['data'] ?? [] as $cat) {
    $id = $cat['id'];
    $name = $cat['attributes']['name'] ?? $cat['name'] ?? 'Unknown';
    $parentId = $cat['attributes']['parentId'] ?? $cat['parentId'] ?? null;
    $level = $cat['attributes']['level'] ?? $cat['level'] ?? 0;

    $categories[$id] = [
        'id' => $id,
        'name' => $name,
        'parentId' => $parentId,
        'level' => $level
    ];

    if (stripos($name, 'Ausrüstung') !== false && $level == 2) {
        $ausrustungId = $id;
    }

    // Track new parent categories
    if (in_array($name, ['Taschen & Transport', 'Körperschutz', 'Bekleidung & Tragen', 'Spezialausrüstung', 'Behörden & Dienst', 'Zubehör']) && $parentId == $ausrustungId) {
        $parentCategories[$name] = $id;
    }
}

if (!$ausrustungId) {
    die("ERROR: Ausrüstung category not found!\n");
}

echo "=== CURRENT SEO URLS FOR NEW PARENT CATEGORIES ===\n\n";

// Check existing SEO URLs
foreach ($parentCategories as $name => $id) {
    $seoResult = apiRequest($API_URL, $token, 'POST', 'search/seo-url', [
        'filter' => [
            ['type' => 'equals', 'field' => 'foreignKey', 'value' => $id]
        ],
        'limit' => 5
    ]);

    $seoUrls = [];
    foreach ($seoResult['data'] ?? [] as $seo) {
        $seoUrls[] = $seo['attributes']['seoPathInfo'] ?? $seo['seoPathInfo'] ?? 'N/A';
    }

    echo "$name ($id):\n";
    if (empty($seoUrls)) {
        echo "  - No SEO URLs found\n";
    } else {
        foreach ($seoUrls as $url) {
            echo "  - /$url\n";
        }
    }
}

// Trigger SEO URL generation via cache clear and index rebuild
echo "\n=== TRIGGERING SEO URL GENERATION ===\n";

// Clear cache
$cacheResponse = apiRequest($API_URL, $token, 'DELETE', '_action/cache');
echo "✓ Cache cleared\n";

// Trigger SEO URL indexer
$indexerResponse = apiRequest($API_URL, $token, 'POST', '_action/indexing/seo-url');
if (isset($indexerResponse['errors'])) {
    echo "SEO URL indexer: " . json_encode($indexerResponse['errors']) . "\n";
} else {
    echo "✓ SEO URL indexer triggered\n";
}

echo "\n=== VERIFYING NEW SEO URLS ===\n\n";

// Wait a moment for indexer
sleep(2);

// Check SEO URLs again
foreach ($parentCategories as $name => $id) {
    $seoResult = apiRequest($API_URL, $token, 'POST', 'search/seo-url', [
        'filter' => [
            ['type' => 'equals', 'field' => 'foreignKey', 'value' => $id]
        ],
        'limit' => 5
    ]);

    $seoUrls = [];
    foreach ($seoResult['data'] ?? [] as $seo) {
        $seoUrls[] = $seo['attributes']['seoPathInfo'] ?? $seo['seoPathInfo'] ?? 'N/A';
    }

    echo "$name:\n";
    if (empty($seoUrls)) {
        echo "  - No SEO URLs yet (may need more time)\n";
    } else {
        foreach ($seoUrls as $url) {
            echo "  - /$url\n";
        }
    }
}

echo "\n=== DONE ===\n";
echo "\nNote: SEO URLs may take a few minutes to fully generate.\n";
echo "Check the Shopware admin under Settings > SEO to verify URLs.\n";
