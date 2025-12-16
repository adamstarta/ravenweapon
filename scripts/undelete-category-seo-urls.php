<?php
/**
 * Undelete category SEO URLs - set isDeleted to false
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

echo "=== Fixing Deleted SEO URLs ===\n\n";

// Get all category SEO URLs that are deleted
$result = apiRequest($config, $token, 'POST', '/api/search/seo-url', [
    'filter' => [
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page'],
        ['type' => 'equals', 'field' => 'isDeleted', 'value' => true],
        ['type' => 'equals', 'field' => 'isCanonical', 'value' => true]
    ],
    'limit' => 500
]);

$seoUrls = $result['data']['data'] ?? [];
echo "Found " . count($seoUrls) . " deleted SEO URLs\n\n";

$fixed = 0;
$failed = 0;

foreach ($seoUrls as $seo) {
    $id = $seo['id'];
    $path = $seo['attributes']['seoPathInfo'] ?? 'unknown';

    echo "Fixing: $path ... ";

    $updateResult = apiRequest($config, $token, 'PATCH', '/api/seo-url/' . $id, [
        'isDeleted' => false
    ]);

    if ($updateResult['code'] === 204 || $updateResult['code'] === 200) {
        echo "✅ OK\n";
        $fixed++;
    } else {
        echo "❌ Failed (HTTP " . $updateResult['code'] . ")\n";
        $failed++;
    }
}

echo "\n=== Summary ===\n";
echo "Fixed: $fixed\n";
echo "Failed: $failed\n";
