<?php
/**
 * Fix SEO URLs for Zubehör subcategories to use parent path prefix
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

$token = getAccessToken($config);
if (!$token) die("Failed to get token\n");

echo "=== Fix Zubehör Subcategory SEO URLs ===\n\n";

// Zubehör subcategories that need fixing
$zubehoerSubcategories = [
    '00a19869155b4c0d9508dfcfeeaf93d7' => 'Magazine',
    '17d31faee72a4f0eb9863adf8bab9b00' => 'Zweibeine',
    '2d9fa9cea22f4d8e8c80fc059f8fc47d' => 'Schienen-Zubehoer',
    '30d2d3ee371248d592cb1cdfdfd0f412' => 'Zielfernrohrmontagen',
    '6aa33f0f12e543fbb28d2bd7ede4dbf2' => 'Griffe-Handschutz',
    'b2f4bafcda154b899c22bfb74496d140' => 'Muendungsaufsaetze',
];

// Get sales channel ID
$scResult = apiRequest('POST', '/search/sales-channel', ['limit' => 1], $token, $config);
$salesChannelId = $scResult['body']['data'][0]['id'] ?? null;
echo "Sales Channel ID: $salesChannelId\n\n";

// Get language ID
$langResult = apiRequest('POST', '/search/language', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Deutsch']]
], $token, $config);
$languageId = $langResult['body']['data'][0]['id'] ?? null;
echo "Language ID: $languageId\n\n";

foreach ($zubehoerSubcategories as $categoryId => $seoPath) {
    $newPath = "Zubehoer/{$seoPath}/";
    echo "Updating: $seoPath -> $newPath\n";

    // First, check existing SEO URL
    $existingResult = apiRequest('POST', '/search/seo-url', [
        'filter' => [
            ['type' => 'equals', 'field' => 'foreignKey', 'value' => $categoryId],
            ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
        ]
    ], $token, $config);

    $existingSeoUrl = $existingResult['body']['data'][0] ?? null;

    if ($existingSeoUrl) {
        echo "  Current path: {$existingSeoUrl['seoPathInfo']}\n";

        // Update existing SEO URL
        $updateResult = apiRequest('PATCH', '/seo-url/' . $existingSeoUrl['id'], [
            'seoPathInfo' => $newPath
        ], $token, $config);

        if ($updateResult['code'] < 300) {
            echo "  Updated to: $newPath ✓\n";
        } else {
            echo "  FAILED to update: " . json_encode($updateResult['body']['errors'][0]['detail'] ?? 'Unknown') . "\n";

            // Try creating new SEO URL instead
            echo "  Trying to create new SEO URL...\n";
            $createResult = apiRequest('POST', '/seo-url', [
                'salesChannelId' => $salesChannelId,
                'languageId' => $languageId,
                'foreignKey' => $categoryId,
                'routeName' => 'frontend.navigation.page',
                'pathInfo' => '/navigation/' . $categoryId,
                'seoPathInfo' => $newPath,
                'isCanonical' => true,
                'isModified' => true
            ], $token, $config);

            if ($createResult['code'] < 300) {
                echo "  Created new SEO URL: $newPath ✓\n";
            } else {
                echo "  FAILED to create: " . json_encode($createResult['body']['errors'][0]['detail'] ?? 'Unknown') . "\n";
            }
        }
    } else {
        echo "  No existing SEO URL found, creating new one...\n";

        $createResult = apiRequest('POST', '/seo-url', [
            'salesChannelId' => $salesChannelId,
            'languageId' => $languageId,
            'foreignKey' => $categoryId,
            'routeName' => 'frontend.navigation.page',
            'pathInfo' => '/navigation/' . $categoryId,
            'seoPathInfo' => $newPath,
            'isCanonical' => true,
            'isModified' => true
        ], $token, $config);

        if ($createResult['code'] < 300) {
            echo "  Created: $newPath ✓\n";
        } else {
            echo "  FAILED: " . json_encode($createResult['body']['errors'][0]['detail'] ?? 'Unknown') . "\n";
        }
    }
    echo "\n";
}

// Clear cache
echo "Clearing cache... ";
$cacheResult = apiRequest('DELETE', '/_action/cache', null, $token, $config);
echo ($cacheResult['code'] < 300 ? "OK" : "FAIL") . "\n";

echo "\n=== Done ===\n";
