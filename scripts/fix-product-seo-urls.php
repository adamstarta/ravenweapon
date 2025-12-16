<?php
/**
 * Fix product SEO URLs to use correct category paths
 *
 * Problem: Products were assigned to new categories but their SEO URLs
 * still point to old category paths like /Ausruestung/Scharfschützen-Ausrüstung/
 *
 * Solution: Update SEO URLs for all products in Zielhilfen and Zubehör
 * subcategories to use correct paths
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

function slugify($text) {
    // Convert to lowercase
    $text = mb_strtolower($text, 'UTF-8');
    // Replace special chars
    $text = str_replace(['ä', 'ö', 'ü', 'ß', '®', '™', '°'], ['ae', 'oe', 'ue', 'ss', '', '', ''], $text);
    // Replace spaces with hyphens
    $text = preg_replace('/[\s_]+/', '-', $text);
    // Remove special chars except hyphens
    $text = preg_replace('/[^a-z0-9-]/', '', $text);
    // Remove multiple hyphens
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

$token = getAccessToken($config);
if (!$token) die("Failed to get token\n");

echo "=== Fix Product SEO URLs ===\n\n";

// Category mapping: category ID => [parent path, category name]
$categoryMapping = [
    // Zielhilfen subcategories (under Waffenzubehoer)
    '499800fd06224f779acc5c4ac243d2e8' => ['Waffenzubehoer', 'Zielfernrohre'],
    'c34b9bb9a7a6473d928dd9c7c0f10f8b' => ['Waffenzubehoer', 'Rotpunktvisiere'],
    '461b4f23bcd74823abbb55550af5008c' => ['Waffenzubehoer', 'Spektive'],
    'b47d4447067c45aaa0aed7081ac465c4' => ['Waffenzubehoer', 'Fernglaeser'],

    // Zubehör subcategories (under Zubehoer)
    '00a19869155b4c0d9508dfcfeeaf93d7' => ['Zubehoer', 'Magazine'],
    '17d31faee72a4f0eb9863adf8bab9b00' => ['Zubehoer', 'Zweibeine'],
    '2d9fa9cea22f4d8e8c80fc059f8fc47d' => ['Zubehoer', 'Schienen-Zubehoer'],
    '30d2d3ee371248d592cb1cdfdfd0f412' => ['Zubehoer', 'Zielfernrohrmontagen'],
    '6aa33f0f12e543fbb28d2bd7ede4dbf2' => ['Zubehoer', 'Griffe-Handschutz'],
    'b2f4bafcda154b899c22bfb74496d140' => ['Zubehoer', 'Muendungsaufsaetze'],
];

// Get sales channel ID
$scResult = apiRequest('POST', '/search/sales-channel', ['limit' => 1], $token, $config);
$salesChannelId = $scResult['body']['data'][0]['id'] ?? null;
echo "Sales Channel ID: $salesChannelId\n\n";

$totalUpdated = 0;
$totalFailed = 0;

foreach ($categoryMapping as $categoryId => $pathInfo) {
    $parentPath = $pathInfo[0];
    $categoryName = $pathInfo[1];

    echo "=== Processing: $parentPath/$categoryName ===\n";

    // Get all products in this category
    $productsResult = apiRequest('POST', '/search/product', [
        'filter' => [
            ['type' => 'equals', 'field' => 'categories.id', 'value' => $categoryId]
        ],
        'includes' => ['product' => ['id', 'name', 'productNumber']],
        'limit' => 200
    ], $token, $config);

    $products = $productsResult['body']['data'] ?? [];
    echo "Found " . count($products) . " products\n";

    foreach ($products as $product) {
        $productId = $product['id'];
        $productName = $product['name'];
        $productNumber = $product['productNumber'] ?? '';

        // Create SEO-friendly slug
        $productSlug = slugify($productName);
        if ($productNumber) {
            $productSlug .= '-' . slugify($productNumber);
        }

        // New SEO path
        $newSeoPath = "$parentPath/$categoryName/$productSlug";

        // Check existing SEO URL for this product
        $existingResult = apiRequest('POST', '/search/seo-url', [
            'filter' => [
                ['type' => 'equals', 'field' => 'foreignKey', 'value' => $productId],
                ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.detail.page'],
                ['type' => 'equals', 'field' => 'salesChannelId', 'value' => $salesChannelId]
            ]
        ], $token, $config);

        $existingSeoUrl = $existingResult['body']['data'][0] ?? null;

        if ($existingSeoUrl) {
            $currentPath = $existingSeoUrl['seoPathInfo'];

            // Skip if already correct
            if (strpos($currentPath, "$parentPath/$categoryName/") === 0) {
                echo "  SKIP: $productName (already correct)\n";
                continue;
            }

            echo "  UPDATE: $productName\n";
            echo "    From: $currentPath\n";
            echo "    To:   $newSeoPath\n";

            // Update the SEO URL
            $updateResult = apiRequest('PATCH', '/seo-url/' . $existingSeoUrl['id'], [
                'seoPathInfo' => $newSeoPath,
                'isModified' => true
            ], $token, $config);

            if ($updateResult['code'] < 300) {
                $totalUpdated++;
            } else {
                echo "    FAILED: " . json_encode($updateResult['body']['errors'][0]['detail'] ?? 'Unknown') . "\n";
                $totalFailed++;
            }
        } else {
            echo "  No SEO URL found for: $productName (ID: $productId)\n";
        }
    }

    echo "\n";
}

echo "=== Summary ===\n";
echo "Updated: $totalUpdated\n";
echo "Failed: $totalFailed\n";

// Clear cache
echo "\nClearing cache... ";
$cacheResult = apiRequest('DELETE', '/_action/cache', null, $token, $config);
echo ($cacheResult['code'] < 300 ? "OK" : "FAIL") . "\n";

echo "\n=== Done ===\n";
