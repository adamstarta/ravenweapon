<?php
/**
 * Fix Order Confirmation Email - Restore full HTML + fix plain text
 * The |currency filter was broken, replaced with |number_format
 */

$pdo = new PDO("mysql:host=localhost;dbname=shopware", "root", "root");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$shopUrl = 'https://shop.ravenweapon.ch';
$logoUrl = $shopUrl . '/bundles/raventheme/assets/raven-logo.png';

// German HTML - Full beautiful template
$germanHtml = <<<'HTML'
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
    <!-- Header with Logo -->
    <div style="text-align: center; padding: 30px 20px; border-bottom: 3px solid #F59E0B;">
        <img src="https://shop.ravenweapon.ch/bundles/raventheme/assets/raven-logo.png" alt="Raven Weapon" style="max-width: 200px; height: auto;">
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

        <!-- Addresses Row -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">
            <tr>
                <!-- Lieferadresse -->
                <td style="width: 33%; vertical-align: top; padding: 15px; border: 1px solid #e5e7eb;">
                    <h4 style="color: #F59E0B; margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">LIEFERADRESSE</h4>
                    <p style="margin: 0; font-size: 14px; line-height: 1.5; color: #374151;">
                        {{ order.deliveries.first.shippingOrderAddress.firstName }} {{ order.deliveries.first.shippingOrderAddress.lastName }}<br>
                        {{ order.deliveries.first.shippingOrderAddress.street }}<br>
                        {{ order.deliveries.first.shippingOrderAddress.zipcode }} {{ order.deliveries.first.shippingOrderAddress.city }}<br>
                        {{ order.deliveries.first.shippingOrderAddress.country.translated.name }}
                    </p>
                </td>
                <!-- Rechnungsadresse -->
                <td style="width: 33%; vertical-align: top; padding: 15px; border: 1px solid #e5e7eb; border-left: none;">
                    <h4 style="color: #F59E0B; margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">RECHNUNGSADRESSE</h4>
                    <p style="margin: 0; font-size: 14px; line-height: 1.5; color: #374151;">
                        {{ order.billingAddress.firstName }} {{ order.billingAddress.lastName }}<br>
                        {{ order.billingAddress.street }}<br>
                        {{ order.billingAddress.zipcode }} {{ order.billingAddress.city }}<br>
                        {{ order.billingAddress.country.translated.name }}
                    </p>
                </td>
                <!-- Informationen -->
                <td style="width: 33%; vertical-align: top; padding: 15px; border: 1px solid #e5e7eb; border-left: none;">
                    <h4 style="color: #F59E0B; margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">INFORMATIONEN</h4>
                    <p style="margin: 0; font-size: 14px; line-height: 1.5; color: #374151;">
                        <strong>Zahlung:</strong> {{ order.transactions.first.paymentMethod.translated.name }}<br>
                        <strong>Versand:</strong> {{ order.deliveries.first.shippingMethod.translated.name }}
                    </p>
                </td>
            </tr>
        </table>

        <!-- Order Items Table -->
        <div style="margin-bottom: 25px; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f9fafb;">
                        <th style="padding: 12px 15px; text-align: left; color: #F59E0B; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">PRODUKT</th>
                        <th style="padding: 12px 15px; text-align: center; color: #F59E0B; font-size: 12px; text-transform: uppercase;">MENGE</th>
                        <th style="padding: 12px 15px; text-align: right; color: #F59E0B; font-size: 12px; text-transform: uppercase;">PREIS</th>
                    </tr>
                </thead>
                <tbody>
                    {% for lineItem in order.nestedLineItems %}
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 15px;">
                            <table><tr>
                                {% if lineItem.cover %}
                                <td style="vertical-align: top; padding-right: 15px;">
                                    <img src="{{ lineItem.cover.url }}" alt="{{ lineItem.label }}" style="width: 60px; height: 60px; object-fit: contain; border: 1px solid #e5e7eb; border-radius: 4px;">
                                </td>
                                {% endif %}
                                <td style="vertical-align: top;">
                                    <strong style="font-size: 14px;">{{ lineItem.label }}</strong>
                                    {% if lineItem.payload.productNumber is defined %}<br><small style="color: #6b7280;">Art.Nr: {{ lineItem.payload.productNumber }}</small>{% endif %}
                                    {% if lineItem.payload.options|length > 0 %}
                                    <br><span style="color: #F59E0B; font-size: 13px;">Variante: {{ lineItem.payload.options|map(o => o.option)|join(' / ') }}</span>
                                    {% endif %}
                                </td>
                            </tr></table>
                        </td>
                        <td style="padding: 15px; text-align: center; color: #6b7280;">{{ lineItem.quantity }}</td>
                        <td style="padding: 15px; text-align: right; font-weight: 600;">CHF {{ lineItem.totalPrice|number_format(2, '.', '') }}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>

        <!-- Order Summary Box -->
        <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
            <h4 style="color: #F59E0B; margin: 0 0 15px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">ZUSAMMENFASSUNG</h4>
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 8px 0; color: #6b7280;">Zwischensumme</td>
                    <td style="padding: 8px 0; text-align: right;">CHF {{ order.amountNet|number_format(2, '.', '') }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #6b7280;">Versand</td>
                    <td style="padding: 8px 0; text-align: right;">CHF {{ order.shippingTotal|number_format(2, '.', '') }}</td>
                </tr>
                <tr style="border-top: 2px solid #1a1a1a;">
                    <td style="padding: 15px 0; font-size: 16px;"><strong>Gesamtsumme</strong></td>
                    <td style="padding: 15px 0; text-align: right; font-size: 16px; font-weight: 700;">CHF {{ order.amountTotal|number_format(2, '.', '') }}</td>
                </tr>
            </table>
        </div>

        <!-- Payment Info for Vorkasse -->
        {% if order.transactions.first.paymentMethod.translated.name == 'Vorkasse' %}
        <div style="background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
            <h4 style="color: #92400E; margin: 0 0 15px 0;">📋 Zahlungsinformationen</h4>
            <p style="margin: 0 0 15px 0; color: #92400E;">Bitte überweisen Sie den Betrag auf folgendes Konto:</p>
            <table style="width: 100%; font-size: 14px; background: #fffbeb; border-radius: 6px;">
                <tr><td style="padding: 8px; color: #666;">Kontoinhaber</td><td style="padding: 8px;"><strong>Raven Weapon AG</strong></td></tr>
                <tr><td style="padding: 8px; color: #666;">Bank</td><td style="padding: 8px;"><strong>PostFinance</strong></td></tr>
                <tr><td style="padding: 8px; color: #666;">IBAN</td><td style="padding: 8px;"><strong>CH6009000000165059892</strong></td></tr>
                <tr><td style="padding: 8px; color: #666;">BIC/SWIFT</td><td style="padding: 8px;"><strong>POFICHBEXXX</strong></td></tr>
                <tr><td style="padding: 8px; color: #666;">Verwendungszweck</td><td style="padding: 8px;"><strong style="color: #F59E0B;">{{ order.orderNumber }}</strong></td></tr>
            </table>
            <p style="margin: 15px 0 0 0; font-size: 13px; color: #92400E;">ℹ Nach Zahlungseingang wird Ihre Bestellung bearbeitet.</p>
        </div>
        {% endif %}

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode }, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
                Bestellung ansehen
            </a>
        </div>

        <!-- Footer -->
        <div style="border-top: 1px solid #eee; padding-top: 20px; margin-top: 30px;">
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
            <a href="https://shop.ravenweapon.ch" style="color: #F59E0B; text-decoration: none;">www.ravenweapon.ch</a>
        </p>
    </div>
