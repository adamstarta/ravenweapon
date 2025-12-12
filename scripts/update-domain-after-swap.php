<?php
/**
 * Update domain mapping after swap - change from IP to ortak.ch
 */

$NEW_URL = 'http://77.42.19.154'; // Now on port 80

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => 'admin',
            'password' => 'shopware'
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true)['access_token'] ?? null;
}

function apiGet($baseUrl, $token, $endpoint) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function apiPost($baseUrl, $token, $endpoint, $data) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

function apiPatch($baseUrl, $token, $endpoint, $data) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

echo "═══════════════════════════════════════════════════════════\n";
echo "       UPDATE DOMAIN MAPPING AFTER SWAP                     \n";
echo "═══════════════════════════════════════════════════════════\n\n";

$token = getToken($NEW_URL);
if (!$token) {
    die("❌ Failed to get token - API may not be ready yet\n");
}
echo "✅ Got API token\n\n";

// Get current domains
echo "📍 Current domains:\n";
$domains = apiGet($NEW_URL, $token, 'sales-channel-domain?limit=50');
foreach ($domains['data'] ?? [] as $d) {
    $url = $d['attributes']['url'] ?? $d['url'] ?? '';
    echo "   - {$url} (ID: {$d['id']})\n";
}

// Get sales channel info
$salesChannels = apiGet($NEW_URL, $token, 'sales-channel?limit=10');
$salesChannelId = null;
$languageId = null;
$currencyId = null;

foreach ($salesChannels['data'] ?? [] as $sc) {
    $salesChannelId = $sc['id'];
    $languageId = $sc['attributes']['languageId'] ?? $sc['languageId'] ?? null;
    $currencyId = $sc['attributes']['currencyId'] ?? $sc['currencyId'] ?? null;
    break;
}

// Get snippet set
$snippetSets = apiGet($NEW_URL, $token, 'snippet-set?limit=10');
$snippetSetId = $snippetSets['data'][0]['id'] ?? null;

echo "\n📋 Sales Channel ID: {$salesChannelId}\n";
echo "📋 Language ID: {$languageId}\n";
echo "📋 Currency ID: {$currencyId}\n";
echo "📋 Snippet Set ID: {$snippetSetId}\n\n";

// Add https://ortak.ch domain
echo "🔧 Adding https://ortak.ch domain...\n";
$newDomainId = bin2hex(random_bytes(16));
$result = apiPost($NEW_URL, $token, 'sales-channel-domain', [
    'id' => $newDomainId,
    'url' => 'https://ortak.ch',
    'salesChannelId' => $salesChannelId,
    'languageId' => $languageId,
    'currencyId' => $currencyId,
    'snippetSetId' => $snippetSetId
]);

if ($result['code'] >= 200 && $result['code'] < 300 || $result['code'] == 204) {
    echo "   ✅ https://ortak.ch added!\n";
} else {
    $error = $result['data']['errors'][0]['detail'] ?? json_encode($result['data']);
    if (strpos($error, 'already exists') !== false || strpos($error, 'DUPLICATE') !== false) {
        echo "   ℹ️ https://ortak.ch already exists\n";
    } else {
        echo "   ⚠️ Result: " . substr($error, 0, 100) . "\n";
    }
}

// Also add http://ortak.ch
echo "🔧 Adding http://ortak.ch domain...\n";
$newDomainId2 = bin2hex(random_bytes(16));
$result = apiPost($NEW_URL, $token, 'sales-channel-domain', [
    'id' => $newDomainId2,
    'url' => 'http://ortak.ch',
    'salesChannelId' => $salesChannelId,
    'languageId' => $languageId,
    'currencyId' => $currencyId,
    'snippetSetId' => $snippetSetId
]);

if ($result['code'] >= 200 && $result['code'] < 300 || $result['code'] == 204) {
    echo "   ✅ http://ortak.ch added!\n";
} else {
    $error = $result['data']['errors'][0]['detail'] ?? json_encode($result['data']);
    if (strpos($error, 'already exists') !== false || strpos($error, 'DUPLICATE') !== false) {
        echo "   ℹ️ http://ortak.ch already exists\n";
    } else {
        echo "   ⚠️ Result: " . substr($error, 0, 100) . "\n";
    }
}

// Show final domains
echo "\n📍 Final domains:\n";
$domains = apiGet($NEW_URL, $token, 'sales-channel-domain?limit=50');
foreach ($domains['data'] ?? [] as $d) {
    $url = $d['attributes']['url'] ?? $d['url'] ?? '';
    echo "   - {$url}\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "                        DONE!                               \n";
echo "═══════════════════════════════════════════════════════════\n";
