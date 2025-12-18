<?php
/**
 * Find URLs with uppercase letters
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

echo "=== Finding ALL SEO URLs with uppercase letters ===\n\n";

$page = 1;
$uppercaseUrls = [];

while (true) {
    $seoResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
        'limit' => 500,
        'page' => $page,
        'includes' => [
            'seo_url' => ['id', 'seoPathInfo', 'routeName', 'isCanonical', 'isDeleted']
        ]
    ]);

    if (!isset($seoResponse['data']) || count($seoResponse['data']) === 0) {
        break;
    }

    foreach ($seoResponse['data'] as $url) {
        $attrs = $url['attributes'] ?? $url;
        $path = $attrs['seoPathInfo'] ?? '';
        $isDeleted = $attrs['isDeleted'] ?? false;

        // Check if path has any uppercase letters
        if (preg_match('/[A-Z]/', $path)) {
            $uppercaseUrls[] = [
                'id' => $url['id'],
                'path' => $path,
                'route' => $attrs['routeName'] ?? '',
                'canonical' => $attrs['isCanonical'] ?? false,
                'deleted' => $isDeleted
            ];
        }
    }

    if (count($seoResponse['data']) < 500) break;
    $page++;
}

echo "Found " . count($uppercaseUrls) . " URLs with uppercase letters:\n\n";

foreach ($uppercaseUrls as $url) {
    $status = $url['deleted'] ? '[DELETED]' : ($url['canonical'] ? '[CANONICAL]' : '');
    echo "  /{$url['path']} $status\n";
    echo "    Route: {$url['route']}\n";
    echo "    ID: {$url['id']}\n\n";
}
