<?php
/**
 * Clear all Shopware caches thoroughly
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

echo "=== Clearing All Caches ===\n\n";

// Clear main cache
echo "1. Clearing main cache... ";
$result = apiRequest('DELETE', '/_action/cache', null, $token, $config);
echo ($result['code'] < 300 ? "OK" : "FAIL ({$result['code']})") . "\n";

// Warmup cache
echo "2. Warming up cache... ";
$result = apiRequest('DELETE', '/_action/cache_warmup', null, $token, $config);
echo ($result['code'] < 300 ? "OK" : "FAIL ({$result['code']})") . "\n";

// Clear container cache
echo "3. Clearing container cache... ";
$result = apiRequest('DELETE', '/_action/container_cache', null, $token, $config);
echo ($result['code'] < 300 ? "OK" : "FAIL ({$result['code']})") . "\n";

// Index products
echo "4. Re-indexing products... ";
$result = apiRequest('POST', '/_action/indexing/product.indexer', null, $token, $config);
echo ($result['code'] < 300 ? "OK" : "FAIL ({$result['code']})") . "\n";

// Index categories
echo "5. Re-indexing categories... ";
$result = apiRequest('POST', '/_action/indexing/category.indexer', null, $token, $config);
echo ($result['code'] < 300 ? "OK" : "FAIL ({$result['code']})") . "\n";

echo "\n=== Done ===\n";
