<?php
/**
 * Update Shopware email templates to German with Raven branding
 * - German language content
 * - Inter font for body text
 * - Chakra Petch font for headers
 * - Raven logo centered at top
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

// Get token
$token = getToken($API_URL);
if (!$token) {
    die("Failed to get token\n");
}

echo "Token obtained successfully\n\n";

// Email style template with Raven branding
$emailStyle = '
<style>
@import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Chakra+Petch:wght@500;600;700&display=swap");
</style>
<div style="font-family: \'Inter\', Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;">
    <!-- Raven Logo -->
    <div style="text-align: center; padding: 20px 0; border-bottom: 2px solid #1a1a1a;">
        <img src="https://ortak.ch/media/b5/e7/4f/1733787426/Raven%20Weapon%20AG-logo-dark.png" alt="Raven Weapon AG" style="max-width: 200px; height: auto;">
    </div>
    <div style="padding: 30px 20px;">
';

$emailFooter = '
    </div>
    <div style="text-align: center; padding: 20px; background-color: #f5f5f5; border-top: 1px solid #ddd; font-size: 12px; color: #666;">
        <p style="margin: 0;">Raven Weapon AG</p>
        <p style="margin: 5px 0;">Bei Fragen kontaktieren Sie uns gerne.</p>
    </div>
</div>
';

// German email templates
$templates = [
    'order_delivery.state.shipped' => [
        'subject' => 'Versandstatus aktualisiert - Bestellung {{ order.orderNumber }}',
        'plainText' => '{% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},

der Versandstatus Ihrer Bestellung bei {{ salesChannel.translated.name }} (Bestellnummer: {{ order.orderNumber }}) vom {{ order.orderDateTime|format_datetime(\'medium\', \'short\', locale=\'de-CH\') }} hat sich geändert.

Der neue Status lautet: {{ order.deliveries.first.stateMachineState.translated.name }}.

Sie können den aktuellen Status Ihrer Bestellung jederzeit auf unserer Website unter "Mein Konto" - "Meine Bestellungen" einsehen: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}

Falls Sie ohne Registrierung oder Kundenkonto bestellt haben, steht Ihnen diese Option nicht zur Verfügung.

Mit freundlichen Grüssen
Raven Weapon AG',
        'html' => $emailStyle . '
        <h2 style="font-family: \'Chakra Petch\', sans-serif; font-weight: 600; color: #1a1a1a; margin-bottom: 20px;">Versandstatus aktualisiert</h2>
        <p>
            {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>
        <p>
            der Versandstatus Ihrer Bestellung bei <strong>{{ salesChannel.translated.name }}</strong> (Bestellnummer: <strong>{{ order.orderNumber }}</strong>) vom {{ order.orderDateTime|format_datetime(\'medium\', \'short\', locale=\'de-CH\') }} hat sich geändert.
        </p>
        <p style="background-color: #f0f0f0; padding: 15px; border-left: 4px solid #1a1a1a; margin: 20px 0;">
            <strong style="font-family: \'Chakra Petch\', sans-serif;">Neuer Status: {{ order.deliveries.first.stateMachineState.translated.name }}</strong>
        </p>
        <p>
            Sie können den aktuellen Status Ihrer Bestellung jederzeit auf unserer Website unter "Mein Konto" - "Meine Bestellungen" einsehen:
        </p>
        <p style="text-align: center; margin: 25px 0;">
            <a href="{{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}" style="display: inline-block; background-color: #1a1a1a; color: #ffffff; padding: 12px 30px; text-decoration: none; font-family: \'Chakra Petch\', sans-serif; font-weight: 500;">Bestellung ansehen</a>
        </p>
        <p style="font-size: 12px; color: #666;">
            Falls Sie ohne Registrierung oder Kundenkonto bestellt haben, steht Ihnen diese Option nicht zur Verfügung.
        </p>
        <p style="margin-top: 30px;">
            Mit freundlichen Grüssen<br>
            <strong>Raven Weapon AG</strong>
        </p>
' . $emailFooter
    ]
];

// First, get all mail template types with their technical names
echo "Fetching mail template types...\n";
$typesResult = apiRequest($API_URL, $token, 'POST', 'search/mail-template-type', [
    'limit' => 100,
    'includes' => [
        'mail_template_type' => ['id', 'technicalName', 'name']
    ]
]);

// Debug: show raw response structure
echo "API Response keys: " . implode(', ', array_keys($typesResult ?? [])) . "\n";

if (!empty($typesResult['data'])) {
    echo "\nFound " . count($typesResult['data']) . " template types:\n";

    $typeMap = [];
    foreach ($typesResult['data'] as $type) {
        $id = $type['id'] ?? 'no-id';
        $techName = $type['attributes']['technicalName'] ?? $type['technicalName'] ?? 'unknown';
        $name = $type['attributes']['name'] ?? $type['name'] ?? 'unknown';
        $typeMap[$techName] = $id;
        echo "  - $techName ($name) => ID: $id\n";
    }

    // Now find and update the shipped template
    foreach ($templates as $techName => $content) {
        echo "\n\nProcessing: $techName\n";

        if (!isset($typeMap[$techName])) {
            echo "  Template type not found in map\n";
            continue;
        }

        $typeId = $typeMap[$techName];
        echo "  Type ID: $typeId\n";

        // Find the template with this type
        $templateResult = apiRequest($API_URL, $token, 'POST', 'search/mail-template', [
            'filter' => [
                ['type' => 'equals', 'field' => 'mailTemplateTypeId', 'value' => $typeId]
            ]
        ]);

        if (!empty($templateResult['data'])) {
            $templateId = $templateResult['data'][0]['id'];
            echo "  Template ID: $templateId\n";

            // Update the template
            $updateResult = apiRequest($API_URL, $token, 'PATCH', 'mail-template/' . $templateId, [
                'subject' => $content['subject'],
                'contentPlain' => $content['plainText'],
                'contentHtml' => $content['html']
            ]);

            if (isset($updateResult['errors'])) {
                echo "  ERROR: " . json_encode($updateResult['errors']) . "\n";
            } else {
                echo "  SUCCESS: Template updated!\n";
            }
        } else {
            echo "  No template found for this type\n";
        }
    }
}

echo "\n\nDone!\n";
