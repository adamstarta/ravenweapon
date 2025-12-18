<?php
/**
 * Fix Order Confirmation Email Template - Remove broken color code and add properly
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

    // Save current template to file for inspection
    file_put_contents(__DIR__ . '/email-template-backup.html', $currentHtml);
    echo "Saved backup to email-template-backup.html\n\n";

    // Remove the broken color code that was inserted
    $brokenPattern = '{% if lineItem.payload.selectedColor is defined and lineItem.payload.selectedColor %}<br><span style="font-size: 12px; color: #059669; font-weight: 600;">Farbe: {{ lineItem.payload.selectedColor }}</span>{% endif %}';

    $cleanHtml = str_replace($brokenPattern, '', $currentHtml);

    if ($cleanHtml !== $currentHtml) {
        echo "Removed broken color code from template\n";
    }

    // Now find the CORRECT place to insert - after the product name text, not in attributes
    // Look for pattern like: </a> or </td> after lineItem.label that's NOT in an attribute

    // The color code to add - this time we'll add it after the closing tag of the product link
    $colorCode = '{% if lineItem.payload.selectedColor is defined and lineItem.payload.selectedColor %}<div style="font-size: 12px; color: #059669; font-weight: 600; margin-top: 4px;">Farbe: {{ lineItem.payload.selectedColor }}</div>{% endif %}';

    // Find pattern: {{ lineItem.label }}</a> or similar closing patterns
    $insertPatterns = [
        // After product name link
        '{{ lineItem.label }}</a>' => '{{ lineItem.label }}</a>' . $colorCode,
        '{{lineItem.label}}</a>' => '{{lineItem.label}}</a>' . $colorCode,
        // After product name in a span or div
        '{{ lineItem.label }}</span>' => '{{ lineItem.label }}</span>' . $colorCode,
        '{{ lineItem.label }}</div>' => '{{ lineItem.label }}</div>' . $colorCode,
        '{{ lineItem.label }}</td>' => '{{ lineItem.label }}</td>' . $colorCode,
        // After product name followed by closing tag with whitespace
        "{{ lineItem.label }}\n</a>" => "{{ lineItem.label }}</a>" . $colorCode,
    ];

    $newHtml = $cleanHtml;
    $inserted = false;

    foreach ($insertPatterns as $search => $replace) {
        if (strpos($newHtml, $search) !== false) {
            $newHtml = str_replace($search, $replace, $newHtml);
            $inserted = true;
            echo "Found pattern and inserted color code after: $search\n";
            break;
        }
    }

    // If no pattern found, try to find the line item section and insert after product name display
    if (!$inserted) {
        echo "\nLooking for line item section...\n";

        // Look for the lineItem.label that's displayed as text (not in alt attribute)
        // Find pattern like: >{{ lineItem.label }}< (displayed text)
        if (preg_match('/>(\s*)\{\{\s*lineItem\.label\s*\}\}(\s*)</s', $cleanHtml, $matches, PREG_OFFSET_MATCH)) {
            $fullMatch = $matches[0][0];
            $offset = $matches[0][1];
            echo "Found displayed lineItem.label at position $offset\n";
            echo "Context: " . substr($cleanHtml, max(0, $offset - 50), 150) . "\n\n";
        }

        // Try another approach - find </a> or </strong> after lineItem.label
        if (preg_match('/\{\{\s*lineItem\.label\s*\}\}[^<]*<\/a>/s', $cleanHtml, $matches)) {
            $search = $matches[0];
            $replace = $search . $colorCode;
            $newHtml = str_replace($search, $replace, $cleanHtml);
            $inserted = true;
            echo "Inserted after: $search\n";
        }
    }

    // Last resort - look for Art.Nr pattern and insert before it
    if (!$inserted && strpos($cleanHtml, 'Art.Nr') !== false) {
        // Find the Art.Nr line and add color before it
        $newHtml = preg_replace(
            '/(<[^>]*>Art\.Nr)/',
            $colorCode . '$1',
            $cleanHtml,
            1
        );
        if ($newHtml !== $cleanHtml) {
            $inserted = true;
            echo "Inserted before Art.Nr\n";
        }
    }

    if ($newHtml !== $currentHtml) {
        // Save new template to file for inspection
        file_put_contents(__DIR__ . '/email-template-new.html', $newHtml);
        echo "\nSaved new template to email-template-new.html\n";

        echo "\nUpdating template...\n";
        $updateResult = apiRequest($shopUrl, $token, 'PATCH', "mail-template/$templateId", [
            'contentHtml' => $newHtml
        ]);

        if ($updateResult['code'] === 204 || $updateResult['code'] === 200) {
            echo "Template updated successfully!\n";
        } else {
            echo "Failed to update: " . print_r($updateResult, true) . "\n";
        }
    } else {
        echo "\nNo changes needed or couldn't find insertion point.\n";
        echo "Check email-template-backup.html to manually find the right place.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
