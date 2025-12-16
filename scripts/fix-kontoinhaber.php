<?php
/**
 * Fix Kontoinhaber in payment method description
 * Change "Nikola Mitrovic" to "Raven Weapon AG"
 */

$API_URL = 'https://ortak.ch';

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
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

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$token = getToken($API_URL);
if (!$token) {
    die("Failed to get token\n");
}

echo "Token obtained\n\n";

// Find payment methods with full data
$payments = apiRequest($API_URL, $token, 'POST', 'search/payment-method', [
    'limit' => 100
]);

echo "Found " . count($payments['data'] ?? []) . " payment methods\n\n";

foreach ($payments['data'] ?? [] as $pm) {
    $id = $pm['id'];

    // Get full payment method data - check in attributes
    $description = $pm['attributes']['description'] ?? $pm['description'] ?? '';
    $name = $pm['attributes']['name'] ?? $pm['name'] ?? 'unknown';

    // Convert entire payment method to JSON to search
    $jsonData = json_encode($pm);

    echo "Payment: $name\n";

    if (stripos($jsonData, 'nikola') !== false || stripos($jsonData, 'mitrovic') !== false) {
        echo "  ** FOUND Nikola/Mitrovic in this payment method **\n";
        echo "  ID: $id\n";

        // Extract description from attributes
        $desc = $pm['attributes']['description'] ?? '';
        echo "  Description: $desc\n\n";

        if (!empty($desc)) {
            // Replace Nikola Mitrovic with Raven Weapon AG
            $newDescription = str_ireplace('Nikola Mitrovic', 'Raven Weapon AG', $desc);

            echo "  New description:\n$newDescription\n\n";

            // Update the payment method
            $updateResult = apiRequest($API_URL, $token, 'PATCH', 'payment-method/' . $id, [
                'description' => $newDescription
            ]);

            if (isset($updateResult['errors'])) {
                echo "  ERROR: " . json_encode($updateResult['errors']) . "\n";
            } else {
                echo "  SUCCESS: Kontoinhaber updated to Raven Weapon AG!\n";
            }
        }
    }
}

echo "\nDone!\n";
