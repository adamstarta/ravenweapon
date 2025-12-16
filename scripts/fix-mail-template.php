<?php
/**
 * Fix the order confirmation mail template
 * The issue: order.billingAddress is null because association not loaded
 * Fix: Use order.addresses|first or order.orderCustomer for customer info
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $httpCode, 'data' => json_decode($response, true)];
}

$token = getToken($API_URL);
if (!$token) {
    die("Failed to get token\n");
}
echo "Token obtained\n\n";

// Find the order confirmation mail template
echo "Finding order confirmation mail template...\n";
$result = apiRequest($API_URL, $token, 'POST', 'search/mail-template', [
    'filter' => [
        [
            'type' => 'equals',
            'field' => 'mailTemplateType.technicalName',
            'value' => 'order_confirmation_mail'
        ]
    ],
    'associations' => [
        'translations' => []
    ]
]);

if (empty($result['data']['data'])) {
    die("Mail template not found\n");
}

$template = $result['data']['data'][0];
$templateId = $template['id'];
echo "Found template ID: $templateId\n\n";

// Get translations
$translations = apiRequest($API_URL, $token, 'POST', 'search/mail-template-translation', [
    'filter' => [
        ['type' => 'equals', 'field' => 'mailTemplateId', 'value' => $templateId]
    ]
]);

echo "Found " . count($translations['data']['data'] ?? []) . " translations\n\n";

// New plain text content - FIXED VERSION
$newContentPlain = <<<'EOT'
{% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ ' ' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},

vielen Dank für Ihre Bestellung bei {{ salesChannel.translated.name }}!

Ihre Bestellnummer: {{ order.orderNumber }}
Bestelldatum: {{ order.orderDateTime|format_datetime('medium', 'short', locale='de-CH') }}

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

{% set shippingAddress = order.deliveries.first.shippingOrderAddress %}
{% if shippingAddress %}
Lieferadresse:
{{ shippingAddress.firstName }} {{ shippingAddress.lastName }}
{{ shippingAddress.street }}
{{ shippingAddress.zipcode }} {{ shippingAddress.city }}
{% if shippingAddress.country %}{{ shippingAddress.country.translated.name }}{% endif %}
{% endif %}

{% set billingAddr = order.addresses|first %}
{% if billingAddr %}
Rechnungsadresse:
{{ billingAddr.firstName }} {{ billingAddr.lastName }}
{{ billingAddr.street }}
{{ billingAddr.zipcode }} {{ billingAddr.city }}
{% if billingAddr.country %}{{ billingAddr.country.translated.name }}{% endif %}
{% endif %}

Sie können den Status Ihrer Bestellung jederzeit hier einsehen: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode }, salesChannel.domains|first.url) }}

Mit freundlichen Grüssen
Raven Weapon AG
EOT;

// New HTML content - FIXED VERSION
$newContentHtml = <<<'EOT'
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <p>{% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ ' ' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},</p>

    <p>vielen Dank für Ihre Bestellung bei <strong>{{ salesChannel.translated.name }}</strong>!</p>

    <p><strong>Ihre Bestellnummer:</strong> {{ order.orderNumber }}<br>
    <strong>Bestelldatum:</strong> {{ order.orderDateTime|format_datetime('medium', 'short', locale='de-CH') }}</p>

    <h3 style="border-bottom: 2px solid #F59E0B; padding-bottom: 5px;">Bestellübersicht</h3>
    <table style="width: 100%; border-collapse: collapse;">
        {% for lineItem in order.lineItems %}
        <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #eee;">{{ lineItem.quantity }}x {{ lineItem.label }}</td>
            <td style="padding: 8px 0; border-bottom: 1px solid #eee; text-align: right;">{{ lineItem.totalPrice|currency(order.currency.isoCode) }}</td>
        </tr>
        {% endfor %}
        <tr>
            <td style="padding: 8px 0;">Zwischensumme:</td>
            <td style="padding: 8px 0; text-align: right;">{{ order.amountNet|currency(order.currency.isoCode) }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0;">Versand:</td>
            <td style="padding: 8px 0; text-align: right;">{{ order.shippingTotal|currency(order.currency.isoCode) }}</td>
        </tr>
        {% for calculatedTax in order.price.calculatedTaxes %}
        <tr>
            <td style="padding: 8px 0;">MwSt. {{ calculatedTax.taxRate }}%:</td>
            <td style="padding: 8px 0; text-align: right;">{{ calculatedTax.tax|currency(order.currency.isoCode) }}</td>
        </tr>
        {% endfor %}
        <tr style="font-weight: bold; background: #f5f5f5;">
            <td style="padding: 12px 8px;">Gesamtbetrag:</td>
            <td style="padding: 12px 8px; text-align: right;">{{ order.amountTotal|currency(order.currency.isoCode) }}</td>
        </tr>
    </table>

    {% set shippingAddress = order.deliveries.first.shippingOrderAddress %}
    {% set billingAddr = order.addresses|first %}

    <div style="display: flex; margin-top: 20px;">
        {% if shippingAddress %}
        <div style="flex: 1; padding-right: 10px;">
            <h4 style="color: #F59E0B;">Lieferadresse</h4>
            <p>{{ shippingAddress.firstName }} {{ shippingAddress.lastName }}<br>
            {{ shippingAddress.street }}<br>
            {{ shippingAddress.zipcode }} {{ shippingAddress.city }}<br>
            {% if shippingAddress.country %}{{ shippingAddress.country.translated.name }}{% endif %}</p>
        </div>
        {% endif %}

        {% if billingAddr %}
        <div style="flex: 1; padding-left: 10px;">
            <h4 style="color: #F59E0B;">Rechnungsadresse</h4>
            <p>{{ billingAddr.firstName }} {{ billingAddr.lastName }}<br>
            {{ billingAddr.street }}<br>
            {{ billingAddr.zipcode }} {{ billingAddr.city }}<br>
            {% if billingAddr.country %}{{ billingAddr.country.translated.name }}{% endif %}</p>
        </div>
        {% endif %}
    </div>

    <p style="margin-top: 20px;">
        <a href="{{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode }, salesChannel.domains|first.url) }}"
           style="background: #F59E0B; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
            Bestellung ansehen
        </a>
    </p>

    <p style="margin-top: 30px;">Mit freundlichen Grüssen<br><strong>Raven Weapon AG</strong></p>
</div>
EOT;

// Update each translation
foreach ($translations['data']['data'] ?? [] as $trans) {
    $transId = $trans['id'];
    $langId = $trans['languageId'];

    echo "Updating translation $transId (lang: $langId)...\n";

    $updateResult = apiRequest($API_URL, $token, 'PATCH', 'mail-template-translation/' . $templateId . '/' . $langId, [
        'contentPlain' => $newContentPlain,
        'contentHtml' => $newContentHtml
    ]);

    if ($updateResult['status'] == 204 || $updateResult['status'] == 200) {
        echo "  SUCCESS!\n";
    } else {
        echo "  ERROR: " . json_encode($updateResult) . "\n";
    }
}

echo "\n=== Mail template fixed! ===\n";
echo "The template now uses:\n";
echo "- order.addresses|first instead of order.billingAddress\n";
echo "- Null-safe checks with {% if %}\n";
echo "\nPlease place a new test order to verify emails are working.\n";
