<?php
/**
 * Update ALL email templates to match Raven Weapon AG branding
 * - Professional styled layout matching Welcome email
 * - All URLs updated to shop.ravenweapon.ch
 */

$host = 'localhost';
$dbname = 'shopware';
$user = 'root';
$pass = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

echo "=== Updating ALL Email Templates for Raven Weapon AG ===\n\n";

// Base URL
$shopUrl = 'https://shop.ravenweapon.ch';
$logoUrl = $shopUrl . '/bundles/raventheme/assets/raven-logo.png';

// Helper function to wrap content in Raven template
function wrapInRavenTemplate($content, $logoUrl, $shopUrl, $lang = 'de') {
    $footer = $lang === 'de' ? 'Mit freundlichen Grüssen' : 'Best regards';
    $country = $lang === 'de' ? 'Schweiz' : 'Switzerland';

    return '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
    <!-- Header with Logo -->
    <div style="text-align: center; padding: 30px 20px; border-bottom: 3px solid #F59E0B;">
        <img src="' . $logoUrl . '" alt="Raven Weapon" style="max-width: 200px; height: auto;">
    </div>

    <!-- Content -->
    <div style="padding: 30px 20px;">
        ' . $content . '

        <!-- Footer -->
        <div style="border-top: 1px solid #eee; padding-top: 20px; margin-top: 30px;">
            <p style="margin: 0; color: #666;">
                ' . $footer . '<br>
                <strong style="color: #1a1a1a;">Raven Weapon AG</strong>
            </p>
        </div>
    </div>

    <!-- Email Footer -->
    <div style="background: #1a1a1a; color: #ffffff; padding: 20px; text-align: center; font-size: 13px;">
        <p style="margin: 0 0 10px 0;">Raven Weapon AG | ' . $country . '</p>
        <p style="margin: 0; color: #888;">
            <a href="' . $shopUrl . '" style="color: #F59E0B; text-decoration: none;">www.ravenweapon.ch</a>
        </p>
    </div>
</div>';
}

// Get language IDs
$sql = "SELECT LOWER(HEX(id)) as id, name FROM language";
$stmt = $pdo->query($sql);
$languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$langMap = [];
foreach ($languages as $lang) {
    if (stripos($lang['name'], 'deutsch') !== false || stripos($lang['name'], 'german') !== false) {
        $langMap['de'] = $lang['id'];
    } else {
        $langMap['en'] = $lang['id'];
    }
}

echo "Language IDs: DE=" . $langMap['de'] . ", EN=" . $langMap['en'] . "\n\n";

// ==================== STEP 1: Update sender names ====================
echo "Step 1: Updating all sender names to 'Raven Weapon AG'...\n";
$sql = "UPDATE mail_template_translation SET sender_name = 'Raven Weapon AG'";
$stmt = $pdo->query($sql);
echo "  ✓ Updated " . $stmt->rowCount() . " sender names\n\n";

// ==================== STEP 2: Update ALL URLs from ortak.ch to shop.ravenweapon.ch ====================
echo "Step 2: Updating all URLs (ortak.ch -> shop.ravenweapon.ch)...\n";
$sql = "UPDATE mail_template_translation
        SET content_html = REPLACE(REPLACE(REPLACE(content_html,
            'https://ortak.ch', 'https://shop.ravenweapon.ch'),
            'http://ortak.ch', 'https://shop.ravenweapon.ch'),
            'www.ortak.ch', 'www.ravenweapon.ch'),
            content_plain = REPLACE(REPLACE(REPLACE(content_plain,
            'https://ortak.ch', 'https://shop.ravenweapon.ch'),
            'http://ortak.ch', 'https://shop.ravenweapon.ch'),
            'www.ortak.ch', 'www.ravenweapon.ch'),
            updated_at = NOW()";
$stmt = $pdo->query($sql);
echo "  ✓ Updated URLs in templates\n\n";