</div>
HTML;

// German Plain - FIXED (no |currency filter)
$germanPlain = <<<'PLAIN'
Bestellbestätigung - Raven Weapon AG

Bestellnummer: {{ order.orderNumber }}
Gesamtsumme: CHF {{ order.amountTotal|number_format(2, '.', '') }}

Vielen Dank für Ihre Bestellung!

{% for lineItem in order.nestedLineItems %}
- {{ lineItem.label }} x {{ lineItem.quantity }} = CHF {{ lineItem.totalPrice|number_format(2, '.', '') }}
{% endfor %}

{% if order.transactions.first.paymentMethod.translated.name == 'Vorkasse' %}
ZAHLUNGSINFORMATIONEN
=====================
Kontoinhaber: Raven Weapon AG
Bank: PostFinance
IBAN: CH6009000000165059892
BIC/SWIFT: POFICHBEXXX
Verwendungszweck: {{ order.orderNumber }}
{% endif %}

Mit freundlichen Grüssen
Raven Weapon AG

www.ravenweapon.ch
PLAIN;

// English Plain - FIXED
$englishPlain = <<<'PLAIN'
Order Confirmation - Raven Weapon AG

Order Number: {{ order.orderNumber }}
Total: CHF {{ order.amountTotal|number_format(2, '.', '') }}

