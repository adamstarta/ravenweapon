<?php
/**
 * Update Order Confirmation Email Template to include selected color
 * This adds the color to the line item display in the order confirmation email
 */

// CHF Installation API Config (production)
$shopUrl = 'https://ortak.ch';

// Get OAuth token using admin password grant
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

// API request helper
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
    echo "✓ Got token\n\n";

    // Search for order confirmation mail template
    echo "Searching for order confirmation email template...\n";
    $result = apiRequest($shopUrl, $token, 'POST', 'search/mail-template', [
        'filter' => [
            [
                'type' => 'contains',
                'field' => 'mailTemplateType.technicalName',
                'value' => 'order_confirmation'
            ]
        ],
        'associations' => [
            'mailTemplateType' => [],
            'translations' => []
        ]
    ]);

    if ($result['code'] !== 200 || empty($result['body']['data'])) {
        // Try alternative search
        $result = apiRequest($shopUrl, $token, 'POST', 'search/mail-template', [
            'associations' => [
                'mailTemplateType' => [],
                'translations' => []
            ]
        ]);
    }

    if ($result['code'] !== 200) {
        throw new Exception("Failed to search templates: " . print_r($result, true));
    }

    // Find the order confirmation template
    $orderTemplate = null;
    foreach ($result['body']['data'] as $template) {
        $typeName = $template['mailTemplateType']['technicalName'] ?? '';
        echo "Found template: $typeName (ID: {$template['id']})\n";

        if (strpos($typeName, 'order_confirmation') !== false || strpos($typeName, 'order_confirmation_mail') !== false) {
            $orderTemplate = $template;
            break;
        }
    }

    if (!$orderTemplate) {
        echo "\nAll templates:\n";
        foreach ($result['body']['data'] as $template) {
            $typeName = $template['mailTemplateType']['technicalName'] ?? 'unknown';
            echo "  - $typeName\n";
        }
        throw new Exception("Order confirmation template not found");
    }

    echo "\n✓ Found order confirmation template: {$orderTemplate['id']}\n";

    // Get current HTML content
    $currentHtml = $orderTemplate['contentHtml'] ?? '';
    echo "\nCurrent HTML length: " . strlen($currentHtml) . " chars\n";

    // Check if color code already exists
    if (strpos($currentHtml, 'selectedColor') !== false) {
        echo "\n⚠ Color code already exists in template!\n";
        exit(0);
    }

    // Find the line item loop and add color after product name
    // Common patterns in Shopware order emails:
    // {{ lineItem.label }} or {{ item.label }}

    $colorCode = '{% if lineItem.payload.selectedColor is defined and lineItem.payload.selectedColor %}<br><span style="font-size: 12px; color: #059669; font-weight: 600;">Farbe: {{ lineItem.payload.selectedColor }}</span>{% endif %}';

    // Try to insert after lineItem.label
    $patterns = [
        '{{ lineItem.label }}' => '{{ lineItem.label }}' . $colorCode,
        '{{lineItem.label}}' => '{{lineItem.label}}' . $colorCode,
        '{{ item.label }}' => '{{ item.label }}' . $colorCode,
    ];

    $newHtml = $currentHtml;
    $replaced = false;

    foreach ($patterns as $search => $replace) {
        if (strpos($newHtml, $search) !== false) {
            $newHtml = str_replace($search, $replace, $newHtml);
            $replaced = true;
            echo "✓ Found and updated pattern: $search\n";
            break;
        }
    }

    if (!$replaced) {
        echo "\n⚠ Could not find standard patterns. Looking for lineItem loop...\n";

        // Try to find any mention of lineItem and show context
        if (preg_match('/(.{100}lineItem.{100})/s', $currentHtml, $matches)) {
            echo "Context found: " . $matches[1] . "\n";
        }

        // Manual insertion point - look for Art.Nr or product number pattern
        if (strpos($currentHtml, 'Art.Nr') !== false) {
            $newHtml = str_replace('Art.Nr', $colorCode . 'Art.Nr', $currentHtml);
            $replaced = true;
            echo "✓ Inserted before Art.Nr\n";
        }
    }

    if ($replaced && $newHtml !== $currentHtml) {
        echo "\nUpdating template...\n";

        $updateResult = apiRequest($shopUrl, $token, 'PATCH', "mail-template/{$orderTemplate['id']}", [
            'contentHtml' => $newHtml
        ]);

        if ($updateResult['code'] === 204 || $updateResult['code'] === 200) {
            echo "✓ Template updated successfully!\n";
        } else {
            echo "✗ Failed to update: " . print_r($updateResult, true) . "\n";
        }
    } else {
        echo "\n⚠ No changes made to template.\n";
        echo "You may need to manually edit the template in Shopware Admin.\n";
        echo "Add this code after the product name in the line items loop:\n\n";
        echo $colorCode . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
