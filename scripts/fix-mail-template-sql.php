<?php
/**
 * Fix mail template via direct SQL update
 */

$pdo = new PDO('mysql:host=127.0.0.1;dbname=shopware', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Fixing order confirmation mail template via SQL...\n\n";

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

    <table style="width: 100%; margin-top: 20px;">
        <tr>
            {% if shippingAddress %}
            <td style="vertical-align: top; padding-right: 10px; width: 50%;">
                <h4 style="color: #F59E0B; margin-bottom: 10px;">Lieferadresse</h4>
                <p style="margin: 0;">{{ shippingAddress.firstName }} {{ shippingAddress.lastName }}<br>
                {{ shippingAddress.street }}<br>
                {{ shippingAddress.zipcode }} {{ shippingAddress.city }}<br>
                {% if shippingAddress.country %}{{ shippingAddress.country.translated.name }}{% endif %}</p>
            </td>
            {% endif %}

            {% if billingAddr %}
            <td style="vertical-align: top; padding-left: 10px; width: 50%;">
                <h4 style="color: #F59E0B; margin-bottom: 10px;">Rechnungsadresse</h4>
                <p style="margin: 0;">{{ billingAddr.firstName }} {{ billingAddr.lastName }}<br>
                {{ billingAddr.street }}<br>
                {{ billingAddr.zipcode }} {{ billingAddr.city }}<br>
                {% if billingAddr.country %}{{ billingAddr.country.translated.name }}{% endif %}</p>
            </td>
            {% endif %}
        </tr>
    </table>

    <p style="margin-top: 20px;">
        <a href="{{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode }, salesChannel.domains|first.url) }}"
           style="display: inline-block; background: #F59E0B; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
            Bestellung ansehen
        </a>
    </p>

    <p style="margin-top: 30px;">Mit freundlichen Grüssen<br><strong>Raven Weapon AG</strong></p>
</div>
EOT;

// Find the template ID for order confirmation
$stmt = $pdo->query("SELECT HEX(mt.id) as id FROM mail_template mt
                     JOIN mail_template_type mtt ON mt.mail_template_type_id = mtt.id
                     WHERE mtt.technical_name = 'order_confirmation_mail'");
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    die("Template not found!\n");
}

$templateId = $template['id'];
echo "Found template ID: $templateId\n";

// Update all translations
$stmt = $pdo->prepare("UPDATE mail_template_translation
                       SET content_plain = ?, content_html = ?, updated_at = NOW()
                       WHERE mail_template_id = UNHEX(?)");
$stmt->execute([$newContentPlain, $newContentHtml, $templateId]);

$affected = $stmt->rowCount();
echo "Updated $affected translations\n\n";

echo "=== SUCCESS! ===\n";
echo "The mail template has been fixed. Changes:\n";
echo "- Uses order.addresses|first instead of order.billingAddress (which was null)\n";
echo "- Added null-safe checks with {% if %}\n";
echo "- Improved HTML layout\n\n";
echo "Please place a new test order to verify emails are working!\n";