Thank you for your order!

{% for lineItem in order.nestedLineItems %}
- {{ lineItem.label }} x {{ lineItem.quantity }} = CHF {{ lineItem.totalPrice|number_format(2, '.', '') }}
{% endfor %}

Best regards,
Raven Weapon AG

www.ravenweapon.ch
PLAIN;

// English HTML (translate German HTML)
$englishHtml = str_replace(
    ['Guten Tag', 'vielen Dank für Ihre Bestellung bei', 'LIEFERADRESSE', 'RECHNUNGSADRESSE', 'INFORMATIONEN',
     'Zahlung:', 'Versand:', 'PRODUKT', 'MENGE', 'PREIS', 'ZUSAMMENFASSUNG', 'Zwischensumme',
     'Gesamtsumme', 'Zahlungsinformationen', 'Bitte überweisen Sie den Betrag auf folgendes Konto:',
     'Kontoinhaber', 'Nach Zahlungseingang wird Ihre Bestellung bearbeitet.', 'Bestellung ansehen',
     'Mit freundlichen Grüssen', 'Schweiz', 'Variante:'],
    ['Hello', 'Thank you for your order at', 'SHIPPING ADDRESS', 'BILLING ADDRESS', 'INFORMATION',
     'Payment:', 'Shipping:', 'PRODUCT', 'QTY', 'PRICE', 'SUMMARY', 'Subtotal',
     'Total', 'Payment Information', 'Please transfer the amount to the following account:',
     'Account Holder', 'Your order will be processed after payment is received.', 'View Order',
     'Best regards', 'Switzerland', 'Variant:'],
    $germanHtml
);

// Get template ID
$stmt = $pdo->query("SELECT LOWER(HEX(mt.id)) as id FROM mail_template mt
    JOIN mail_template_type mtt ON mt.mail_template_type_id = mtt.id
    WHERE mtt.technical_name = 'order_confirmation_mail'");
$templateId = $stmt->fetchColumn();

echo "Template ID: $templateId\n";

// Update German (0191c12cc15e72189d57328fb3d2d987)
$stmt = $pdo->prepare("UPDATE mail_template_translation
    SET content_html = ?, content_plain = ?,
        subject = 'Bestellbestätigung - Bestellung {{ order.orderNumber }} | Raven Weapon AG',
        updated_at = NOW()
    WHERE LOWER(HEX(mail_template_id)) = ? AND LOWER(HEX(language_id)) = '0191c12cc15e72189d57328fb3d2d987'");
$stmt->execute([$germanHtml, $germanPlain, $templateId]);
echo "German template updated: " . $stmt->rowCount() . " rows\n";

// Update English (2fbb5fe2e29a4d70aa5854ce7ce3e20b)
$stmt = $pdo->prepare("UPDATE mail_template_translation
    SET content_html = ?, content_plain = ?,
        subject = 'Order Confirmation - Order {{ order.orderNumber }} | Raven Weapon AG',
        updated_at = NOW()
    WHERE LOWER(HEX(mail_template_id)) = ? AND LOWER(HEX(language_id)) = '2fbb5fe2e29a4d70aa5854ce7ce3e20b'");
$stmt->execute([$englishHtml, $englishPlain, $templateId]);
echo "English template updated: " . $stmt->rowCount() . " rows\n";

echo "\n✅ Order confirmation email template restored with full layout!\n";
echo "The |currency filter has been replaced with |number_format to prevent errors.\n";
