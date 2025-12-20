<?php
/**
 * Update Contact Form Email Template with Luxury Design
 * Matches Raven Weapon AG brand: Gold gradient, Black, Dark gray
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

// New luxury email template HTML
$newHtmlTemplate = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">

                    <!-- Header with Logo -->
                    <tr>
                        <td align="center" style="padding: 32px 40px 24px 40px; background-color: #ffffff;">
                            <img src="data:image/png;base64,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" alt="Raven Weapon AG" width="200" style="display: block; max-width: 200px; height: auto;">
                        </td>
                    </tr>

                    <!-- Gold Gradient Line -->
                    <tr>
                        <td style="height: 4px; background: linear-gradient(90deg, #F2B90D 0%, #F6CE55 50%, #FFD54F 100%);"></td>
                    </tr>

                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h1 style="margin: 0 0 32px 0; font-family: Arial, sans-serif; font-size: 24px; font-weight: 700; color: #111827; text-align: center;">
                                Neue Kontaktanfrage
                            </h1>

                            <!-- Contact Details Table -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                                        <span style="font-size: 14px; font-weight: 600; color: #F2B90D;">Name:</span>
                                    </td>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb; text-align: right;">
                                        <span style="font-size: 14px; color: #374151;">{{ contactFormData.firstName }} {{ contactFormData.lastName }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                                        <span style="font-size: 14px; font-weight: 600; color: #F2B90D;">E-Mail:</span>
                                    </td>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb; text-align: right;">
                                        <a href="mailto:{{ contactFormData.email }}" style="font-size: 14px; color: #374151; text-decoration: none;">{{ contactFormData.email }}</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                                        <span style="font-size: 14px; font-weight: 600; color: #F2B90D;">Telefon:</span>
                                    </td>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb; text-align: right;">
                                        <a href="tel:{{ contactFormData.phone }}" style="font-size: 14px; color: #374151; text-decoration: none;">{{ contactFormData.phone }}</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 0;">
                                        <span style="font-size: 14px; font-weight: 600; color: #F2B90D;">Betreff:</span>
                                    </td>
                                    <td style="padding: 12px 0; text-align: right;">
                                        <span style="font-size: 14px; color: #374151;">{{ contactFormData.subject }}</span>
                                    </td>
                                </tr>
                            </table>

                            <!-- Message Box -->
                            <div style="background-color: #f9fafb; border-radius: 8px; padding: 20px; border-left: 4px solid #F2B90D;">
                                <p style="margin: 0 0 8px 0; font-size: 14px; font-weight: 600; color: #F2B90D;">Nachricht:</p>
                                <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #374151;">{{ contactFormData.comment|nl2br }}</p>
                            </div>
                        </td>
                    </tr>

                    <!-- Gold Gradient Line -->
                    <tr>
                        <td style="height: 4px; background: linear-gradient(90deg, #F2B90D 0%, #F6CE55 50%, #FFD54F 100%);"></td>
                    </tr>

                    <!-- Dark Footer -->
                    <tr>
                        <td style="padding: 32px 40px; background-color: #111827;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center">
                                        <p style="margin: 0 0 8px 0; font-size: 16px; font-weight: 700; color: #ffffff;">Raven Weapon AG</p>
                                        <p style="margin: 0 0 4px 0; font-size: 13px; color: #9ca3af;">Gorisstrasse 1, 8735 St. Gallenkappel</p>
                                        <p style="margin: 0; font-size: 13px; color: #9ca3af;">
                                            <a href="mailto:info@ravenweapon.ch" style="color: #F6CE55; text-decoration: none;">info@ravenweapon.ch</a>
                                            <span style="color: #6b7280;"> | </span>
                                            <a href="tel:+41793561986" style="color: #F6CE55; text-decoration: none;">+41 79 356 19 86</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

// Plain text version
$newPlainTemplate = <<<'TEXT'
NEUE KONTAKTANFRAGE
==================

Name: {{ contactFormData.firstName }} {{ contactFormData.lastName }}
E-Mail: {{ contactFormData.email }}
Telefon: {{ contactFormData.phone }}
Betreff: {{ contactFormData.subject }}

Nachricht:
{{ contactFormData.comment }}

--
Raven Weapon AG
Gorisstrasse 1, 8735 St. Gallenkappel
info@ravenweapon.ch | +41 79 356 19 86
TEXT;

try {
    echo "=== Raven Weapon Contact Email Template Update ===\n\n";

    echo "Getting access token...\n";
    $token = getAccessToken($shopUrl);
    echo "✓ Got token\n\n";

    // Search for contact form mail template
    echo "Searching for contact form email template...\n";
    $result = apiRequest($shopUrl, $token, 'POST', 'search/mail-template', [
        'filter' => [
            [
                'type' => 'equals',
                'field' => 'mailTemplateType.technicalName',
                'value' => 'contact_form'
            ]
        ],
        'associations' => [
            'mailTemplateType' => [],
            'translations' => []
        ]
    ]);

    if ($result['code'] !== 200 || empty($result['body']['data'])) {
        throw new Exception("Contact form template not found: " . print_r($result, true));
    }

    $template = $result['body']['data'][0];
    echo "✓ Found template ID: {$template['id']}\n\n";

    // Update the template
    echo "Updating email template with luxury design...\n";

    $updateResult = apiRequest($shopUrl, $token, 'PATCH', "mail-template/{$template['id']}", [
        'contentHtml' => $newHtmlTemplate,
        'contentPlain' => $newPlainTemplate,
        'subject' => 'Neue Kontaktanfrage von {{ contactFormData.firstName }} {{ contactFormData.lastName }}'
    ]);

    if ($updateResult['code'] === 204 || $updateResult['code'] === 200) {
        echo "✓ Template updated successfully!\n\n";
        echo "New features:\n";
        echo "  - Centered logo header\n";
        echo "  - Gold gradient accent lines (#F2B90D → #F6CE55 → #FFD54F)\n";
        echo "  - Clean table layout for contact details\n";
        echo "  - Gold labels with dark gray values\n";
        echo "  - Message box with gold left border\n";
        echo "  - Dark footer (#111827) with full contact info\n";
        echo "\nTest by submitting the contact form at: https://ortak.ch/kontakt\n";
    } else {
        throw new Exception("Failed to update: " . print_r($updateResult, true));
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
