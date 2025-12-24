<?php
/**
 * Fix Order Confirmation Email - WITH PRODUCT IMAGES
 * Adds product images to the order items table
 */

$pdo = new PDO("mysql:host=localhost;dbname=shopware", "root", "root");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// German HTML - WITH PRODUCT IMAGES
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
            Guten Tag {{ order.orderCustomer.firstName|default('') }} {{ order.orderCustomer.lastName|default('') }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            vielen Dank für Ihre Bestellung bei <strong>Raven Weapon AG</strong>!
        </p>

        <!-- Order Number Box -->
        <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; margin-bottom: 25px; text-align: center;">
            <p style="margin: 0; color: #F59E0B; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">BESTELLNUMMER</p>
            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 24px; font-weight: bold;">{{ order.orderNumber }}</p>
        </div>

        <!-- Billing Address -->
        <div style="margin-bottom: 25px; padding: 15px; border: 1px solid #e5e7eb; border-radius: 8px;">
            <h4 style="color: #F59E0B; margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">RECHNUNGSADRESSE</h4>
            {% if order.orderCustomer %}
            <p style="margin: 0; font-size: 14px; line-height: 1.5; color: #374151;">
                {{ order.orderCustomer.firstName|default('') }} {{ order.orderCustomer.lastName|default('') }}<br>
                {{ order.orderCustomer.email|default('') }}
            </p>
            {% endif %}
        </div>

        <!-- Payment & Shipping Info -->
        <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; margin-bottom: 25px;">
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 5px 0; color: #6b7280;">Zahlungsart:</td>
                    <td style="padding: 5px 0; font-weight: 600;">{% if order.transactions.first and order.transactions.first.paymentMethod %}{{ order.transactions.first.paymentMethod.translated.name|default('') }}{% endif %}</td>
                </tr>
                <tr>
                    <td style="padding: 5px 0; color: #6b7280;">Versandart:</td>
                    <td style="padding: 5px 0; font-weight: 600;">{% if order.deliveries.first and order.deliveries.first.shippingMethod %}{{ order.deliveries.first.shippingMethod.translated.name|default('Standard') }}{% else %}Standard{% endif %}</td>
                </tr>
            </table>
        </div>

        <!-- Order Items Table WITH IMAGES -->
        <div style="margin-bottom: 25px; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f9fafb;">
                        <th style="padding: 12px 15px; text-align: left; color: #F59E0B; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;" colspan="2">PRODUKT</th>
                        <th style="padding: 12px 15px; text-align: center; color: #F59E0B; font-size: 12px; text-transform: uppercase;">MENGE</th>
                        <th style="padding: 12px 15px; text-align: right; color: #F59E0B; font-size: 12px; text-transform: uppercase;">PREIS</th>
                    </tr>
                </thead>
                <tbody>
                    {% for lineItem in order.lineItems %}
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <!-- Product Image -->
                        <td style="padding: 15px; width: 70px;">
                            {% if lineItem.cover and lineItem.cover.url %}
                            <img src="{{ lineItem.cover.url }}" alt="{{ lineItem.label|default('') }}" style="width: 60px; height: 60px; object-fit: contain; border: 1px solid #e5e7eb; border-radius: 4px;">
                            {% else %}
                            <div style="width: 60px; height: 60px; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                <span style="color: #9ca3af; font-size: 10px;">Kein Bild</span>
                            </div>
                            {% endif %}
                        </td>
                        <!-- Product Name -->
                        <td style="padding: 15px;">
                            <strong style="font-size: 14px;">{{ lineItem.label|default('Produkt') }}</strong>
                            {% if lineItem.payload.productNumber is defined and lineItem.payload.productNumber %}
                            <br><small style="color: #6b7280;">Art.Nr: {{ lineItem.payload.productNumber }}</small>
                            {% endif %}
                            {% if lineItem.payload.selectedColor is defined and lineItem.payload.selectedColor %}
                            <br><span style="color: #F59E0B; font-size: 12px;">Variante: {{ lineItem.payload.selectedColor }}</span>
                            {% endif %}
                        </td>
                        <!-- Quantity -->
                        <td style="padding: 15px; text-align: center; color: #6b7280;">{{ lineItem.quantity|default(1) }}</td>
                        <!-- Price -->
                        <td style="padding: 15px; text-align: right; font-weight: 600;">CHF {{ lineItem.totalPrice|default(0)|number_format(2, '.', '') }}</td>
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
                    <td style="padding: 8px 0; text-align: right;">CHF {{ order.amountNet|default(0)|number_format(2, '.', '') }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #6b7280;">Versand</td>
                    <td style="padding: 8px 0; text-align: right;">CHF {{ order.shippingTotal|default(0)|number_format(2, '.', '') }}</td>
                </tr>
                <tr style="border-top: 2px solid #1a1a1a;">
                    <td style="padding: 15px 0; font-size: 16px;"><strong>Gesamtsumme</strong></td>
                    <td style="padding: 15px 0; text-align: right; font-size: 16px; font-weight: 700;">CHF {{ order.amountTotal|default(0)|number_format(2, '.', '') }}</td>
                </tr>
            </table>
        </div>

        <!-- Payment Info for Vorkasse -->
        {% set paymentName = '' %}
        {% if order.transactions.first and order.transactions.first.paymentMethod %}
            {% set paymentName = order.transactions.first.paymentMethod.translated.name|default('') %}
        {% endif %}
        {% if paymentName == 'Vorkasse' %}
        <div style="background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
            <h4 style="color: #92400E; margin: 0 0 15px 0;">Zahlungsinformationen</h4>
            <p style="margin: 0 0 15px 0; color: #92400E;">Bitte überweisen Sie den Betrag auf folgendes Konto:</p>
            <table style="width: 100%; font-size: 14px; background: #fffbeb; border-radius: 6px;">
                <tr><td style="padding: 8px; color: #666;">Kontoinhaber</td><td style="padding: 8px;"><strong>Raven Weapon AG</strong></td></tr>
                <tr><td style="padding: 8px; color: #666;">Bank</td><td style="padding: 8px;"><strong>PostFinance</strong></td></tr>
                <tr><td style="padding: 8px; color: #666;">IBAN</td><td style="padding: 8px;"><strong>CH6009000000165059892</strong></td></tr>
                <tr><td style="padding: 8px; color: #666;">BIC/SWIFT</td><td style="padding: 8px;"><strong>POFICHBEXXX</strong></td></tr>
                <tr><td style="padding: 8px; color: #666;">Verwendungszweck</td><td style="padding: 8px;"><strong style="color: #F59E0B;">{{ order.orderNumber }}</strong></td></tr>
            </table>
            <p style="margin: 15px 0 0 0; font-size: 13px; color: #92400E;">Nach Zahlungseingang wird Ihre Bestellung bearbeitet.</p>
        </div>
        {% endif %}

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="https://shop.ravenweapon.ch/account/order"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
                Bestellungen ansehen
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

// German Plain
$germanPlain = <<<'PLAIN'
Bestellbestätigung - Raven Weapon AG
====================================

Bestellnummer: {{ order.orderNumber }}
Gesamtsumme: CHF {{ order.amountTotal|default(0)|number_format(2, '.', '') }}

Guten Tag {{ order.orderCustomer.firstName|default('') }} {{ order.orderCustomer.lastName|default('') }},

vielen Dank für Ihre Bestellung bei Raven Weapon AG!

BESTELLTE ARTIKEL
-----------------
{% for lineItem in order.lineItems %}
- {{ lineItem.label|default('Produkt') }} x {{ lineItem.quantity|default(1) }} = CHF {{ lineItem.totalPrice|default(0)|number_format(2, '.', '') }}
{% endfor %}

ZUSAMMENFASSUNG
---------------
Zwischensumme: CHF {{ order.amountNet|default(0)|number_format(2, '.', '') }}
Versand: CHF {{ order.shippingTotal|default(0)|number_format(2, '.', '') }}
Gesamtsumme: CHF {{ order.amountTotal|default(0)|number_format(2, '.', '') }}

{% set paymentName = '' %}
{% if order.transactions.first and order.transactions.first.paymentMethod %}
{% set paymentName = order.transactions.first.paymentMethod.translated.name|default('') %}
{% endif %}
{% if paymentName == 'Vorkasse' %}
ZAHLUNGSINFORMATIONEN
---------------------
Kontoinhaber: Raven Weapon AG
Bank: PostFinance
IBAN: CH6009000000165059892
BIC/SWIFT: POFICHBEXXX
Verwendungszweck: {{ order.orderNumber }}
{% endif %}

Bestellungen ansehen: https://shop.ravenweapon.ch/account/order

Mit freundlichen Grüssen
Raven Weapon AG

www.ravenweapon.ch
PLAIN;

// English HTML
$englishHtml = str_replace(
    ['Guten Tag', 'vielen Dank für Ihre Bestellung bei', 'BESTELLNUMMER', 'RECHNUNGSADRESSE',
     'Zahlungsart:', 'Versandart:', 'PRODUKT', 'MENGE', 'PREIS', 'Kein Bild', 'Variante:',
     'ZUSAMMENFASSUNG', 'Zwischensumme', 'Gesamtsumme', 'Zahlungsinformationen',
     'Bitte überweisen Sie den Betrag auf folgendes Konto:', 'Kontoinhaber',
     'Nach Zahlungseingang wird Ihre Bestellung bearbeitet.', 'Bestellungen ansehen',
     'Mit freundlichen Grüssen', 'Schweiz'],
    ['Hello', 'Thank you for your order at', 'ORDER NUMBER', 'BILLING ADDRESS',
     'Payment:', 'Shipping:', 'PRODUCT', 'QTY', 'PRICE', 'No image', 'Variant:',
     'SUMMARY', 'Subtotal', 'Total', 'Payment Information',
     'Please transfer the amount to the following account:', 'Account Holder',
     'Your order will be processed after payment is received.', 'View Orders',
     'Best regards', 'Switzerland'],
    $germanHtml
);

// English Plain
$englishPlain = <<<'PLAIN'
Order Confirmation - Raven Weapon AG
====================================

Order Number: {{ order.orderNumber }}
Total: CHF {{ order.amountTotal|default(0)|number_format(2, '.', '') }}

Hello {{ order.orderCustomer.firstName|default('') }} {{ order.orderCustomer.lastName|default('') }},

Thank you for your order at Raven Weapon AG!

ORDERED ITEMS
-------------
{% for lineItem in order.lineItems %}
- {{ lineItem.label|default('Product') }} x {{ lineItem.quantity|default(1) }} = CHF {{ lineItem.totalPrice|default(0)|number_format(2, '.', '') }}
{% endfor %}

SUMMARY
-------
Subtotal: CHF {{ order.amountNet|default(0)|number_format(2, '.', '') }}
Shipping: CHF {{ order.shippingTotal|default(0)|number_format(2, '.', '') }}
Total: CHF {{ order.amountTotal|default(0)|number_format(2, '.', '') }}

View Orders: https://shop.ravenweapon.ch/account/order

Best regards,
Raven Weapon AG

www.ravenweapon.ch
PLAIN;

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

echo "\n✅ Order confirmation email template updated WITH PRODUCT IMAGES!\n";
echo "Features:\n";
echo "- Product images (60x60px) in order items table\n";
echo "- Product number (Art.Nr)\n";
echo "- Selected color variant\n";
echo "- Fallback placeholder if no image\n";