// ==================== STEP 3: Define all styled templates ====================
$templates = [
    // ORDER CONFIRMATION
    'order_confirmation_mail' => [
        'de' => [
            'subject' => 'Bestellbestätigung - Bestellung {{ order.orderNumber }} | Raven Weapon AG',
            'html' => '<!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Guten Tag {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            vielen Dank für Ihre Bestellung bei <strong>Raven Weapon AG</strong>!
        </p>

        <!-- Order Info Box -->
        <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
            <h3 style="color: #F59E0B; margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Bestellinformationen</h3>
            <table style="width: 100%; color: #ffffff;">
                <tr><td style="padding: 5px 0;"><strong>Bestellnummer:</strong></td><td style="padding: 5px 0;">{{ order.orderNumber }}</td></tr>
                <tr><td style="padding: 5px 0;"><strong>Bestelldatum:</strong></td><td style="padding: 5px 0;">{{ order.orderDateTime|format_datetime(\'medium\', \'short\', locale=\'de-CH\') }}</td></tr>
                <tr><td style="padding: 5px 0;"><strong>Zahlungsart:</strong></td><td style="padding: 5px 0;">{{ order.transactions.first.paymentMethod.translated.name }}</td></tr>
            </table>
        </div>

        <!-- Order Items -->
        <div style="margin-bottom: 25px;">
            <h3 style="color: #1a1a1a; margin: 0 0 15px 0; font-size: 16px;">Ihre bestellten Artikel</h3>
            <table style="width: 100%; border-collapse: collapse;">
                {% for lineItem in order.nestedLineItems %}
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 12px 0;">
                        <strong>{{ lineItem.label }}</strong>
                        {% if lineItem.payload.productNumber is defined %}<br><small style="color: #666;">Art.-Nr.: {{ lineItem.payload.productNumber }}</small>{% endif %}
                    </td>
                    <td style="padding: 12px; text-align: center;">{{ lineItem.quantity }}x</td>
                    <td style="padding: 12px 0; text-align: right;">{{ lineItem.totalPrice|currency }}</td>
                </tr>
                {% endfor %}
            </table>
            <table style="width: 100%; margin-top: 15px;">
                <tr><td style="padding: 5px 0;"><strong>Zwischensumme:</strong></td><td style="text-align: right;">{{ order.amountNet|currency }}</td></tr>
                <tr><td style="padding: 5px 0;">Versandkosten:</td><td style="text-align: right;">{{ order.shippingTotal|currency }}</td></tr>
                <tr style="border-top: 2px solid #1a1a1a;"><td style="padding: 12px 0; font-size: 18px;"><strong>Gesamtsumme:</strong></td><td style="text-align: right; font-size: 18px; color: #F59E0B;"><strong>{{ order.amountTotal|currency }}</strong></td></tr>
            </table>
        </div>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
                Bestellung ansehen
            </a>
        </div>',
            'plain' => 'Bestellbestätigung - Raven Weapon AG

Bestellnummer: {{ order.orderNumber }}
Gesamtsumme: {{ order.amountTotal|currency }}

Vielen Dank für Ihre Bestellung!

Mit freundlichen Grüssen
Raven Weapon AG'
        ],
        'en' => [
            'subject' => 'Order Confirmation - Order {{ order.orderNumber }} | Raven Weapon AG',
            'html' => '<!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Hello {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            Thank you for your order at <strong>Raven Weapon AG</strong>!
        </p>

        <!-- Order Info Box -->
        <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
            <h3 style="color: #F59E0B; margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase;">Order Information</h3>
            <table style="width: 100%; color: #ffffff;">
                <tr><td style="padding: 5px 0;"><strong>Order Number:</strong></td><td>{{ order.orderNumber }}</td></tr>
                <tr><td style="padding: 5px 0;"><strong>Order Date:</strong></td><td>{{ order.orderDateTime|format_datetime(\'medium\', \'short\') }}</td></tr>
            </table>
        </div>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                View Order
            </a>
        </div>',
            'plain' => 'Order Confirmation - Raven Weapon AG

Order Number: {{ order.orderNumber }}
Total: {{ order.amountTotal|currency }}

Thank you for your order!

Best regards,
Raven Weapon AG'
        ]
    ],

    // PASSWORD RECOVERY
    'customer.recovery.request' => [
        'de' => [
            'subject' => 'Passwort zurücksetzen - Raven Weapon AG',
            'html' => '<!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Guten Tag {{ customer.firstName }} {{ customer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            Sie haben eine Anfrage zum Zurücksetzen Ihres Passworts bei <strong>Raven Weapon AG</strong> gestellt.
        </p>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 35px 0;">
            <a href="{{ resetUrl }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 16px 40px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
                Passwort zurücksetzen
            </a>
        </div>

        <!-- Info Box -->
        <div style="background: #f8f9fa; border-left: 4px solid #F59E0B; padding: 20px; margin-bottom: 25px; border-radius: 0 8px 8px 0;">
            <p style="margin: 0; font-size: 14px; color: #374151;">
                Falls der Button nicht funktioniert:<br>
                <a href="{{ resetUrl }}" style="color: #F59E0B; word-break: break-all;">{{ resetUrl }}</a>
            </p>
        </div>

        <!-- Security Note -->
        <div style="background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 8px; padding: 15px; margin-bottom: 25px;">
            <p style="margin: 0; font-size: 13px; color: #92400E;">
                <strong>Hinweis:</strong> Falls Sie diese Anfrage nicht gestellt haben, ignorieren Sie diese E-Mail.
            </p>
        </div>',
            'plain' => 'Passwort zurücksetzen - Raven Weapon AG

Guten Tag {{ customer.firstName }} {{ customer.lastName }},

Sie haben eine Anfrage zum Zurücksetzen Ihres Passworts gestellt.

Link: {{ resetUrl }}

Falls Sie diese Anfrage nicht gestellt haben, ignorieren Sie diese E-Mail.

Mit freundlichen Grüssen
Raven Weapon AG'
        ],
        'en' => [
            'subject' => 'Reset your password - Raven Weapon AG',
            'html' => '<!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Hello {{ customer.firstName }} {{ customer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            You have requested to reset your password at <strong>Raven Weapon AG</strong>.
        </p>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 35px 0;">
            <a href="{{ resetUrl }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 16px 40px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
                Reset Password
            </a>
        </div>

        <!-- Security Note -->
        <div style="background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 8px; padding: 15px; margin-bottom: 25px;">
            <p style="margin: 0; font-size: 13px; color: #92400E;">
                <strong>Note:</strong> If you did not request this, please ignore this email.
            </p>
        </div>',
            'plain' => 'Reset your password - Raven Weapon AG

Hello {{ customer.firstName }} {{ customer.lastName }},

You have requested to reset your password.

Link: {{ resetUrl }}

If you did not request this, please ignore this email.

Best regards,
Raven Weapon AG'
        ]
    ],

    // ORDER SHIPPED
    'order_delivery.state.shipped' => [
        'de' => [
            'subject' => 'Ihre Bestellung wurde versandt - #{{ order.orderNumber }} | Raven Weapon AG',
            'html' => '<!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Guten Tag {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            <strong style="color: #059669;">Gute Nachrichten!</strong> Ihre Bestellung wurde versandt.
        </p>

        <!-- Order Info Box -->
        <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
            <h3 style="color: #F59E0B; margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase;">Versandinformationen</h3>
            <table style="width: 100%; color: #ffffff;">
                <tr><td style="padding: 5px 0;"><strong>Bestellnummer:</strong></td><td>{{ order.orderNumber }}</td></tr>
                <tr><td style="padding: 5px 0;"><strong>Versandart:</strong></td><td>{{ order.deliveries.first.shippingMethod.translated.name }}</td></tr>
                {% if order.deliveries.first.trackingCodes|length > 0 %}
                <tr><td style="padding: 5px 0;"><strong>Tracking:</strong></td><td>{{ order.deliveries.first.trackingCodes|first }}</td></tr>
                {% endif %}
            </table>
        </div>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Bestellung verfolgen
            </a>
        </div>',
            'plain' => 'Ihre Bestellung wurde versandt - Raven Weapon AG

Bestellnummer: {{ order.orderNumber }}

Ihre Bestellung ist unterwegs!

Mit freundlichen Grüssen
Raven Weapon AG'
        ],
        'en' => [
            'subject' => 'Your order has been shipped - #{{ order.orderNumber }} | Raven Weapon AG',
            'html' => '<!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Hello {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            <strong style="color: #059669;">Great news!</strong> Your order has been shipped.
        </p>

        <!-- Order Info Box -->
        <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
            <h3 style="color: #F59E0B; margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase;">Shipping Information</h3>
            <table style="width: 100%; color: #ffffff;">
                <tr><td style="padding: 5px 0;"><strong>Order Number:</strong></td><td>{{ order.orderNumber }}</td></tr>
                {% if order.deliveries.first.trackingCodes|length > 0 %}
                <tr><td style="padding: 5px 0;"><strong>Tracking:</strong></td><td>{{ order.deliveries.first.trackingCodes|first }}</td></tr>
                {% endif %}
            </table>
        </div>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Track Order
            </a>
        </div>',
            'plain' => 'Your order has been shipped - Raven Weapon AG

Order Number: {{ order.orderNumber }}

Your order is on its way!

Best regards,
Raven Weapon AG'
        ]
    ],

    // PAYMENT RECEIVED
    'order_transaction.state.paid' => [
        'de' => [
            'subject' => 'Zahlung erhalten - Bestellung #{{ order.orderNumber }} | Raven Weapon AG',
            'html' => '<!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Guten Tag {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            <strong style="color: #059669;">Vielen Dank!</strong> Wir haben Ihre Zahlung erhalten.
        </p>

        <!-- Order Info Box -->
        <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
            <h3 style="color: #F59E0B; margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase;">Zahlungsdetails</h3>
            <table style="width: 100%; color: #ffffff;">
                <tr><td style="padding: 5px 0;"><strong>Bestellnummer:</strong></td><td>{{ order.orderNumber }}</td></tr>
                <tr><td style="padding: 5px 0;"><strong>Betrag:</strong></td><td style="color: #F59E0B;"><strong>{{ order.amountTotal|currency }}</strong></td></tr>
            </table>
        </div>

        <p style="font-size: 16px; margin-bottom: 25px;">
            Ihre Bestellung wird nun für den Versand vorbereitet.
        </p>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Bestellung ansehen
            </a>
        </div>',
            'plain' => 'Zahlung erhalten - Raven Weapon AG

Bestellnummer: {{ order.orderNumber }}
Betrag: {{ order.amountTotal|currency }}

Vielen Dank! Ihre Bestellung wird nun vorbereitet.

Mit freundlichen Grüssen
Raven Weapon AG'
        ],
        'en' => [
            'subject' => 'Payment received - Order #{{ order.orderNumber }} | Raven Weapon AG',
            'html' => '<!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Hello {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            <strong style="color: #059669;">Thank you!</strong> We have received your payment.
        </p>

        <!-- Order Info Box -->
        <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
            <h3 style="color: #F59E0B; margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase;">Payment Details</h3>
            <table style="width: 100%; color: #ffffff;">
                <tr><td style="padding: 5px 0;"><strong>Order Number:</strong></td><td>{{ order.orderNumber }}</td></tr>
                <tr><td style="padding: 5px 0;"><strong>Amount:</strong></td><td style="color: #F59E0B;"><strong>{{ order.amountTotal|currency }}</strong></td></tr>
            </table>
        </div>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                View Order
            </a>
        </div>',
            'plain' => 'Payment received - Raven Weapon AG

Order Number: {{ order.orderNumber }}
Amount: {{ order.amountTotal|currency }}

Thank you! Your order is being prepared.

Best regards,
Raven Weapon AG'
        ]
    ],

    // CONTACT FORM
    'contact_form' => [
        'de' => [
            'subject' => 'Neue Kontaktanfrage von {{ contactFormData.firstName }} {{ contactFormData.lastName }}',
            'html' => '<!-- Heading -->
        <h2 style="color: #1a1a1a; margin: 0 0 20px 0;">Neue Kontaktanfrage</h2>

        <!-- Contact Info Box -->
        <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
            <h3 style="color: #F59E0B; margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase;">Kontaktdaten</h3>
            <table style="width: 100%; color: #ffffff;">
                <tr><td style="padding: 5px 0;"><strong>Name:</strong></td><td>{{ contactFormData.firstName }} {{ contactFormData.lastName }}</td></tr>
                <tr><td style="padding: 5px 0;"><strong>E-Mail:</strong></td><td><a href="mailto:{{ contactFormData.email }}" style="color: #F59E0B;">{{ contactFormData.email }}</a></td></tr>
                {% if contactFormData.phone %}<tr><td style="padding: 5px 0;"><strong>Telefon:</strong></td><td>{{ contactFormData.phone }}</td></tr>{% endif %}
                <tr><td style="padding: 5px 0;"><strong>Betreff:</strong></td><td>{{ contactFormData.subject }}</td></tr>
            </table>
        </div>

        <!-- Message Box -->
        <div style="background: #f8f9fa; border-left: 4px solid #F59E0B; padding: 20px; margin-bottom: 25px; border-radius: 0 8px 8px 0;">
            <h4 style="margin: 0 0 10px 0; color: #1a1a1a;">Nachricht</h4>
            <p style="margin: 0; line-height: 1.6; color: #374151; white-space: pre-wrap;">{{ contactFormData.comment }}</p>
        </div>',
            'plain' => 'Neue Kontaktanfrage

Name: {{ contactFormData.firstName }} {{ contactFormData.lastName }}
E-Mail: {{ contactFormData.email }}
Betreff: {{ contactFormData.subject }}

Nachricht:
{{ contactFormData.comment }}

---
Raven Weapon AG'
        ],
        'en' => [
            'subject' => 'New contact request from {{ contactFormData.firstName }} {{ contactFormData.lastName }}',
            'html' => '<!-- Heading -->
        <h2 style="color: #1a1a1a; margin: 0 0 20px 0;">New Contact Request</h2>

        <!-- Contact Info Box -->
        <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
            <h3 style="color: #F59E0B; margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase;">Contact Details</h3>
            <table style="width: 100%; color: #ffffff;">
                <tr><td style="padding: 5px 0;"><strong>Name:</strong></td><td>{{ contactFormData.firstName }} {{ contactFormData.lastName }}</td></tr>
                <tr><td style="padding: 5px 0;"><strong>Email:</strong></td><td><a href="mailto:{{ contactFormData.email }}" style="color: #F59E0B;">{{ contactFormData.email }}</a></td></tr>
            </table>
        </div>

        <!-- Message Box -->
        <div style="background: #f8f9fa; border-left: 4px solid #F59E0B; padding: 20px; margin-bottom: 25px; border-radius: 0 8px 8px 0;">
            <h4 style="margin: 0 0 10px 0; color: #1a1a1a;">Message</h4>
            <p style="margin: 0; line-height: 1.6; color: #374151; white-space: pre-wrap;">{{ contactFormData.comment }}</p>
        </div>',
            'plain' => 'New Contact Request

Name: {{ contactFormData.firstName }} {{ contactFormData.lastName }}
Email: {{ contactFormData.email }}

Message:
{{ contactFormData.comment }}

---
Raven Weapon AG'
        ]
    ],

    // ORDER CANCELLED
    'order.state.cancelled' => [
        'de' => [
            'subject' => 'Bestellung storniert - #{{ order.orderNumber }} | Raven Weapon AG',
            'html' => '<!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Guten Tag {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            Ihre Bestellung <strong>#{{ order.orderNumber }}</strong> wurde storniert.
        </p>

        <!-- Info Box -->
        <div style="background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 8px; padding: 15px; margin-bottom: 25px;">
            <p style="margin: 0; font-size: 13px; color: #92400E;">
                Bei Fragen kontaktieren Sie uns unter <a href="mailto:info@ravenweapon.ch" style="color: #F59E0B;">info@ravenweapon.ch</a>
            </p>
        </div>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.home.page\', {}, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Zum Shop
            </a>
        </div>',
            'plain' => 'Bestellung storniert - Raven Weapon AG

Bestellnummer: {{ order.orderNumber }}

Ihre Bestellung wurde storniert.

Bei Fragen: info@ravenweapon.ch

Mit freundlichen Grüssen
Raven Weapon AG'
        ],
        'en' => [
            'subject' => 'Order cancelled - #{{ order.orderNumber }} | Raven Weapon AG',
            'html' => '<!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Hello {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            Your order <strong>#{{ order.orderNumber }}</strong> has been cancelled.
        </p>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.home.page\', {}, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Visit Shop
            </a>
        </div>',
            'plain' => 'Order cancelled - Raven Weapon AG

Order Number: {{ order.orderNumber }}

Your order has been cancelled.

Best regards,
Raven Weapon AG'
        ]
    ],

    // INVOICE
    'invoice_mail' => [
        'de' => [
            'subject' => 'Rechnung für Ihre Bestellung #{{ order.orderNumber }} | Raven Weapon AG',
            'html' => '<!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Guten Tag {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            anbei erhalten Sie die Rechnung zu Ihrer Bestellung <strong>#{{ order.orderNumber }}</strong>.
        </p>

        <!-- Order Info Box -->
        <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
            <h3 style="color: #F59E0B; margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase;">Rechnungsdetails</h3>
            <table style="width: 100%; color: #ffffff;">
                <tr><td style="padding: 5px 0;"><strong>Bestellnummer:</strong></td><td>{{ order.orderNumber }}</td></tr>
                <tr><td style="padding: 5px 0;"><strong>Betrag:</strong></td><td style="color: #F59E0B;"><strong>{{ order.amountTotal|currency }}</strong></td></tr>
            </table>
        </div>

        <p style="font-size: 16px; margin-bottom: 25px;">
            Die Rechnung finden Sie als PDF im Anhang dieser E-Mail.
        </p>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Bestellung ansehen
            </a>
        </div>',
            'plain' => 'Rechnung - Raven Weapon AG

Bestellnummer: {{ order.orderNumber }}
Betrag: {{ order.amountTotal|currency }}

Die Rechnung finden Sie als PDF im Anhang.

Mit freundlichen Grüssen
Raven Weapon AG'
        ],
        'en' => [
            'subject' => 'Invoice for your order #{{ order.orderNumber }} | Raven Weapon AG',
            'html' => '<!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Hello {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            Please find attached the invoice for your order <strong>#{{ order.orderNumber }}</strong>.
        </p>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                View Order
            </a>
        </div>',
            'plain' => 'Invoice - Raven Weapon AG

Order Number: {{ order.orderNumber }}
Amount: {{ order.amountTotal|currency }}

The invoice is attached as a PDF.

Best regards,
Raven Weapon AG'
        ]
    ],

    // DELIVERY NOTE
    'delivery_mail' => [
        'de' => [
            'subject' => 'Lieferschein für Ihre Bestellung #{{ order.orderNumber }} | Raven Weapon AG',
            'html' => '<!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Guten Tag {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            anbei erhalten Sie den Lieferschein zu Ihrer Bestellung <strong>#{{ order.orderNumber }}</strong>.
        </p>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Bestellung ansehen
            </a>
        </div>',
            'plain' => 'Lieferschein - Raven Weapon AG

Bestellnummer: {{ order.orderNumber }}

Den Lieferschein finden Sie als PDF im Anhang.

Mit freundlichen Grüssen
Raven Weapon AG'
        ],
        'en' => [
            'subject' => 'Delivery note for your order #{{ order.orderNumber }} | Raven Weapon AG',
            'html' => '<!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Hello {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            Please find attached the delivery note for your order <strong>#{{ order.orderNumber }}</strong>.
        </p>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode }, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                View Order
            </a>
        </div>',
            'plain' => 'Delivery Note - Raven Weapon AG

Order Number: {{ order.orderNumber }}

The delivery note is attached as a PDF.

Best regards,
Raven Weapon AG'
        ]
    ],

    // PASSWORD CHANGE
    'password_change' => [
        'de' => [
            'subject' => 'Passwort geändert - Raven Weapon AG',
            'html' => '<!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Guten Tag,
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            Ihr Passwort bei <strong>Raven Weapon AG</strong> wurde erfolgreich geändert.
        </p>

        <!-- Security Note -->
        <div style="background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 8px; padding: 15px; margin-bottom: 25px;">
            <p style="margin: 0; font-size: 13px; color: #92400E;">
                <strong>Hinweis:</strong> Falls Sie diese Änderung nicht vorgenommen haben, kontaktieren Sie uns sofort unter <a href="mailto:info@ravenweapon.ch" style="color: #F59E0B;">info@ravenweapon.ch</a>
            </p>
        </div>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.account.login.page\', {}, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Jetzt anmelden
            </a>
        </div>',
            'plain' => 'Passwort geändert - Raven Weapon AG

Ihr Passwort wurde erfolgreich geändert.

Falls Sie diese Änderung nicht vorgenommen haben, kontaktieren Sie uns sofort.

Mit freundlichen Grüssen
Raven Weapon AG'
        ],
        'en' => [
            'subject' => 'Password changed - Raven Weapon AG',
            'html' => '<!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Hello,
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            Your password at <strong>Raven Weapon AG</strong> has been successfully changed.
        </p>

        <!-- Security Note -->
        <div style="background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 8px; padding: 15px; margin-bottom: 25px;">
            <p style="margin: 0; font-size: 13px; color: #92400E;">
                <strong>Note:</strong> If you did not make this change, please contact us immediately.
            </p>
        </div>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl(\'frontend.account.login.page\', {}, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Sign In Now
            </a>
        </div>',
            'plain' => 'Password changed - Raven Weapon AG

Your password has been successfully changed.

If you did not make this change, please contact us immediately.

Best regards,
Raven Weapon AG'
        ]
    ],
];

// ==================== STEP 3: Update styled templates ====================
echo "Step 3: Updating styled templates...\n\n";

foreach ($templates as $technicalName => $langTemplates) {
    echo "  Updating: $technicalName\n";

    // Get template ID
    $sql = "SELECT LOWER(HEX(mt.id)) as template_id
            FROM mail_template mt
            JOIN mail_template_type mtt ON mt.mail_template_type_id = mtt.id
            WHERE mtt.technical_name = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$technicalName]);
    $templateId = $stmt->fetchColumn();

    if (!$templateId) {
        echo "    ⚠ Template not found, skipping\n";
        continue;
    }

    foreach ($langTemplates as $langCode => $content) {
        $langId = $langMap[$langCode] ?? null;
        if (!$langId) continue;

        $fullHtml = wrapInRavenTemplate($content['html'], $logoUrl, $shopUrl, $langCode);

        $sql = "UPDATE mail_template_translation
                SET content_html = :html,
                    content_plain = :plain,
                    subject = :subject,
                    updated_at = NOW()
                WHERE mail_template_id = UNHEX(:templateId)
                AND language_id = UNHEX(:languageId)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'html' => $fullHtml,
            'plain' => $content['plain'],
            'subject' => $content['subject'],
            'templateId' => $templateId,
            'languageId' => $langId
        ]);

        echo "    ✓ $langCode updated\n";
    }
}

echo "\n✓ All email templates updated!\n";
echo "\nRemember to clear the cache:\n";
echo "bin/console cache:clear\n";
