<?php
/**
 * Check payment methods in both OLD and NEW sites
 */

$OLD_URL = 'https://ortak.ch';
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

echo "═══════════════════════════════════════════════════════════\n";
echo "           PAYMENT METHODS COMPARISON                       \n";
echo "═══════════════════════════════════════════════════════════\n\n";

// OLD Site
echo "📦 OLD SITE (ortak.ch):\n";
$oldToken = getToken($OLD_URL);
if ($oldToken) {
    $payments = apiGet($OLD_URL, $oldToken, 'payment-method?limit=50');
    if (!empty($payments['data'])) {
        foreach ($payments['data'] as $pm) {
            $name = $pm['attributes']['name'] ?? $pm['name'] ?? 'Unknown';
            $active = ($pm['attributes']['active'] ?? $pm['active'] ?? false) ? '✅' : '❌';
            $handler = $pm['attributes']['handlerIdentifier'] ?? $pm['handlerIdentifier'] ?? '';
            echo "   {$active} {$name}\n";
            echo "      Handler: {$handler}\n";
        }
    }
} else {
    echo "   ❌ Could not connect\n";
}

echo "\n";

// NEW Site
echo "📦 NEW SITE (CHF - 77.42.19.154:8080):\n";
$newToken = getToken($NEW_URL);
if ($newToken) {
    $payments = apiGet($NEW_URL, $newToken, 'payment-method?limit=50');
    if (!empty($payments['data'])) {
        foreach ($payments['data'] as $pm) {
            $name = $pm['attributes']['name'] ?? $pm['name'] ?? 'Unknown';
            $active = ($pm['attributes']['active'] ?? $pm['active'] ?? false) ? '✅' : '❌';
            $handler = $pm['attributes']['handlerIdentifier'] ?? $pm['handlerIdentifier'] ?? '';
            echo "   {$active} {$name}\n";
            echo "      Handler: {$handler}\n";
        }
    }
} else {
    echo "   ❌ Could not connect\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";
