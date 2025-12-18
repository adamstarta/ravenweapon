<?php
/**
 * Regenerate Product SEO URLs
 *
 * Problem: Old product SEO URLs (marked as deleted) cause duplicate key errors
 * Solution: Delete ALL product SEO URLs and let the indexer recreate them
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

echo "=== Regenerate Product SEO URLs ===\n\n";

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "Got API token\n\n";

// Step 1: Delete ALL product SEO URLs (including deleted ones)
echo "1. Deleting ALL product SEO URLs (including deleted)...\n";
$totalDeleted = 0;
$maxIterations = 50;

for ($i = 0; $i < $maxIterations; $i++) {
    // Get batch of product SEO URLs (without filtering isDeleted)
    $seoResponse = apiRequest($baseUrl, $token, 'POST', '/search/seo-url', [
        'limit' => 100,
        'page' => 1, // Always page 1 since we're deleting
        'filter' => [
            ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.detail.page']
        ],
        'includes' => [
            'seo_url' => ['id', 'seoPathInfo', 'isDeleted']
        ]
    ]);

    if (!isset($seoResponse['body']['data']) || count($seoResponse['body']['data']) === 0) {
        break;
    }

    $batch = $seoResponse['body']['data'];
    $batchSize = count($batch);

    // Delete each SEO URL
    foreach ($batch as $url) {
        $deleteResponse = apiRequest($baseUrl, $token, 'DELETE', '/seo-url/' . $url['id']);
        if ($deleteResponse['code'] >= 200 && $deleteResponse['code'] < 300) {
            $totalDeleted++;
        }
    }

    echo "   Deleted batch of $batchSize (total: $totalDeleted)\n";

    if ($batchSize < 100) break;
}

echo "   Total deleted: $totalDeleted\n\n";

echo "=== DONE ===\n\n";
echo "Product SEO URLs have been cleared.\n\n";
echo "NOW RUN THESE COMMANDS:\n";
echo "1. Regenerate product indexes:\n";
echo "   docker exec shopware-chf bash -c 'cd /var/www/html && bin/console dal:refresh:index --only=product.indexer'\n\n";
echo "2. Clear cache:\n";
echo "   docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'\n\n";
echo "3. Test a product URL like:\n";
echo "   https://ortak.ch/ausruestung/koerperschutz/westen-chest-rigs/covert-equipment-vest-12\n";
