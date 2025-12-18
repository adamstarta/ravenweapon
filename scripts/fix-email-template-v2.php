<?php
/**
 * Fix Order Confirmation Email Template - Remove broken color code from alt attribute
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

    $data = json_decode($response, true);
    return $data['access_token'];
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
    echo "Fetching order confirmation email template...\n";
    $result = apiRequest($shopUrl, $token, 'POST', 'search/mail-template', [
        'filter' => [
            [
                'type' => 'contains',
                'field' => 'mailTemplateType.technicalName',
                'value' => 'order_confirmation'
            ]
        ],
        'associations' => [
            'mailTemplateType' => []
        ]
    ]);

    if ($result['code'] !== 200 || empty($result['body']['data'])) {
        throw new Exception("Template not found");
    }

    $template = $result['body']['data'][0];
    $templateId = $template['id'];
    $currentHtml = $template['contentHtml'] ?? '';

    echo "Template ID: $templateId\n";
    echo "Current HTML length: " . strlen($currentHtml) . " chars\n\n";

    // The broken code pattern (inside alt attribute)
    $brokenAltPattern = 'alt="{{ lineItem.label }}{% if lineItem.payload.selectedColor is defined and lineItem.payload.selectedColor %}<br><span style="font-size: 12px; color: #059669; font-weight: 600;">Farbe: {{ lineItem.payload.selectedColor }}</span>{% endif %}"';

    // The correct alt attribute (just the label)
    $correctAlt = 'alt="{{ lineItem.label }}"';

    // Fix the broken alt attribute
    $fixedHtml = str_replace($brokenAltPattern, $correctAlt, $currentHtml);

    if ($fixedHtml !== $currentHtml) {
        echo "Fixed broken alt attribute!\n";

        // Save fixed template to file for verification
        file_put_contents(__DIR__ . '/email-template-fixed.html', $fixedHtml);
        echo "Saved fixed template to email-template-fixed.html\n\n";

        echo "Updating template...\n";
        $updateResult = apiRequest($shopUrl, $token, 'PATCH', "mail-template/$templateId", [
            'contentHtml' => $fixedHtml
        ]);

        if ($updateResult['code'] === 204 || $updateResult['code'] === 200) {
            echo "Template updated successfully!\n";
            echo "\nThe color will now show correctly after the product name.\n";
        } else {
            echo "Failed to update: " . print_r($updateResult, true) . "\n";
        }
    } else {
        echo "Pattern not found - template might already be fixed or has different structure.\n";

        // Show what we're looking for
        echo "\nSearching for broken pattern...\n";
        if (strpos($currentHtml, 'alt="{{ lineItem.label }}{% if') !== false) {
            echo "Found partial match - broken pattern exists\n";
        } else {
            echo "No broken alt attribute found\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
