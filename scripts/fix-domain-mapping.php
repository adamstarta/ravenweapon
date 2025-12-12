<?php
/**
 * Fix domain mapping for CHF installation
 */

$NEW_URL = 'http://77.42.19.154:8080';

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

echo "═══════════════════════════════════════════════════════════\n";
echo "           FIX DOMAIN MAPPING                               \n";
echo "═══════════════════════════════════════════════════════════\n\n";

$token = getToken($NEW_URL);
if (!$token) {
    die("❌ Failed to get token\n");
}
echo "✅ Got token\n\n";

// Get sales channel domains
echo "📍 Getting current sales channel domains...\n";
$ch = curl_init($NEW_URL . '/api/sales-channel-domain');
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

$domains = json_decode($response, true);
echo "Found domains:\n";
print_r($domains);

// Get sales channel ID
echo "\n📍 Getting sales channel...\n";
$ch = curl_init($NEW_URL . '/api/sales-channel');
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

$salesChannels = json_decode($response, true);
$salesChannelId = null;
$languageId = null;
$currencyId = null;
$snippetSetId = null;

if (!empty($salesChannels['data'])) {
    foreach ($salesChannels['data'] as $sc) {
        $salesChannelId = $sc['id'];
        $languageId = $sc['attributes']['languageId'] ?? $sc['languageId'] ?? null;
        $currencyId = $sc['attributes']['currencyId'] ?? $sc['currencyId'] ?? null;
        break;
    }
}

echo "Sales Channel ID: {$salesChannelId}\n";
echo "Language ID: {$languageId}\n";
echo "Currency ID: {$currencyId}\n";

// Get snippet set
$ch = curl_init($NEW_URL . '/api/snippet-set?filter[iso]=de-DE');
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

$snippetSets = json_decode($response, true);
if (!empty($snippetSets['data'][0]['id'])) {
    $snippetSetId = $snippetSets['data'][0]['id'];
}
echo "Snippet Set ID: {$snippetSetId}\n\n";

// Add IP domain
echo "📍 Adding IP domain mapping...\n";
$newDomainId = bin2hex(random_bytes(16));

$domainData = [
    'id' => $newDomainId,
    'url' => 'http://77.42.19.154:8080',
    'salesChannelId' => $salesChannelId,
    'languageId' => $languageId,
    'currencyId' => $currencyId,
    'snippetSetId' => $snippetSetId
];

$ch = curl_init($NEW_URL . '/api/sales-channel-domain');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($domainData)
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response code: {$httpCode}\n";
if ($httpCode >= 200 && $httpCode < 300 || $httpCode == 204) {
    echo "✅ IP domain added!\n";
} else {
    echo "Response: {$response}\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "                        DONE!                               \n";
echo "═══════════════════════════════════════════════════════════\n";
