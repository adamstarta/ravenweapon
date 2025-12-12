<?php
/**
 * Configure bank details for Vorkasse payment method in NEW site
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
echo "     CONFIGURE BANK DETAILS FOR VORKASSE                    \n";
echo "═══════════════════════════════════════════════════════════\n\n";

$token = getToken($NEW_URL);
if (!$token) {
    die("❌ Failed to get token\n");
}

// Find PrePayment method
$result = apiPost($NEW_URL, $token, 'search/payment-method', [
    'filter' => [
        ['type' => 'equals', 'field' => 'handlerIdentifier', 'value' => 'Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\PrePayment']
    ]
]);

$paymentMethodId = $result['data']['data'][0]['id'] ?? null;
if (!$paymentMethodId) {
    die("❌ PrePayment method not found\n");
}

echo "📋 Found PrePayment method ID: {$paymentMethodId}\n\n";

// Bank details from OLD site
$bankDescription = 'Kontoinhaber: Nikola Mitrovic
Bank: PostFinance
Postkonto: 16-505989-2
IBAN: CH6009000000165059892
SWIFT: POFICHBEXXX

 Verwendungszweck: Ihre Bestellnummer

  Nach Zahlungseingang wird Ihre Bestellung umgehend bearbeitet.';

// Update payment method
echo "🔧 Updating payment method with bank details...\n";
$updateResult = apiPatch($NEW_URL, $token, 'payment-method/' . $paymentMethodId, [
    'name' => 'Vorkasse',
    'description' => $bankDescription,
    'active' => true
]);

if ($updateResult['code'] >= 200 && $updateResult['code'] < 300 || $updateResult['code'] == 204) {
    echo "✅ Payment method updated successfully!\n";
} else {
    echo "❌ Failed to update: " . json_encode($updateResult['data']) . "\n";
}

// Also disable other payment methods that shouldn't be active
echo "\n🔧 Disabling other payment methods...\n";

// Get all payment methods
$allMethods = apiPost($NEW_URL, $token, 'search/payment-method', ['limit' => 50]);

$methodsToDisable = ['Invoice', 'Cash on delivery', 'Direct Debit'];
foreach ($allMethods['data']['data'] ?? [] as $pm) {
    $name = $pm['name'] ?? $pm['attributes']['name'] ?? '';
    if (in_array($name, $methodsToDisable)) {
        $disableResult = apiPatch($NEW_URL, $token, 'payment-method/' . $pm['id'], [
            'active' => false
        ]);
        if ($disableResult['code'] >= 200 && $disableResult['code'] < 300 || $disableResult['code'] == 204) {
            echo "   ✅ Disabled: {$name}\n";
        }
    }
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "                        DONE!                               \n";
echo "═══════════════════════════════════════════════════════════\n";
