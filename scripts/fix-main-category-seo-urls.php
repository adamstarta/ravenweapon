<?php
/**
 * Fix main category SEO URLs - replace /navigation/ with SEO-friendly URLs
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

function getToken($config) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . '/api/oauth/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => $config['api_user'],
            'password' => $config['api_password'],
        ]),
    ]);
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);
    return $data['access_token'] ?? null;
}

function apiRequest($config, $token, $method, $endpoint, $data = null) {
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $config['shopware_url'] . $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($data) $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    } elseif ($method === 'PATCH' || $method === 'DELETE') {
        $opts[CURLOPT_CUSTOMREQUEST] = $method;
        if ($data) $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

$token = getToken($config);
if (!$token) {
    die("Failed to authenticate\n");
}
echo "Authenticated successfully\n\n";

// Categories that need SEO URLs
$categoriesToFix = [
    '019aee0f487c79a1a8814377c46e0c10' => 'Alle-Produkte/',
    'a61f19c9cb4b11f0b4074aca3d279c31' => 'Waffen/',
    'a61f1ec3cb4b11f0b4074aca3d279c31' => 'Raven-Caliber-Kit/',
    '019adeff65f97225927586968691dc02' => 'Zielhilfen-Optik-Zubehoer/',
    '2f40311624aea6de289c770f0bfd0ff9' => 'Munition/',
    '604131c6ae1646c98623da4fe61a739b' => 'Zubehoer/',
    '019b0857613474e6a799cfa07d143c76' => 'Ausruestung/',
];

// Get sales channel and language
$scResult = apiRequest($config, $token, 'POST', '/api/search/sales-channel', ['limit' => 1]);
$salesChannelId = $scResult['data']['data'][0]['id'] ?? null;
echo "Sales Channel ID: $salesChannelId\n";

$langResult = apiRequest($config, $token, 'POST', '/api/search/language', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Deutsch']],
    'limit' => 1
]);
$languageId = $langResult['data']['data'][0]['id'] ?? '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
echo "Language ID: $languageId\n\n";

echo "=== Creating SEO URLs for Main Categories ===\n\n";

foreach ($categoriesToFix as $categoryId => $seoPath) {
    echo "Category ID: $categoryId\n";
    echo "SEO Path: $seoPath\n";

    // Check if SEO URL already exists
    $checkResult = apiRequest($config, $token, 'POST', '/api/search/seo-url', [
        'filter' => [
            ['type' => 'equals', 'field' => 'foreignKey', 'value' => $categoryId],
            ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page'],
            ['type' => 'equals', 'field' => 'isCanonical', 'value' => true]
        ]
    ]);

    $existingUrl = null;
    if (!empty($checkResult['data']['data'])) {
        $existingUrl = $checkResult['data']['data'][0]['attributes']['seoPathInfo'] ?? null;
        $existingId = $checkResult['data']['data'][0]['id'] ?? null;
        echo "Existing URL: $existingUrl\n";

        if ($existingUrl === $seoPath) {
            echo "✅ Already correct!\n\n";
            continue;
        }

        // Update existing
        if ($existingId) {
            $updateResult = apiRequest($config, $token, 'PATCH', '/api/seo-url/' . $existingId, [
                'seoPathInfo' => $seoPath,
                'isModified' => true
            ]);

            if ($updateResult['code'] === 204 || $updateResult['code'] === 200) {
                echo "✅ Updated: $existingUrl -> $seoPath\n\n";
            } else {
                echo "❌ Failed to update (HTTP " . $updateResult['code'] . ")\n\n";
            }
            continue;
        }
    }

    // Create new SEO URL
    $createResult = apiRequest($config, $token, 'POST', '/api/seo-url', [
        'salesChannelId' => $salesChannelId,
        'languageId' => $languageId,
        'foreignKey' => $categoryId,
        'routeName' => 'frontend.navigation.page',
        'pathInfo' => '/navigation/' . $categoryId,
        'seoPathInfo' => $seoPath,
        'isCanonical' => true,
        'isModified' => true
    ]);

    if ($createResult['code'] === 201 || $createResult['code'] === 200 || $createResult['code'] === 204) {
        echo "✅ Created: $seoPath\n\n";
    } else {
        echo "❌ Failed to create (HTTP " . $createResult['code'] . ")\n";
        if (isset($createResult['data']['errors'])) {
            print_r($createResult['data']['errors']);
        }
        echo "\n";
    }
}

echo "=== Done! ===\n";
echo "Run: ssh root@77.42.19.154 \"docker exec shopware-chf php bin/console cache:clear\"\n";
