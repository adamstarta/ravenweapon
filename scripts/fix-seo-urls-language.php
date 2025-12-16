<?php
/**
 * Fix SEO URLs - ensure they have correct language for the sales channel
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
if (!$token) die("Failed to authenticate\n");

// Get sales channel info
echo "=== Sales Channel Info ===\n";
$scResult = apiRequest($config, $token, 'POST', '/api/search/sales-channel', [
    'associations' => ['language' => [], 'languages' => []],
    'limit' => 1
]);

$salesChannel = $scResult['data']['data'][0] ?? null;
if ($salesChannel) {
    $scId = $salesChannel['id'];
    $scLangId = $salesChannel['attributes']['languageId'] ?? 'unknown';
    echo "Sales Channel ID: $scId\n";
    echo "Default Language ID: $scLangId\n\n";
}

// Get all languages
echo "=== Languages ===\n";
$langResult = apiRequest($config, $token, 'POST', '/api/search/language', ['limit' => 10]);
foreach ($langResult['data']['data'] ?? [] as $lang) {
    $name = $lang['attributes']['name'] ?? 'unknown';
    $id = $lang['id'];
    echo "  $name: $id\n";
}
echo "\n";

// Main categories to fix
$categories = [
    'Alle Produkte' => ['id' => '019aee0f487c79a1a8814377c46e0c10', 'seoPath' => 'Alle-Produkte/'],
    'Waffen' => ['id' => 'a61f19c9cb4b11f0b4074aca3d279c31', 'seoPath' => 'Waffen/'],
    'Raven Caliber Kit' => ['id' => 'a61f1ec3cb4b11f0b4074aca3d279c31', 'seoPath' => 'Raven-Caliber-Kit/'],
    'Zielhilfen' => ['id' => '019adeff65f97225927586968691dc02', 'seoPath' => 'Zielhilfen-Optik-Zubehoer/'],
    'Munition' => ['id' => '2f40311624aea6de289c770f0bfd0ff9', 'seoPath' => 'Munition/'],
    'Zubehör' => ['id' => '604131c6ae1646c98623da4fe61a739b', 'seoPath' => 'Zubehoer/'],
    'Ausrüstung' => ['id' => '019b0857613474e6a799cfa07d143c76', 'seoPath' => 'Ausruestung/'],
];

echo "=== Creating/Updating SEO URLs with correct language ===\n\n";

foreach ($categories as $name => $info) {
    $categoryId = $info['id'];
    $seoPath = $info['seoPath'];

    echo "$name ($categoryId)\n";

    // Delete existing SEO URLs for this category
    $existingResult = apiRequest($config, $token, 'POST', '/api/search/seo-url', [
        'filter' => [
            ['type' => 'equals', 'field' => 'foreignKey', 'value' => $categoryId],
            ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
        ],
        'limit' => 50
    ]);

    if (!empty($existingResult['data']['data'])) {
        foreach ($existingResult['data']['data'] as $existing) {
            $deleteResult = apiRequest($config, $token, 'DELETE', '/api/seo-url/' . $existing['id'], null);
            echo "  Deleted old: " . ($existing['attributes']['seoPathInfo'] ?? 'unknown') . "\n";
        }
    }

    // Create new SEO URL with correct sales channel and language
    $createResult = apiRequest($config, $token, 'POST', '/api/seo-url', [
        'salesChannelId' => $scId,
        'languageId' => $scLangId,
        'foreignKey' => $categoryId,
        'routeName' => 'frontend.navigation.page',
        'pathInfo' => '/navigation/' . $categoryId,
        'seoPathInfo' => $seoPath,
        'isCanonical' => true,
        'isModified' => true,
        'isDeleted' => false
    ]);

    if ($createResult['code'] === 201 || $createResult['code'] === 200 || $createResult['code'] === 204) {
        echo "  ✅ Created: $seoPath\n";
    } else {
        echo "  ❌ Failed (HTTP " . $createResult['code'] . ")\n";
        if (isset($createResult['data']['errors'])) {
            foreach ($createResult['data']['errors'] as $err) {
                echo "    Error: " . ($err['detail'] ?? json_encode($err)) . "\n";
            }
        }
    }
    echo "\n";
}

echo "=== Done! Clear cache now. ===\n";
