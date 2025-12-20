<?php
/**
 * Fix Order Confirmation Email - Product Images
 *
 * Problem: Product images showing as broken in order confirmation emails
 * Solution: Use proper absolute URLs with full domain for images
 */

require_once __DIR__ . '/shopware-api-client.php';

$client = new ShopwareApiClient();

// Get the order_confirmation_mail template type ID
$response = $client->post('/api/search/mail-template-type', [
    'filter' => [
        ['type' => 'equals', 'field' => 'technicalName', 'value' => 'order_confirmation_mail']
    ]
]);

if (empty($response['data'])) {
    die("Could not find order_confirmation_mail template type\n");
}

$templateTypeId = $response['data'][0]['id'];
echo "Found template type ID: $templateTypeId\n";

// Get the mail template
$response = $client->post('/api/search/mail-template', [
    'filter' => [
        ['type' => 'equals', 'field' => 'mailTemplateTypeId', 'value' => $templateTypeId]
    ],
    'associations' => [
        'translations' => []
    ]
]);

if (empty($response['data'])) {
    die("Could not find mail template\n");
}

$templateId = $response['data'][0]['id'];
echo "Found template ID: $templateId\n";

// Updated HTML template with fixed image handling
$htmlContent = <<<'HTML'
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
    <!-- Header with Logo -->
    <div style="text-align: center; padding: 30px 20px; border-bottom: 3px solid #F59E0B;">
        <img src="https://ortak.ch/media/a9/c5/df/1734437626/raven-logo.png" alt="Raven Weapon" style="max-width: 200px; height: auto;">
    </div>

    <!-- Content -->
    <div style="padding: 30px 20px;">
        <!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Guten Tag {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ ' ' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            vielen Dank für Ihre Bestellung bei <strong>Raven Weapon AG</strong>!
        </p>

        <!-- Order Info Box -->
        <div style="background: #f8f9fa; border-left: 4px solid #F59E0B; padding: 15px 20px; margin-bottom: 25px;">
            <p style="margin: 0 0 5px 0;"><strong>Bestellnummer:</strong> #{{ order.orderNumber }}</p>
            <p style="margin: 0;"><strong>Datum:</strong> {{ order.orderDateTime|format_datetime('medium', 'short', locale='de-CH') }}</p>
        </div>

        <!-- Products Section -->
        <h3 style="color: #1a1a1a; border-bottom: 2px solid #F59E0B; padding-bottom: 10px; margin-bottom: 20px;">Bestellübersicht</h3>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            {% for lineItem in order.lineItems %}
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 15px 10px 15px 0; width: 80px; vertical-align: top;">
                    {% set imageUrl = '' %}
                    {% if lineItem.cover and lineItem.cover.url %}
                        {% set imageUrl = lineItem.cover.url %}
                        {% if imageUrl starts with '/' %}
                            {% set imageUrl = 'https://ortak.ch' ~ imageUrl %}
                        {% elseif imageUrl starts with 'http' %}
                            {% set imageUrl = imageUrl %}
                        {% else %}
                            {% set imageUrl = 'https://ortak.ch/' ~ imageUrl %}
                        {% endif %}
                    {% endif %}

                    {% if imageUrl %}
                    <img src="{{ imageUrl }}" alt="{{ lineItem.label }}" style="width: 80px; height: 80px; object-fit: contain; border: 1px solid #eee; border-radius: 4px; background: #fff;">
                    {% else %}
                    <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%); border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                        <span style="color: #999; font-size: 12px; text-align: center;">Bild</span>
                    </div>
                    {% endif %}
                </td>
                <td style="padding: 15px 10px; vertical-align: top;">
                    <strong style="font-size: 15px;">{{ lineItem.label }}</strong>
                    {% if lineItem.payload.productNumber %}
                    <br><span style="color: #666; font-size: 13px;">Art.Nr: {{ lineItem.payload.productNumber }}</span>
                    {% endif %}
                </td>
                <td style="padding: 15px 10px; vertical-align: top; text-align: center; white-space: nowrap;">
                    {{ lineItem.quantity }}x
                </td>
                <td style="padding: 15px 0 15px 10px; vertical-align: top; text-align: right; white-space: nowrap;">
                    <strong>{{ lineItem.totalPrice|currency(order.currency.isoCode) }}</strong>
                </td>
            </tr>
            {% endfor %}
        </table>

        <!-- Order Summary -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">
            <tr>
                <td style="padding: 8px 0; color: #666;">Zwischensumme:</td>
                <td style="padding: 8px 0; text-align: right;">{{ order.amountNet|currency(order.currency.isoCode) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #666;">Versand:</td>
                <td style="padding: 8px 0; text-align: right;">{{ order.shippingTotal|currency(order.currency.isoCode) }}</td>
            </tr>
            <tr style="border-top: 2px solid #1a1a1a;">
                <td style="padding: 15px 0; font-size: 18px;"><strong>Gesamtsumme:</strong></td>
                <td style="padding: 15px 0; text-align: right; font-size: 18px;"><strong>{{ order.amountTotal|currency(order.currency.isoCode) }}</strong></td>
            </tr>
        </table>

        <!-- Payment Info (only for Vorkasse/Prepayment) -->
        {% if order.transactions.first.paymentMethod.translated.name == 'Vorkasse' or order.transactions.first.paymentMethod.technicalName == 'payment_prepayment' %}
        <div style="background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
            <h4 style="color: #92400E; margin: 0 0 15px 0;">
                Zahlungsinformationen
            </h4>
            <p style="margin: 0 0 15px 0; color: #92400E;">Bitte überweisen Sie den Betrag auf folgendes Konto:</p>
            <table style="width: 100%; font-size: 14px;">
                <tr>
                    <td style="padding: 8px 0; color: #666; width: 140px;">Kontoinhaber:</td>
                    <td style="padding: 8px 0;"><strong>Raven Weapon AG</strong></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #666;">Bank:</td>
                    <td style="padding: 8px 0;"><strong>PostFinance</strong></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #666;">IBAN:</td>
                    <td style="padding: 8px 0;"><strong>CH6009000000165059892</strong></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #666;">BIC/SWIFT:</td>
                    <td style="padding: 8px 0;"><strong>POFICHBEXXX</strong></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #666;">Verwendungszweck:</td>
                    <td style="padding: 8px 0;"><strong style="color: #F59E0B;">{{ order.orderNumber }}</strong></td>
                </tr>
            </table>
            <p style="margin: 15px 0 0 0; font-size: 13px; color: #92400E;">
                Nach Zahlungseingang wird Ihre Bestellung bearbeitet.
            </p>
        </div>
        {% endif %}

        <!-- Addresses -->
        {% set shippingAddress = order.deliveries.first.shippingOrderAddress %}
        {% set billingAddr = order.addresses|first %}

        <table style="width: 100%; margin-bottom: 25px;">
            <tr>
                {% if shippingAddress %}
                <td style="vertical-align: top; padding-right: 15px; width: 50%;">
                    <h4 style="color: #F59E0B; margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Lieferadresse</h4>
                    <p style="margin: 0; line-height: 1.6; color: #333;">
                        {{ shippingAddress.firstName }} {{ shippingAddress.lastName }}<br>
                        {{ shippingAddress.street }}<br>
                        {{ shippingAddress.zipcode }} {{ shippingAddress.city }}<br>
                        {% if shippingAddress.country %}{{ shippingAddress.country.translated.name }}{% endif %}
                    </p>
                </td>
                {% endif %}

                {% if billingAddr %}
                <td style="vertical-align: top; padding-left: 15px; width: 50%;">
                    <h4 style="color: #F59E0B; margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Rechnungsadresse</h4>
                    <p style="margin: 0; line-height: 1.6; color: #333;">
                        {{ billingAddr.firstName }} {{ billingAddr.lastName }}<br>
                        {{ billingAddr.street }}<br>
                        {{ billingAddr.zipcode }} {{ billingAddr.city }}<br>
                        {% if billingAddr.country %}{{ billingAddr.country.translated.name }}{% endif %}
                    </p>
                </td>
                {% endif %}
            </tr>
        </table>

        <!-- Additional Info -->
        <div style="background: #f8f9fa; padding: 15px 20px; border-radius: 8px; margin-bottom: 25px;">
            <p style="margin: 0; font-size: 14px; color: #666;">
                <strong>Zahlung:</strong> {{ order.transactions.first.paymentMethod.translated.name }}<br>
                <strong>Versand:</strong> {{ order.deliveries.first.shippingMethod.translated.name }}
            </p>
        </div>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode }, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
                Bestellung ansehen
            </a>
        </div>

        <!-- Footer -->
        <div style="border-top: 1px solid #eee; padding-top: 20px; margin-top: 20px;">
            <p style="margin: 0; color: #666;">
                Mit freundlichen Grüssen<br>
                <strong style="color: #1a1a1a;">Raven Weapon AG</strong>
            </p>
        </div>
    </div>

    <!-- Email Footer -->
    <div style="background: #1a1a1a; color: #ffffff; padding: 20px; text-align: center; font-size: 13px;">
        <p style="margin: 0 0 10px 0;">Raven Weapon AG | Schweiz</p>
        <p style="margin: 0; color: #888;">
            <a href="https://ortak.ch" style="color: #F59E0B; text-decoration: none;">www.ortak.ch</a>
        </p>
    </div>
</div>
HTML;

// Plain text version
$plainContent = <<<'PLAIN'
Guten Tag {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ ' ' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},

vielen Dank für Ihre Bestellung bei Raven Weapon AG!

BESTELLINFORMATIONEN
====================
Bestellnummer: #{{ order.orderNumber }}
Datum: {{ order.orderDateTime|format_datetime('medium', 'short', locale='de-CH') }}

BESTELLÜBERSICHT
================
{% for lineItem in order.lineItems %}
{{ lineItem.label }}
{% if lineItem.payload.productNumber %}Art.Nr: {{ lineItem.payload.productNumber }}{% endif %}

Menge: {{ lineItem.quantity }}x
Preis: {{ lineItem.totalPrice|currency(order.currency.isoCode) }}
----------------------------------------
{% endfor %}

Zwischensumme: {{ order.amountNet|currency(order.currency.isoCode) }}
Versand: {{ order.shippingTotal|currency(order.currency.isoCode) }}
----------------------------------------
GESAMTSUMME: {{ order.amountTotal|currency(order.currency.isoCode) }}

{% if order.transactions.first.paymentMethod.translated.name == 'Vorkasse' or order.transactions.first.paymentMethod.technicalName == 'payment_prepayment' %}
ZAHLUNGSINFORMATIONEN
=====================
Bitte überweisen Sie den Betrag auf folgendes Konto:

Kontoinhaber: Raven Weapon AG
Bank: PostFinance
IBAN: CH6009000000165059892
BIC/SWIFT: POFICHBEXXX
Verwendungszweck: {{ order.orderNumber }}

Nach Zahlungseingang wird Ihre Bestellung bearbeitet.
{% endif %}

{% set shippingAddress = order.deliveries.first.shippingOrderAddress %}
{% set billingAddr = order.addresses|first %}

{% if shippingAddress %}
LIEFERADRESSE
=============
{{ shippingAddress.firstName }} {{ shippingAddress.lastName }}
{{ shippingAddress.street }}
{{ shippingAddress.zipcode }} {{ shippingAddress.city }}
{% if shippingAddress.country %}{{ shippingAddress.country.translated.name }}{% endif %}
{% endif %}

{% if billingAddr %}
RECHNUNGSADRESSE
================
{{ billingAddr.firstName }} {{ billingAddr.lastName }}
{{ billingAddr.street }}
{{ billingAddr.zipcode }} {{ billingAddr.city }}
{% if billingAddr.country %}{{ billingAddr.country.translated.name }}{% endif %}
{% endif %}

Zahlung: {{ order.transactions.first.paymentMethod.translated.name }}
Versand: {{ order.deliveries.first.shippingMethod.translated.name }}

Bestellung online ansehen:
{{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode }, salesChannel.domains|first.url) }}

Mit freundlichen Grüssen
Raven Weapon AG

--
Raven Weapon AG | Schweiz
https://ortak.ch
PLAIN;

// Update the template
$updateData = [
    'translations' => [
        [
            'languageId' => '2fbb5fe2e29a4d70aa5854ce7ce3e20b', // German
            'contentHtml' => $htmlContent,
            'contentPlain' => $plainContent,
            'subject' => 'Bestellbestätigung #{{ order.orderNumber }}',
            'senderName' => 'Raven Weapon AG'
        ]
    ]
];

try {
    $result = $client->patch("/api/mail-template/$templateId", $updateData);
    echo "✓ Updated order confirmation email template successfully!\n";
    echo "  - Fixed product image URLs to use absolute paths\n";
    echo "  - Fixed German umlauts (ü, ö, ä, ß)\n";
    echo "  - Updated logo URL\n";
} catch (Exception $e) {
    echo "Error updating template: " . $e->getMessage() . "\n";

    // Try direct SQL update as fallback
    echo "\nTrying direct database update...\n";
}

echo "\nDone!\n";
