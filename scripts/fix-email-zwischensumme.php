<?php
/**
 * Fix Email Template - Change Zwischensumme from NET to GROSS (position price)
 */

$shopUrl = 'https://ortak.ch';

function getAccessToken($shopUrl) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$shopUrl/api/oauth/token");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'grant_type' => 'password',
        'client_id' => 'administration',
        'username' => 'Micro the CEO',
        'password' => '100%Ravenweapon...'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Failed to get token: $response");
    }

    return json_decode($response, true)['access_token'];
}

function apiRequest($shopUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$shopUrl/api/$endpoint");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

try {
    echo "Getting access token...\n";
    $token = getAccessToken($shopUrl);
    echo "Got token\n\n";

    // Get the order confirmation template
    $result = apiRequest($shopUrl, $token, 'POST', 'search/mail-template', [
        'filter' => [
            ['type' => 'contains', 'field' => 'mailTemplateType.technicalName', 'value' => 'order_confirmation']
        ],
        'associations' => ['mailTemplateType' => []]
    ]);

    if ($result['code'] !== 200 || empty($result['body']['data'])) {
        throw new Exception("Template not found");
    }

    $template = $result['body']['data'][0];
    $templateId = $template['id'];
    $currentHtml = $template['contentHtml'] ?? '';

    echo "Template ID: $templateId\n";

    // Change amountNet to positionPrice (gross subtotal without shipping)
    // order.positionPrice = sum of all line item gross prices (without shipping)
    $fixedHtml = str_replace(
        '{{ order.amountNet|currency(order.currency.isoCode) }}',
        '{{ order.positionPrice|currency(order.currency.isoCode) }}',
        $currentHtml
    );

    if ($fixedHtml !== $currentHtml) {
        echo "Changed Zwischensumme from amountNet to positionPrice (gross)\n\n";

        echo "Updating template...\n";
        $updateResult = apiRequest($shopUrl, $token, 'PATCH', "mail-template/$templateId", [
            'contentHtml' => $fixedHtml
        ]);

        if ($updateResult['code'] === 204 || $updateResult['code'] === 200) {
            echo "Template updated successfully!\n";
            echo "\nZwischensumme will now show GROSS price (same as product prices)\n";
        } else {
            echo "Failed to update: " . print_r($updateResult, true) . "\n";
        }
    } else {
        echo "Pattern not found - template might already be fixed.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
