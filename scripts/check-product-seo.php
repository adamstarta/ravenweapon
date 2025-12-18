<?php
/**
 * Check Product SEO URL - "Covert equipment vest -12"
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

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "=== Product SEO URL Check ===\n\n";

// Find product "Covert equipment vest -12"
$productResponse = apiRequest($baseUrl, $token, 'POST', '/search/product', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'Covert equipment vest -12']
    ],
    'includes' => [
        'product' => ['id', 'name', 'productNumber']
    ]
]);

$productId = $productResponse['data'][0]['id'] ?? null;
$productName = $productResponse['data'][0]['attributes']['name'] ?? $productResponse['data'][0]['name'] ?? 'Unknown';

echo "Product: $productName\n";
echo "Product ID: $productId\n\n";

if ($productId) {
    // Get SEO URLs for this product
    $seoResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
        'filter' => [
            ['type' => 'equals', 'field' => 'foreignKey', 'value' => $productId],
            ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.detail.page']
        ],
        'includes' => [
            'seo_url' => ['id', 'seoPathInfo', 'isCanonical', 'isDeleted', 'isModified']
        ]
    ]);

    echo "SEO URLs for this product:\n";
    if (isset($seoResponse['data']) && count($seoResponse['data']) > 0) {
        foreach ($seoResponse['data'] as $url) {
            $attrs = $url['attributes'] ?? $url;
            $path = $attrs['seoPathInfo'] ?? '';
            $canonical = $attrs['isCanonical'] ?? false;
            $deleted = $attrs['isDeleted'] ?? false;

            echo "  - /$path\n";
            echo "    Canonical: " . ($canonical ? 'YES' : 'no') . ", Deleted: " . ($deleted ? 'YES' : 'no') . "\n";
            echo "    ID: " . $url['id'] . "\n\n";
        }
    } else {
        echo "  No SEO URLs found!\n";
    }

    // Get product categories
    $catResponse = apiRequest($baseUrl, $token, 'POST', '/search/product', [
        'filter' => [
            ['type' => 'equals', 'field' => 'id', 'value' => $productId]
        ],
        'associations' => [
            'categories' => []
        ],
        'includes' => [
            'product' => ['id', 'categories'],
            'category' => ['id', 'name', 'breadcrumb', 'translated']
        ]
    ]);

    echo "Assigned Categories:\n";
    if (isset($catResponse['data'][0]['categories']) && count($catResponse['data'][0]['categories']) > 0) {
        foreach ($catResponse['data'][0]['categories'] as $cat) {
            $catAttrs = $cat['attributes'] ?? $cat;
            $catName = $catAttrs['translated']['name'] ?? $catAttrs['name'] ?? 'Unknown';
            $breadcrumb = $catAttrs['translated']['breadcrumb'] ?? $catAttrs['breadcrumb'] ?? [];
            echo "  - $catName\n";
            echo "    Breadcrumb: " . implode(' / ', $breadcrumb) . "\n";
            echo "    ID: " . $cat['id'] . "\n\n";
        }

        // Show expected SEO URL based on category
        $firstCat = $catResponse['data'][0]['categories'][0];
        $catAttrs = $firstCat['attributes'] ?? $firstCat;
        $breadcrumb = $catAttrs['translated']['breadcrumb'] ?? $catAttrs['breadcrumb'] ?? [];

        // Build expected URL from breadcrumb
        $pathParts = [];
        for ($i = 1; $i < count($breadcrumb); $i++) {
            $slug = $breadcrumb[$i];
            $slug = str_replace(['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü', ' & ', '&', ' ', ',', '/'],
                               ['ae', 'oe', 'ue', 'ss', 'Ae', 'Oe', 'Ue', '-', '-', '-', '', '-'], $slug);
            $slug = strtolower($slug);
            $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
            $slug = preg_replace('/-+/', '-', $slug);
            $slug = trim($slug, '-');
            $pathParts[] = $slug;
        }

        // Add product slug
        $productSlug = str_replace(['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü', ' & ', '&', ' ', ',', '/'],
                                   ['ae', 'oe', 'ue', 'ss', 'Ae', 'Oe', 'Ue', '-', '-', '-', '', '-'], $productName);
        $productSlug = strtolower($productSlug);
        $productSlug = preg_replace('/[^a-z0-9-]/', '', $productSlug);
        $productSlug = preg_replace('/-+/', '-', $productSlug);
        $productSlug = trim($productSlug, '-');

        $pathParts[] = $productSlug;
        $expectedUrl = implode('/', $pathParts) . '/';

        echo "EXPECTED SEO URL (based on category):\n";
        echo "  /$expectedUrl\n";
    }
}
