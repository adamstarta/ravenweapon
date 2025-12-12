<?php
/**
 * Check bank details configuration for Vorkasse payment
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
    curl_close($ch);
    return json_decode($response, true);
}

echo "═══════════════════════════════════════════════════════════\n";
echo "           BANK DETAILS FOR VORKASSE                        \n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Get payment method details from OLD
$oldToken = getToken($OLD_URL);
if ($oldToken) {
    $result = apiPost($OLD_URL, $oldToken, 'search/payment-method', [
        'filter' => [
            ['type' => 'equals', 'field' => 'handlerIdentifier', 'value' => 'Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\PrePayment']
        ],
        'associations' => [
            'translations' => []
        ]
    ]);

    echo "📦 OLD SITE - Paid in advance details:\n";
    if (!empty($result['data'])) {
        $pm = $result['data'][0];
        echo "   ID: " . $pm['id'] . "\n";
        echo "   Name: " . ($pm['name'] ?? $pm['attributes']['name'] ?? 'N/A') . "\n";
        echo "   Description: " . ($pm['description'] ?? $pm['attributes']['description'] ?? 'N/A') . "\n";

        // Check custom fields
        $customFields = $pm['customFields'] ?? $pm['attributes']['customFields'] ?? [];
        if (!empty($customFields)) {
            echo "   Custom Fields:\n";
            print_r($customFields);
        }
    }
}

echo "\n";

// Get payment method details from NEW
$newToken = getToken($NEW_URL);
if ($newToken) {
    $result = apiPost($NEW_URL, $newToken, 'search/payment-method', [
        'filter' => [
            ['type' => 'equals', 'field' => 'handlerIdentifier', 'value' => 'Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\PrePayment']
        ],
        'associations' => [
            'translations' => []
        ]
    ]);

    echo "📦 NEW SITE - Paid in advance details:\n";
    if (!empty($result['data'])) {
        $pm = $result['data'][0];
        echo "   ID: " . $pm['id'] . "\n";
        echo "   Name: " . ($pm['name'] ?? $pm['attributes']['name'] ?? 'N/A') . "\n";
        echo "   Description: " . ($pm['description'] ?? $pm['attributes']['description'] ?? 'N/A') . "\n";

        // Check custom fields
        $customFields = $pm['customFields'] ?? $pm['attributes']['customFields'] ?? [];
        if (!empty($customFields)) {
            echo "   Custom Fields:\n";
            print_r($customFields);
        }
    }
}

echo "\n═══════════════════════════════════════════════════════════\n";

// Check system config for bank details
echo "\n📋 Checking system config for bank details...\n\n";

if ($oldToken) {
    $ch = curl_init($OLD_URL . '/api/search/system-config');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $oldToken,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'filter' => [
                ['type' => 'contains', 'field' => 'configurationKey', 'value' => 'bank']
            ],
            'limit' => 50
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $configs = json_decode($response, true);

    echo "OLD SITE bank configs:\n";
    if (!empty($configs['data'])) {
        foreach ($configs['data'] as $config) {
            $key = $config['configurationKey'] ?? $config['attributes']['configurationKey'] ?? '';
            $value = $config['configurationValue'] ?? $config['attributes']['configurationValue'] ?? '';
            echo "   {$key}: " . json_encode($value) . "\n";
        }
    } else {
        echo "   No bank configs found\n";
    }
}

echo "\n";

if ($newToken) {
    $ch = curl_init($NEW_URL . '/api/search/system-config');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $newToken,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'filter' => [
                ['type' => 'contains', 'field' => 'configurationKey', 'value' => 'bank']
            ],
            'limit' => 50
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $configs = json_decode($response, true);

    echo "NEW SITE bank configs:\n";
    if (!empty($configs['data'])) {
        foreach ($configs['data'] as $config) {
            $key = $config['configurationKey'] ?? $config['attributes']['configurationKey'] ?? '';
            $value = $config['configurationValue'] ?? $config['attributes']['configurationValue'] ?? '';
            echo "   {$key}: " . json_encode($value) . "\n";
        }
    } else {
        echo "   No bank configs found\n";
    }
}
