<?php
/**
 * Add IP domain for direct access (port 80)
 */

$NEW_URL = 'http://77.42.19.154'; // Port 80

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

$token = getToken($NEW_URL);
if (!$token) {
    die("❌ Failed to get token\n");
}

// Get sales channel info
$salesChannels = apiGet($NEW_URL, $token, 'sales-channel?limit=10');
$salesChannelId = null;
$languageId = null;
$currencyId = null;

foreach ($salesChannels['data'] ?? [] as $sc) {
    $type = $sc['attributes']['typeId'] ?? '';
    // Storefront type
    if ($type === '8a243080f92e4c719546314b577cf82b') {
        $salesChannelId = $sc['id'];
        $languageId = $sc['attributes']['languageId'] ?? null;
        $currencyId = $sc['attributes']['currencyId'] ?? null;
        break;
    }
}

if (!$salesChannelId) {
    // Fallback to first
    $salesChannelId = $salesChannels['data'][0]['id'] ?? null;
    $languageId = $salesChannels['data'][0]['attributes']['languageId'] ?? null;
    $currencyId = $salesChannels['data'][0]['attributes']['currencyId'] ?? null;
}

$snippetSets = apiGet($NEW_URL, $token, 'snippet-set?limit=10');
$snippetSetId = $snippetSets['data'][0]['id'] ?? null;

echo "Adding http://77.42.19.154 domain...\n";
$newDomainId = bin2hex(random_bytes(16));
$result = apiPost($NEW_URL, $token, 'sales-channel-domain', [
    'id' => $newDomainId,
    'url' => 'http://77.42.19.154',
    'salesChannelId' => $salesChannelId,
    'languageId' => $languageId,
    'currencyId' => $currencyId,
    'snippetSetId' => $snippetSetId
]);

if ($result['code'] >= 200 && $result['code'] < 300 || $result['code'] == 204) {
    echo "✅ http://77.42.19.154 added!\n";
} else {
    $error = $result['data']['errors'][0]['detail'] ?? json_encode($result['data']);
    echo "Result: {$error}\n";
}
