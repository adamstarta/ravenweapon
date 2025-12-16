<?php
/**
 * Update ALL Shopware email templates to German with Raven branding
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
$emailHeader = '
<style>
@import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Chakra+Petch:wght@500;600;700&display=swap");
</style>
<div style="font-family: \'Inter\', Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; background-color: #ffffff;">
    <!-- Raven Logo -->
    <div style="text-align: center; padding: 25px 0; background-color: #1a1a1a;">
        <img src="https://ortak.ch/media/b5/e7/4f/1733787426/Raven%20Weapon%20AG-logo-dark.png" alt="Raven Weapon AG" style="max-width: 180px; height: auto;">
    </div>
    <div style="padding: 30px 25px;">
';

$emailFooter = '
    </div>
    <div style="text-align: center; padding: 25px; background-color: #1a1a1a; color: #ffffff; font-size: 12px;">
        <p style="margin: 0 0 5px 0; font-family: \'Chakra Petch\', sans-serif; font-weight: 600;">Raven Weapon AG</p>
        <p style="margin: 5px 0; color: #cccccc;">Bei Fragen kontaktieren Sie uns gerne unter info@ravenweapon.ch</p>
    </div>
</div>
';

// German email templates
$templates = [
    // Order Confirmation
    'order_confirmation_mail' => [
        'subject' => 'Bestellbestätigung - Bestellung {{ order.orderNumber }}',
        'plainText' => '{% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},

vielen Dank für Ihre Bestellung bei {{ salesChannel.translated.name }}!

Ihre Bestellnummer: {{ order.orderNumber }}
Bestelldatum: {{ order.orderDateTime|format_datetime(\'medium\', \'short\', locale=\'de-CH\') }}

Bestellübersicht:
{% for lineItem in order.lineItems %}
- {{ lineItem.quantity }}x {{ lineItem.label }} - {{ lineItem.totalPrice|currency(order.currency.isoCode) }}
{% endfor %}

Zwischensumme: {{ order.amountNet|currency(order.currency.isoCode) }}
Versand: {{ order.shippingTotal|currency(order.currency.isoCode) }}
{% for calculatedTax in order.price.calculatedTaxes %}
MwSt. {{ calculatedTax.taxRate }}%: {{ calculatedTax.tax|currency(order.currency.isoCode) }}
{% endfor %}
Gesamtbetrag: {{ order.amountTotal|currency(order.currency.isoCode) }}

Lieferadresse:
{{ order.deliveries.first.shippingOrderAddress.firstName }} {{ order.deliveries.first.shippingOrderAddress.lastName }}
{{ order.deliveries.first.shippingOrderAddress.street }}
{{ order.deliveries.first.shippingOrderAddress.zipcode }} {{ order.deliveries.first.shippingOrderAddress.city }}
{{ order.deliveries.first.shippingOrderAddress.country.translated.name }}

Rechnungsadresse:
{{ order.billingAddress.firstName }} {{ order.billingAddress.lastName }}
{{ order.billingAddress.street }}
{{ order.billingAddress.zipcode }} {{ order.billingAddress.city }}
{{ order.billingAddress.country.translated.name }}

Sie können den Status Ihrer Bestellung jederzeit hier einsehen: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}

Mit freundlichen Grüssen
Raven Weapon AG',
        'html' => $emailHeader . '
        <h2 style="font-family: \'Chakra Petch\', sans-serif; font-weight: 600; color: #1a1a1a; margin: 0 0 20px 0; font-size: 24px;">Bestellbestätigung</h2>
        <p>
            {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>
        <p>
            vielen Dank für Ihre Bestellung bei <strong>{{ salesChannel.translated.name }}</strong>!
        </p>

        <div style="background-color: #f5f5f5; padding: 15px; margin: 20px 0; border-left: 4px solid #1a1a1a;">
            <p style="margin: 0;"><strong>Bestellnummer:</strong> {{ order.orderNumber }}</p>
            <p style="margin: 5px 0 0 0;"><strong>Bestelldatum:</strong> {{ order.orderDateTime|format_datetime(\'medium\', \'short\', locale=\'de-CH\') }}</p>
        </div>

        <h3 style="font-family: \'Chakra Petch\', sans-serif; font-weight: 600; color: #1a1a1a; font-size: 18px; border-bottom: 2px solid #1a1a1a; padding-bottom: 10px;">Bestellübersicht</h3>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            {% for lineItem in order.lineItems %}
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px 0;">{{ lineItem.quantity }}x {{ lineItem.label }}</td>
                <td style="padding: 10px 0; text-align: right; font-weight: 500;">{{ lineItem.totalPrice|currency(order.currency.isoCode) }}</td>
            </tr>
            {% endfor %}
        </table>

        <table style="width: 100%; margin-bottom: 25px;">
            <tr>
                <td style="padding: 5px 0;">Zwischensumme:</td>
                <td style="padding: 5px 0; text-align: right;">{{ order.amountNet|currency(order.currency.isoCode) }}</td>
            </tr>
            <tr>
                <td style="padding: 5px 0;">Versand:</td>
                <td style="padding: 5px 0; text-align: right;">{{ order.shippingTotal|currency(order.currency.isoCode) }}</td>
            </tr>
            {% for calculatedTax in order.price.calculatedTaxes %}
            <tr>
                <td style="padding: 5px 0;">MwSt. {{ calculatedTax.taxRate }}%:</td>
                <td style="padding: 5px 0; text-align: right;">{{ calculatedTax.tax|currency(order.currency.isoCode) }}</td>
            </tr>
            {% endfor %}
            <tr style="border-top: 2px solid #1a1a1a; font-weight: 600;">
                <td style="padding: 15px 0; font-family: \'Chakra Petch\', sans-serif;">Gesamtbetrag:</td>
                <td style="padding: 15px 0; text-align: right; font-size: 18px;">{{ order.amountTotal|currency(order.currency.isoCode) }}</td>
            </tr>
        </table>

        <div style="display: flex; gap: 20px; margin-bottom: 25px;">
            <div style="flex: 1; background-color: #f9f9f9; padding: 15px;">
                <h4 style="font-family: \'Chakra Petch\', sans-serif; margin: 0 0 10px 0; font-size: 14px; color: #666;">Lieferadresse</h4>
                <p style="margin: 0;">
                    {{ order.deliveries.first.shippingOrderAddress.firstName }} {{ order.deliveries.first.shippingOrderAddress.lastName }}<br>
                    {{ order.deliveries.first.shippingOrderAddress.street }}<br>
                    {{ order.deliveries.first.shippingOrderAddress.zipcode }} {{ order.deliveries.first.shippingOrderAddress.city }}<br>
                    {{ order.deliveries.first.shippingOrderAddress.country.translated.name }}
                </p>
            </div>
        </div>

        <p style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}" style="display: inline-block; background-color: #1a1a1a; color: #ffffff; padding: 14px 35px; text-decoration: none; font-family: \'Chakra Petch\', sans-serif; font-weight: 500;">Bestellung ansehen</a>
        </p>

        <p style="margin-top: 30px;">
            Mit freundlichen Grüssen<br>
            <strong>Raven Weapon AG</strong>
        </p>
' . $emailFooter
    ],

    // Shipped
    'order_delivery.state.shipped' => [
        'subject' => 'Ihre Bestellung wurde versandt - {{ order.orderNumber }}',
        'plainText' => '{% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},

gute Neuigkeiten! Ihre Bestellung {{ order.orderNumber }} wurde versandt.

Der neue Status lautet: {{ order.deliveries.first.stateMachineState.translated.name }}.

Sie können den aktuellen Status Ihrer Bestellung jederzeit unter "Mein Konto" - "Meine Bestellungen" einsehen: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}

Mit freundlichen Grüssen
Raven Weapon AG',
        'html' => $emailHeader . '
        <h2 style="font-family: \'Chakra Petch\', sans-serif; font-weight: 600; color: #1a1a1a; margin: 0 0 20px 0; font-size: 24px;">Ihre Bestellung wurde versandt!</h2>
        <p>
            {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>
        <p>
            gute Neuigkeiten! Ihre Bestellung <strong>{{ order.orderNumber }}</strong> wurde versandt.
        </p>
        <div style="background-color: #e8f5e9; padding: 15px; border-left: 4px solid #4caf50; margin: 20px 0;">
            <strong style="font-family: \'Chakra Petch\', sans-serif;">Status: {{ order.deliveries.first.stateMachineState.translated.name }}</strong>
        </div>
        <p style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}" style="display: inline-block; background-color: #1a1a1a; color: #ffffff; padding: 14px 35px; text-decoration: none; font-family: \'Chakra Petch\', sans-serif; font-weight: 500;">Bestellung verfolgen</a>
        </p>
        <p style="margin-top: 30px;">
            Mit freundlichen Grüssen<br>
            <strong>Raven Weapon AG</strong>
        </p>
' . $emailFooter
    ],

    // Payment Paid
    'order_transaction.state.paid' => [
        'subject' => 'Zahlung erhalten - Bestellung {{ order.orderNumber }}',
        'plainText' => '{% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},

wir haben Ihre Zahlung für die Bestellung {{ order.orderNumber }} erhalten. Vielen Dank!

Der Zahlungsstatus lautet nun: {{ order.transactions.first.stateMachineState.translated.name }}.

Mit freundlichen Grüssen
Raven Weapon AG',
        'html' => $emailHeader . '
        <h2 style="font-family: \'Chakra Petch\', sans-serif; font-weight: 600; color: #1a1a1a; margin: 0 0 20px 0; font-size: 24px;">Zahlung erhalten</h2>
        <p>
            {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>
        <p>
            wir haben Ihre Zahlung für die Bestellung <strong>{{ order.orderNumber }}</strong> erhalten. Vielen Dank!
        </p>
        <div style="background-color: #e8f5e9; padding: 15px; border-left: 4px solid #4caf50; margin: 20px 0;">
            <strong style="font-family: \'Chakra Petch\', sans-serif;">Zahlungsstatus: {{ order.transactions.first.stateMachineState.translated.name }}</strong>
        </div>
        <p style="margin-top: 30px;">
            Mit freundlichen Grüssen<br>
            <strong>Raven Weapon AG</strong>
        </p>
' . $emailFooter
    ],

    // Customer Registration
    'customer_register' => [
        'subject' => 'Willkommen bei Raven Weapon AG',
        'plainText' => '{% if customer.salutation %}{{ customer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ customer.firstName }} {{ customer.lastName }},

herzlich willkommen bei Raven Weapon AG!

Ihr Kundenkonto wurde erfolgreich erstellt. Sie können sich ab sofort mit Ihrer E-Mail-Adresse anmelden.

Mit freundlichen Grüssen
Raven Weapon AG',
        'html' => $emailHeader . '
        <h2 style="font-family: \'Chakra Petch\', sans-serif; font-weight: 600; color: #1a1a1a; margin: 0 0 20px 0; font-size: 24px;">Willkommen bei Raven Weapon AG!</h2>
        <p>
            {% if customer.salutation %}{{ customer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ customer.firstName }} {{ customer.lastName }},
        </p>
        <p>
            herzlich willkommen bei <strong>Raven Weapon AG</strong>!
        </p>
        <p>
            Ihr Kundenkonto wurde erfolgreich erstellt. Sie können sich ab sofort mit Ihrer E-Mail-Adresse anmelden.
        </p>
        <p style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.account.login.page\', {}, salesChannel.domains|first.url) }}" style="display: inline-block; background-color: #1a1a1a; color: #ffffff; padding: 14px 35px; text-decoration: none; font-family: \'Chakra Petch\', sans-serif; font-weight: 500;">Jetzt einloggen</a>
        </p>
        <p style="margin-top: 30px;">
            Mit freundlichen Grüssen<br>
            <strong>Raven Weapon AG</strong>
        </p>
' . $emailFooter
    ],

    // Password Recovery
    'customer.recovery.request' => [
        'subject' => 'Passwort zurücksetzen - Raven Weapon AG',
        'plainText' => '{% if customer.salutation %}{{ customer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ customer.firstName }} {{ customer.lastName }},

Sie haben eine Anfrage zum Zurücksetzen Ihres Passworts gestellt.

Klicken Sie auf folgenden Link, um ein neues Passwort festzulegen:
{{ rawUrl(\'frontend.account.recover.password.page\', { \'hash\': resetUrl.hash }, salesChannel.domains|first.url) }}

Falls Sie diese Anfrage nicht gestellt haben, können Sie diese E-Mail ignorieren.

Mit freundlichen Grüssen
Raven Weapon AG',
        'html' => $emailHeader . '
        <h2 style="font-family: \'Chakra Petch\', sans-serif; font-weight: 600; color: #1a1a1a; margin: 0 0 20px 0; font-size: 24px;">Passwort zurücksetzen</h2>
        <p>
            {% if customer.salutation %}{{ customer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ customer.firstName }} {{ customer.lastName }},
        </p>
        <p>
            Sie haben eine Anfrage zum Zurücksetzen Ihres Passworts gestellt.
        </p>
        <p style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.account.recover.password.page\', { \'hash\': resetUrl.hash }, salesChannel.domains|first.url) }}" style="display: inline-block; background-color: #1a1a1a; color: #ffffff; padding: 14px 35px; text-decoration: none; font-family: \'Chakra Petch\', sans-serif; font-weight: 500;">Neues Passwort festlegen</a>
        </p>
        <p style="font-size: 12px; color: #666;">
            Falls Sie diese Anfrage nicht gestellt haben, können Sie diese E-Mail ignorieren.
        </p>
        <p style="margin-top: 30px;">
            Mit freundlichen Grüssen<br>
            <strong>Raven Weapon AG</strong>
        </p>
' . $emailFooter
    ],

    // Order Cancelled
    'order.state.cancelled' => [
        'subject' => 'Bestellung storniert - {{ order.orderNumber }}',
        'plainText' => '{% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},

Ihre Bestellung {{ order.orderNumber }} wurde storniert.

Bei Fragen kontaktieren Sie uns bitte.

Mit freundlichen Grüssen
Raven Weapon AG',
        'html' => $emailHeader . '
        <h2 style="font-family: \'Chakra Petch\', sans-serif; font-weight: 600; color: #1a1a1a; margin: 0 0 20px 0; font-size: 24px;">Bestellung storniert</h2>
        <p>
            {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>
        <p>
            Ihre Bestellung <strong>{{ order.orderNumber }}</strong> wurde storniert.
        </p>
        <p>
            Bei Fragen kontaktieren Sie uns bitte.
        </p>
        <p style="margin-top: 30px;">
            Mit freundlichen Grüssen<br>
            <strong>Raven Weapon AG</strong>
        </p>
' . $emailFooter
    ],

    // Contact Form
    'contact_form' => [
        'subject' => 'Kontaktanfrage von {{ contactFormData.firstName }} {{ contactFormData.lastName }}',
        'plainText' => 'Neue Kontaktanfrage:

Name: {{ contactFormData.firstName }} {{ contactFormData.lastName }}
E-Mail: {{ contactFormData.email }}
Telefon: {{ contactFormData.phone }}
Betreff: {{ contactFormData.subject }}

Nachricht:
{{ contactFormData.comment }}',
        'html' => $emailHeader . '
        <h2 style="font-family: \'Chakra Petch\', sans-serif; font-weight: 600; color: #1a1a1a; margin: 0 0 20px 0; font-size: 24px;">Neue Kontaktanfrage</h2>
        <table style="width: 100%; margin-bottom: 20px;">
            <tr>
                <td style="padding: 8px 0; font-weight: 500; width: 120px;">Name:</td>
                <td style="padding: 8px 0;">{{ contactFormData.firstName }} {{ contactFormData.lastName }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-weight: 500;">E-Mail:</td>
                <td style="padding: 8px 0;">{{ contactFormData.email }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-weight: 500;">Telefon:</td>
                <td style="padding: 8px 0;">{{ contactFormData.phone }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-weight: 500;">Betreff:</td>
                <td style="padding: 8px 0;">{{ contactFormData.subject }}</td>
            </tr>
        </table>
        <div style="background-color: #f5f5f5; padding: 15px; margin-top: 20px;">
            <h4 style="font-family: \'Chakra Petch\', sans-serif; margin: 0 0 10px 0;">Nachricht:</h4>
            <p style="margin: 0; white-space: pre-wrap;">{{ contactFormData.comment }}</p>
        </div>
' . $emailFooter
    ]
];

// Fetch all mail template types
echo "Fetching mail template types...\n";
$typesResult = apiRequest($API_URL, $token, 'POST', 'search/mail-template-type', [
    'limit' => 100,
    'includes' => [
        'mail_template_type' => ['id', 'technicalName', 'name']
    ]
]);

if (!empty($typesResult['data'])) {
    $typeMap = [];
    foreach ($typesResult['data'] as $type) {
        $id = $type['id'] ?? 'no-id';
        $techName = $type['attributes']['technicalName'] ?? $type['technicalName'] ?? 'unknown';
        $typeMap[$techName] = $id;
    }

    echo "Found " . count($typeMap) . " template types\n\n";

    // Update each template
    $updated = 0;
    $failed = 0;

    foreach ($templates as $techName => $content) {
        echo "Processing: $techName... ";

        if (!isset($typeMap[$techName])) {
            echo "TYPE NOT FOUND\n";
            $failed++;
            continue;
        }

        $typeId = $typeMap[$techName];

        // Find the template
        $templateResult = apiRequest($API_URL, $token, 'POST', 'search/mail-template', [
            'filter' => [
                ['type' => 'equals', 'field' => 'mailTemplateTypeId', 'value' => $typeId]
            ]
        ]);

        if (!empty($templateResult['data'])) {
            $templateId = $templateResult['data'][0]['id'];

            // Update the template
            $updateResult = apiRequest($API_URL, $token, 'PATCH', 'mail-template/' . $templateId, [
                'subject' => $content['subject'],
                'contentPlain' => $content['plainText'],
                'contentHtml' => $content['html']
            ]);

            if (isset($updateResult['errors'])) {
                echo "ERROR\n";
                $failed++;
            } else {
                echo "SUCCESS\n";
                $updated++;
            }
        } else {
            echo "TEMPLATE NOT FOUND\n";
            $failed++;
        }
    }

    echo "\n========================================\n";
    echo "Updated: $updated templates\n";
    echo "Failed: $failed templates\n";
}

echo "\nDone!\n";
