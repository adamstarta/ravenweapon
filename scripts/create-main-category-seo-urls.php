<?php
/**
 * Create SEO URLs for main (top-level) categories
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
        'body' => json_decode($response, true)
    ];
}

$token = getAccessToken($baseUrl, $clientId, $clientSecret);

echo "=== Create Main Category SEO URLs ===\n\n";

// Get sales channel
$scResponse = apiRequest($baseUrl, $token, 'POST', '/search/sales-channel', [
    'limit' => 1,
    'includes' => ['sales_channel' => ['id']]
]);
$salesChannelId = $scResponse['body']['data'][0]['id'] ?? null;
echo "Sales Channel: $salesChannelId\n\n";

// Main categories to create
$mainCategories = [
    '019aee0f487c79a1a8814377c46e0c10' => 'alle-produkte/',
    'a61f19c9cb4b11f0b4074aca3d279c31' => 'waffen/',
    'a61f1ec3cb4b11f0b4074aca3d279c31' => 'raven-caliber-kit/',
    '019adeff65f97225927586968691dc02' => 'zielhilfen-optik-zubehoer/',
    '2f40311624aea6de289c770f0bfd0ff9' => 'munition/',
    '604131c6ae1646c98623da4fe61a739b' => 'zubehoer/',
    '019b0857613474e6a799cfa07d143c76' => 'ausruestung/'
];

$created = 0;
$errors = 0;

foreach ($mainCategories as $catId => $seoPath) {
    $seoUrlData = [
        'foreignKey' => $catId,
        'routeName' => 'frontend.navigation.page',
        'pathInfo' => '/navigation/' . $catId,
        'seoPathInfo' => $seoPath,
        'salesChannelId' => $salesChannelId,
        'isCanonical' => true,
        'isModified' => true,
        'isDeleted' => false
    ];

    $response = apiRequest($baseUrl, $token, 'POST', '/seo-url', $seoUrlData);

    if ($response['code'] >= 200 && $response['code'] < 300) {
        echo "✓ /$seoPath\n";
        $created++;
    } else {
        $error = $response['body']['errors'][0]['detail'] ?? 'Unknown error';
        if (strpos($error, 'Duplicate') !== false || strpos($error, 'unique') !== false) {
            echo "~ /$seoPath (already exists)\n";
        } else {
            echo "✗ /$seoPath - " . substr($error, 0, 80) . "\n";
            $errors++;
        }
    }
}

echo "\n=== SUMMARY ===\n";
echo "Created: $created\n";
echo "Errors: $errors\n\n";

echo "Clear cache:\n";
echo "docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'\n";
