<?php
/**
 * Update Order Confirmation Email Template
 * Design matching client's preferred layout with:
 * - Product table (POSITION, ANZAHL, PREIS, BETRAG)
 * - Order details
 * - Address cards
 * - Bank details for Vorkasse
 */

$host = '127.0.0.1';
$dbname = 'shopware';
$username = 'root';
$password = 'root';

$htmlTemplate = <<<'HTML'
<style>
@import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap");
</style>

<div style="font-family: 'Inter', Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333; max-width: 650px; margin: 0 auto; background-color: #ffffff;">

    <!-- Logo Header -->
    <div style="text-align: center; padding: 30px 20px; background-color: #ffffff; border-bottom: 3px solid #F2B90D;">
        <img src="https://ortak.ch/media/b5/e7/4f/1733787426/Raven%20Weapon%20AG-logo-dark.png" alt="Raven Weapon AG" style="max-width: 200px; height: auto;">
    </div>

    <!-- Main Content -->
    <div style="padding: 30px 25px;">

        <!-- Thank You Message -->
        <h1 style="font-size: 22px; font-weight: 600; color: #1a1a1a; margin: 0 0 10px 0;">Vielen Dank für Ihre Bestellung!</h1>
        <p style="color: #666; margin: 0 0 25px 0;">
            Hallo {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ ' ' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br>
            Ihre Bestellung wurde erfolgreich aufgenommen. Hier sind Ihre Bestelldetails:
        </p>

        <!-- Products Table -->
        <div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; margin-bottom: 20px;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px 15px; text-align: left; font-weight: 600; font-size: 12px; color: #6b7280; text-transform: uppercase; border-bottom: 1px solid #e5e7eb;">Position</th>
                        <th style="padding: 12px 15px; text-align: center; font-weight: 600; font-size: 12px; color: #6b7280; text-transform: uppercase; border-bottom: 1px solid #e5e7eb;">Anzahl</th>
                        <th style="padding: 12px 15px; text-align: right; font-weight: 600; font-size: 12px; color: #6b7280; text-transform: uppercase; border-bottom: 1px solid #e5e7eb;">Preis</th>
                        <th style="padding: 12px 15px; text-align: right; font-weight: 600; font-size: 12px; color: #6b7280; text-transform: uppercase; border-bottom: 1px solid #e5e7eb;">Betrag</th>
                    </tr>
                </thead>
                <tbody>
                    {% for lineItem in order.lineItems %}
                    <tr>
                        <td style="padding: 15px; border-bottom: 1px solid #f3f4f6;">
                            <div style="display: flex; align-items: center;">
                                {% if lineItem.cover %}
                                <img src="{{ lineItem.cover.url }}" alt="{{ lineItem.label }}" style="width: 50px; height: 50px; object-fit: contain; margin-right: 12px; border-radius: 4px; border: 1px solid #e5e7eb;">
                                {% endif %}
                                <div>
                                    <strong style="color: #1a1a1a; font-size: 14px;">{{ lineItem.payload.productNumber|default('') }}</strong><br>
                                    <span style="color: #666; font-size: 13px;">{{ lineItem.label }}</span>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 15px; text-align: center; border-bottom: 1px solid #f3f4f6; color: #1a1a1a;">{{ lineItem.quantity|number_format(2, '.', '') }} Stk.</td>
                        <td style="padding: 15px; text-align: right; border-bottom: 1px solid #f3f4f6; color: #1a1a1a;">CHF {{ lineItem.unitPrice|number_format(2, '.', "'") }}</td>
                        <td style="padding: 15px; text-align: right; border-bottom: 1px solid #f3f4f6; color: #1a1a1a; font-weight: 600;">CHF {{ lineItem.totalPrice|number_format(2, '.', "'") }}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>

            <!-- Order Totals -->
            <div style="padding: 15px 15px 5px 15px; border-top: 1px solid #e5e7eb;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 5px 0; color: #666;">Bestellsumme inkl. MwSt.:</td>
                        <td style="padding: 5px 0; text-align: right; color: #1a1a1a;">CHF {{ order.amountNet|number_format(2, '.', "'") }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0; color: #666;">Versandspesen inkl. MwSt.:</td>
                        <td style="padding: 5px 0; text-align: right; color: #1a1a1a;">CHF {{ order.shippingTotal|number_format(2, '.', "'") }}</td>
                    </tr>
                </table>
            </div>
            <div style="padding: 15px; background: #f8f9fa; border-top: 1px solid #e5e7eb;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="font-weight: 700; font-size: 16px; color: #1a1a1a;">Totalbestellbetrag inkl. MwSt.:</td>
                        <td style="font-weight: 700; font-size: 16px; text-align: right; color: #1a1a1a;">CHF {{ order.amountTotal|number_format(2, '.', "'") }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Order Details Card -->
        <div style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; color: #6b7280; width: 40%;">Bestellnummer:</td>
                    <td style="padding: 8px 0; color: #1a1a1a; font-weight: 600;">#{{ order.orderNumber }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #6b7280;">Bestelldatum:</td>
                    <td style="padding: 8px 0; color: #1a1a1a;">{{ order.orderDateTime|format_datetime('short', 'short', locale='de-CH') }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #6b7280;">Zahlungsart:</td>
                    <td style="padding: 8px 0; color: #1a1a1a;">{{ order.transactions|first.paymentMethod.translated.name }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #6b7280;">Versandoption:</td>
                    <td style="padding: 8px 0; color: #1a1a1a;">{{ order.deliveries|first.shippingMethod.translated.name }}</td>
                </tr>
            </table>
        </div>

        <!-- Address Cards -->
        <div style="display: flex; gap: 15px; margin-bottom: 25px;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <!-- Delivery Address -->
                    <td style="width: 48%; vertical-align: top; padding-right: 10px;">
                        <div style="background: #ffffff; border: 1px solid #e5e7eb; border-left: 4px solid #F2B90D; border-radius: 8px; padding: 15px;">
                            <h3 style="font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; margin: 0 0 12px 0; letter-spacing: 0.5px;">Lieferadresse</h3>
                            <p style="margin: 0; color: #1a1a1a; line-height: 1.6;">
                                {{ order.deliveries|first.shippingOrderAddress.firstName }} {{ order.deliveries|first.shippingOrderAddress.lastName }}<br>
                                {{ order.deliveries|first.shippingOrderAddress.street }}<br>
                                {{ order.deliveries|first.shippingOrderAddress.zipcode }} {{ order.deliveries|first.shippingOrderAddress.city }}<br>
                                {{ order.deliveries|first.shippingOrderAddress.country.translated.name }}
                            </p>
                        </div>
                    </td>
                    <!-- Billing Address -->
                    <td style="width: 48%; vertical-align: top; padding-left: 10px;">
                        <div style="background: #ffffff; border: 1px solid #e5e7eb; border-left: 4px solid #F2B90D; border-radius: 8px; padding: 15px;">
                            <h3 style="font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; margin: 0 0 12px 0; letter-spacing: 0.5px;">Rechnungsadresse</h3>
                            <p style="margin: 0; color: #1a1a1a; line-height: 1.6;">
                                {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }}<br>
                                {{ order.billingAddress.street }}<br>
                                {{ order.billingAddress.zipcode }} {{ order.billingAddress.city }}<br>
                                {{ order.billingAddress.country.translated.name }}
                            </p>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Bank Details (only for Vorkasse/Prepayment) -->
        {% set paymentName = order.transactions|first.paymentMethod.translated.name %}
        {% if paymentName == 'Vorkasse' or paymentName == 'Paid in advance' or paymentName == 'Prepayment' %}
        <div style="background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
            <h3 style="font-size: 16px; font-weight: 600; color: #1a1a1a; margin: 0 0 15px 0;">Zahlungsinformationen (Vorkasse)</h3>
            <div style="background: #ffffff; border-radius: 6px; padding: 15px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 6px 0; color: #6b7280; width: 140px;"><strong>Kontoinhaber:</strong></td>
                        <td style="padding: 6px 0; color: #1a1a1a;">Raven Weapon AG</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #6b7280;"><strong>Bank:</strong></td>
                        <td style="padding: 6px 0; color: #1a1a1a;">PostFinance</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #6b7280;"><strong>IBAN:</strong></td>
                        <td style="padding: 6px 0; color: #1a1a1a;">CH6009000000165059892</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #6b7280;"><strong>BIC/SWIFT:</strong></td>
                        <td style="padding: 6px 0; color: #1a1a1a;">POFICHBEXXX</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #6b7280;"><strong>Verwendungszweck:</strong></td>
                        <td style="padding: 6px 0; color: #1a1a1a;">{{ order.orderNumber }}</td>
                    </tr>
                </table>
            </div>
            <p style="margin: 15px 0 0 0; color: #666; font-size: 13px;">
                Nach Zahlungseingang wird Ihre Bestellung umgehend bearbeitet.
            </p>
        </div>
        {% endif %}

        <!-- Closing Message -->
        <div style="border-top: 1px solid #e5e7eb; padding-top: 20px; margin-top: 10px;">
            <p style="color: #666; margin: 0 0 5px 0;">Bei Fragen stehen wir Ihnen gerne zur Verfügung.</p>
            <p style="color: #1a1a1a; margin: 0;">
                Mit freundlichen Grüssen<br>
                <strong>Ihr Raven Weapon AG Team</strong>
            </p>
        </div>

    </div>

    <!-- Footer -->
    <div style="background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb;">
        <p style="color: #9ca3af; font-size: 12px; margin: 0;">
            &copy; {{ "now"|date("Y") }} Raven Weapon AG | <a href="https://ortak.ch" style="color: #F2B90D; text-decoration: none;">ortak.ch</a>
        </p>
    </div>

</div>
HTML;

$plainTemplate = <<<'PLAIN'
{% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ ' ' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},

vielen Dank für Ihre Bestellung bei {{ salesChannel.translated.name }}!

Ihre Bestellnummer: #{{ order.orderNumber }}
Bestelldatum: {{ order.orderDateTime|format_datetime('medium', 'short', locale='de-CH') }}

Bestellübersicht:
{% for lineItem in order.lineItems %}
- {{ lineItem.quantity }}x {{ lineItem.label }} - CHF {{ lineItem.totalPrice|number_format(2, '.', "'") }}
{% endfor %}

Zwischensumme: CHF {{ order.amountNet|number_format(2, '.', "'") }}
Versand: CHF {{ order.shippingTotal|number_format(2, '.', "'") }}
{% for calculatedTax in order.price.calculatedTaxes %}
MwSt. {{ calculatedTax.taxRate }}%: CHF {{ calculatedTax.tax|number_format(2, '.', "'") }}
{% endfor %}
Gesamtbetrag: CHF {{ order.amountTotal|number_format(2, '.', "'") }}

Zahlungsart: {{ order.transactions|first.paymentMethod.translated.name }}
Versandart: {{ order.deliveries|first.shippingMethod.translated.name }}

LIEFERADRESSE:
{{ order.deliveries|first.shippingOrderAddress.firstName }} {{ order.deliveries|first.shippingOrderAddress.lastName }}
{{ order.deliveries|first.shippingOrderAddress.street }}
{{ order.deliveries|first.shippingOrderAddress.zipcode }} {{ order.deliveries|first.shippingOrderAddress.city }}
{{ order.deliveries|first.shippingOrderAddress.country.translated.name }}

RECHNUNGSADRESSE:
{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }}
{{ order.billingAddress.street }}
{{ order.billingAddress.zipcode }} {{ order.billingAddress.city }}
{{ order.billingAddress.country.translated.name }}

{% set paymentName = order.transactions|first.paymentMethod.translated.name %}
{% if paymentName == 'Vorkasse' or paymentName == 'Paid in advance' or paymentName == 'Prepayment' %}
ZAHLUNGSINFORMATIONEN (Vorkasse):
Kontoinhaber: Raven Weapon AG
Bank: PostFinance
IBAN: CH6009000000165059892
BIC/SWIFT: POFICHBEXXX
Verwendungszweck: {{ order.orderNumber }}

Nach Zahlungseingang wird Ihre Bestellung umgehend bearbeitet.
{% endif %}

Bei Fragen stehen wir Ihnen gerne zur Verfügung.

Mit freundlichen Grüssen
Ihr Raven Weapon AG Team
PLAIN;

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "=== Updating Order Confirmation Email Template ===\n\n";

    // Find the mail template ID for order confirmation
    $stmt = $pdo->query("
        SELECT HEX(mt.id) as template_id, mtt.language_id
        FROM mail_template mt
        JOIN mail_template_type mtt_type ON mt.mail_template_type_id = mtt_type.id
        JOIN mail_template_translation mtt ON mt.id = mtt.mail_template_id
        WHERE mtt_type.technical_name = 'order_confirmation_mail'
    ");

    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($templates)) {
        die("No order confirmation template found!\n");
    }

    foreach ($templates as $template) {
        $templateId = $template['template_id'];
        echo "Found template ID: $templateId\n";

        // Update the template
        $updateStmt = $pdo->prepare("
            UPDATE mail_template_translation
            SET content_html = ?,
                content_plain = ?,
                updated_at = NOW()
            WHERE HEX(mail_template_id) = ?
        ");

        $updateStmt->execute([$htmlTemplate, $plainTemplate, $templateId]);

        echo "Updated template translations\n";
    }

    echo "\n✅ Order confirmation email template updated successfully!\n";
    echo "The new design includes:\n";
    echo "- Product table with POSITION, ANZAHL, PREIS, BETRAG columns\n";
    echo "- Order details (Bestellnummer, Datum, Zahlungsart, Versand)\n";
    echo "- Address cards (Lieferadresse, Rechnungsadresse)\n";
    echo "- Bank details for Vorkasse payments\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
