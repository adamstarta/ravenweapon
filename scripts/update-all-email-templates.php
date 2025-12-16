<?php
/**
 * Update ALL Email Templates
 * 1. Change all sender names to "Raven Weapon AG"
 * 2. Update shipped template with logo, products, prices
 * 3. Update paid template with same style
 */
$pdo = new PDO('mysql:host=127.0.0.1;dbname=shopware', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== UPDATING ALL EMAIL TEMPLATES ===\n\n";

// 1. Update ALL sender names to "Raven Weapon AG"
echo "1. Updating all sender names to 'Raven Weapon AG'...\n";
$stmt = $pdo->exec("UPDATE mail_template_translation SET sender_name = 'Raven Weapon AG' WHERE sender_name != 'Raven Weapon AG'");
echo "   Updated sender names\n\n";

// 2. SHIPPED template (order_delivery.state.shipped)
echo "2. Updating SHIPPED template...\n";

$shippedHtml = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
    <!-- Header with Logo -->
    <div style="text-align: center; padding: 30px 20px; border-bottom: 3px solid #F59E0B;">
        <img src="https://ortak.ch/raven-logo.png" alt="Raven Weapon" style="max-width: 200px; height: auto;">
    </div>

    <!-- Content -->
    <div style="padding: 30px 20px;">
        <h2 style="color: #1a1a1a; margin: 0 0 20px 0;">Ihre Bestellung wurde versandt!</h2>

        <p style="font-size: 16px; margin-bottom: 20px;">
            Guten Tag {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            gute Neuigkeiten! Ihre Bestellung <strong>#{{ order.orderNumber }}</strong> wurde versandt.
        </p>

        <!-- Status Box -->
        <div style="background: #D1FAE5; border-left: 4px solid #10B981; padding: 15px 20px; margin-bottom: 25px;">
            <p style="margin: 0; color: #065F46;"><strong>Status:</strong> Versandt</p>
        </div>

        <!-- Products Section -->
        <h3 style="color: #1a1a1a; border-bottom: 2px solid #F59E0B; padding-bottom: 10px; margin-bottom: 20px;">Bestellübersicht</h3>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            {% for lineItem in order.lineItems %}
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 15px 10px 15px 0; width: 80px; vertical-align: top;">
                    {% if lineItem.cover %}
                    <img src="{{ lineItem.cover.url }}" alt="{{ lineItem.label }}" style="width: 80px; height: 80px; object-fit: contain; border: 1px solid #eee; border-radius: 4px;">
                    {% else %}
                    <div style="width: 80px; height: 80px; background: #f5f5f5; border-radius: 4px;"></div>
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

        <!-- Shipping Address -->
        {% set shippingAddress = order.deliveries.first.shippingOrderAddress %}
        {% if shippingAddress %}
        <div style="margin-bottom: 25px;">
            <h4 style="color: #F59E0B; margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Lieferadresse</h4>
            <p style="margin: 0; line-height: 1.6; color: #333;">
                {{ shippingAddress.firstName }} {{ shippingAddress.lastName }}<br>
                {{ shippingAddress.street }}<br>
                {{ shippingAddress.zipcode }} {{ shippingAddress.city }}<br>
                {% if shippingAddress.country %}{{ shippingAddress.country.translated.name }}{% endif %}
            </p>
        </div>
        {% endif %}

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
                Bestellung verfolgen
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
            Bei Fragen kontaktieren Sie uns gerne unter <a href="mailto:info@ravenweapon.ch" style="color: #F59E0B; text-decoration: none;">info@ravenweapon.ch</a>
        </p>
    </div>
</div>';

$shippedPlain = 'Ihre Bestellung wurde versandt!

Guten Tag {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},

gute Neuigkeiten! Ihre Bestellung #{{ order.orderNumber }} wurde versandt.

Status: Versandt

BESTELLÜBERSICHT
================
{% for lineItem in order.lineItems %}
{{ lineItem.quantity }}x {{ lineItem.label }}{% if lineItem.payload.productNumber %} (Art.Nr: {{ lineItem.payload.productNumber }}){% endif %} - {{ lineItem.totalPrice|currency(order.currency.isoCode) }}
{% endfor %}

Zwischensumme: {{ order.amountNet|currency(order.currency.isoCode) }}
Versand: {{ order.shippingTotal|currency(order.currency.isoCode) }}
GESAMTSUMME: {{ order.amountTotal|currency(order.currency.isoCode) }}

{% set shippingAddress = order.deliveries.first.shippingOrderAddress %}
{% if shippingAddress %}
LIEFERADRESSE
=============
{{ shippingAddress.firstName }} {{ shippingAddress.lastName }}
{{ shippingAddress.street }}
{{ shippingAddress.zipcode }} {{ shippingAddress.city }}
{% if shippingAddress.country %}{{ shippingAddress.country.translated.name }}{% endif %}
{% endif %}

Bestellung verfolgen: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}

Mit freundlichen Grüssen
Raven Weapon AG

--
Bei Fragen: info@ravenweapon.ch';

// Get shipped template ID
$stmt = $pdo->query("SELECT HEX(mt.id) as tid FROM mail_template mt
    JOIN mail_template_type mtt ON mt.mail_template_type_id = mtt.id
    WHERE mtt.technical_name = 'order_delivery.state.shipped'");
$row = $stmt->fetch();
if ($row) {
    $tid = $row['tid'];
    $stmt = $pdo->prepare("UPDATE mail_template_translation
            SET content_plain = ?,
                content_html = ?,
                subject = 'Ihre Bestellung wurde versandt - #{{ order.orderNumber }}',
                updated_at = NOW()
            WHERE mail_template_id = UNHEX(?)");
    $stmt->execute([$shippedPlain, $shippedHtml, $tid]);
    echo "   Shipped template updated\n\n";
}

// 3. PAID template (order_transaction.state.paid)
echo "3. Updating PAID template...\n";

$paidHtml = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
    <!-- Header with Logo -->
    <div style="text-align: center; padding: 30px 20px; border-bottom: 3px solid #F59E0B;">
        <img src="https://ortak.ch/raven-logo.png" alt="Raven Weapon" style="max-width: 200px; height: auto;">
    </div>

    <!-- Content -->
    <div style="padding: 30px 20px;">
        <h2 style="color: #1a1a1a; margin: 0 0 20px 0;">Zahlung erhalten</h2>

        <p style="font-size: 16px; margin-bottom: 20px;">
            Guten Tag {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            wir haben Ihre Zahlung für die Bestellung <strong>#{{ order.orderNumber }}</strong> erhalten. Vielen Dank!
        </p>

        <!-- Status Box -->
        <div style="background: #D1FAE5; border-left: 4px solid #10B981; padding: 15px 20px; margin-bottom: 25px;">
            <p style="margin: 0; color: #065F46;"><strong>Zahlungsstatus:</strong> Bezahlt</p>
        </div>

        <!-- Products Section -->
        <h3 style="color: #1a1a1a; border-bottom: 2px solid #F59E0B; padding-bottom: 10px; margin-bottom: 20px;">Bestellübersicht</h3>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            {% for lineItem in order.lineItems %}
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 15px 10px 15px 0; width: 80px; vertical-align: top;">
                    {% if lineItem.cover %}
                    <img src="{{ lineItem.cover.url }}" alt="{{ lineItem.label }}" style="width: 80px; height: 80px; object-fit: contain; border: 1px solid #eee; border-radius: 4px;">
                    {% else %}
                    <div style="width: 80px; height: 80px; background: #f5f5f5; border-radius: 4px;"></div>
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

        <p style="font-size: 14px; color: #666; margin-bottom: 25px;">
            Ihre Bestellung wird nun für den Versand vorbereitet. Sie erhalten eine weitere E-Mail, sobald Ihre Bestellung versandt wurde.
        </p>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}"
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
            Bei Fragen kontaktieren Sie uns gerne unter <a href="mailto:info@ravenweapon.ch" style="color: #F59E0B; text-decoration: none;">info@ravenweapon.ch</a>
        </p>
    </div>
</div>';

$paidPlain = 'Zahlung erhalten

Guten Tag {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},

wir haben Ihre Zahlung für die Bestellung #{{ order.orderNumber }} erhalten. Vielen Dank!

Zahlungsstatus: Bezahlt

BESTELLÜBERSICHT
================
{% for lineItem in order.lineItems %}
{{ lineItem.quantity }}x {{ lineItem.label }}{% if lineItem.payload.productNumber %} (Art.Nr: {{ lineItem.payload.productNumber }}){% endif %} - {{ lineItem.totalPrice|currency(order.currency.isoCode) }}
{% endfor %}

Zwischensumme: {{ order.amountNet|currency(order.currency.isoCode) }}
Versand: {{ order.shippingTotal|currency(order.currency.isoCode) }}
GESAMTSUMME: {{ order.amountTotal|currency(order.currency.isoCode) }}

Ihre Bestellung wird nun für den Versand vorbereitet. Sie erhalten eine weitere E-Mail, sobald Ihre Bestellung versandt wurde.

Bestellung ansehen: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}

Mit freundlichen Grüssen
Raven Weapon AG

--
Bei Fragen: info@ravenweapon.ch';

// Get paid template ID
$stmt = $pdo->query("SELECT HEX(mt.id) as tid FROM mail_template mt
    JOIN mail_template_type mtt ON mt.mail_template_type_id = mtt.id
    WHERE mtt.technical_name = 'order_transaction.state.paid'");
$row = $stmt->fetch();
if ($row) {
    $tid = $row['tid'];
    $stmt = $pdo->prepare("UPDATE mail_template_translation
            SET content_plain = ?,
                content_html = ?,
                subject = 'Zahlung erhalten - Bestellung #{{ order.orderNumber }}',
                updated_at = NOW()
            WHERE mail_template_id = UNHEX(?)");
    $stmt->execute([$paidPlain, $paidHtml, $tid]);
    echo "   Paid template updated\n\n";
}

// Verify
echo "=== VERIFICATION ===\n";
$stmt = $pdo->query("SELECT COUNT(DISTINCT sender_name) as cnt, GROUP_CONCAT(DISTINCT sender_name) as names FROM mail_template_translation");
$check = $stmt->fetch();
echo "Unique sender names: " . $check['cnt'] . " (" . $check['names'] . ")\n";

$stmt = $pdo->query("SELECT content_html LIKE '%raven-logo.png%' as has_logo FROM mail_template_translation mtt JOIN mail_template mt ON mtt.mail_template_id = mt.id JOIN mail_template_type mttype ON mt.mail_template_type_id = mttype.id WHERE mttype.technical_name = 'order_delivery.state.shipped' LIMIT 1");
$check = $stmt->fetch();
echo "Shipped has logo: " . ($check['has_logo'] ? "YES" : "NO") . "\n";

$stmt = $pdo->query("SELECT content_html LIKE '%raven-logo.png%' as has_logo FROM mail_template_translation mtt JOIN mail_template mt ON mtt.mail_template_id = mt.id JOIN mail_template_type mttype ON mt.mail_template_type_id = mttype.id WHERE mttype.technical_name = 'order_transaction.state.paid' LIMIT 1");
$check = $stmt->fetch();
echo "Paid has logo: " . ($check['has_logo'] ? "YES" : "NO") . "\n";

echo "\n=== ALL DONE! ===\n";
