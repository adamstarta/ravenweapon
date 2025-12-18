<?php
/**
 * Delete all product SEO URLs so they can be regenerated correctly
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

echo "=== Delete Product SEO URLs ===\n\n";

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "Got token\n\n";

// Step 1: Get all product SEO URLs
echo "1. Fetching all product SEO URLs...\n";

$allUrls = [];
$page = 1;
$limit = 100;

while (true) {
    $response = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
        'limit' => $limit,
        'page' => $page,
        'filter' => [
            ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.detail.page']
        ]
    ]);

    $data = $response['body']['data'] ?? [];
    if (empty($data)) {
        break;
    }

    foreach ($data as $url) {
        $attrs = $url['attributes'] ?? $url;
        $allUrls[] = [
            'id' => $url['id'],
            'seoPathInfo' => $attrs['seoPathInfo'] ?? '',
            'foreignKey' => $attrs['foreignKey'] ?? '',
            'isCanonical' => $attrs['isCanonical'] ?? false
        ];
    }

    echo "   Page $page: Found " . count($data) . " URLs (total: " . count($allUrls) . ")\n";

    if (count($data) < $limit) {
        break;
    }
    $page++;
}

echo "\n   Total product SEO URLs found: " . count($allUrls) . "\n\n";

if (empty($allUrls)) {
    echo "No product URLs to delete.\n";
    exit(0);
}

// Step 2: Delete all product SEO URLs
echo "2. Deleting product SEO URLs...\n\n";

$deleted = 0;
$errors = 0;

foreach ($allUrls as $i => $url) {
    $deleteResponse = apiRequest($baseUrl, $token, 'DELETE', '/seo-url/' . $url['id']);

    if ($deleteResponse['code'] === 204) {
        $deleted++;
        if ($deleted % 50 == 0) {
            echo "   Deleted $deleted URLs...\n";
        }
    } else {
        $errors++;
        echo "   ERROR deleting {$url['seoPathInfo']}: HTTP {$deleteResponse['code']}\n";
    }
}

echo "\n=== Summary ===\n";
echo "Deleted: $deleted\n";
echo "Errors: $errors\n";
echo "\nNow run on server:\n";
echo "  docker exec shopware-chf bin/console dal:refresh:index\n";
echo "  docker exec shopware-chf bin/console cache:clear\n";
