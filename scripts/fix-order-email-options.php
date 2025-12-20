<?php
/**
 * Fix Order Confirmation Email - Show Product Options (Size/Color)
 */

$host = getenv('DATABASE_HOST') ?: '127.0.0.1';
$dbname = getenv('DATABASE_NAME') ?: 'shopware';
$user = getenv('DATABASE_USER') ?: 'root';
$pass = getenv('DATABASE_PASSWORD') ?: 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to database\n";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// Complete fixed HTML template with product options display
$htmlContent = <<<'HTML'
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
    <!-- Header with Logo -->
    <div style="text-align: center; padding: 30px 20px; border-bottom: 3px solid #F59E0B;">
        <img src="https://ortak.ch/bundles/raventheme/assets/raven-logo.png" alt="Raven Weapon" style="max-width: 200px; height: auto;">
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
                    {% if lineItem.cover %}
                        {% if lineItem.cover.url is defined and lineItem.cover.url %}
                            {% set rawUrl = lineItem.cover.url %}
                        {% elseif lineItem.cover.path is defined and lineItem.cover.path %}
                            {% set rawUrl = lineItem.cover.path %}
                        {% endif %}
                        {% if rawUrl is defined and rawUrl %}
                            {% set encodedUrl = rawUrl|replace({' ': '%20'}) %}
                            {% if encodedUrl starts with 'http' %}
                                {% set imageUrl = encodedUrl %}
                            {% elseif encodedUrl starts with '/' %}
                                {% set imageUrl = 'https://ortak.ch' ~ encodedUrl %}
                            {% else %}
                                {% set imageUrl = 'https://ortak.ch/' ~ encodedUrl %}
                            {% endif %}
                        {% endif %}
                    {% endif %}
                    {% if imageUrl %}
                    <img src="{{ imageUrl }}" alt="{{ lineItem.label }}" style="width: 80px; height: 80px; object-fit: contain; border: 1px solid #eee; border-radius: 4px; background: #fff;">
                    {% else %}
                    <div style="width: 80px; height: 80px; background: #f5f5f5; border-radius: 4px;"></div>
                    {% endif %}
                </td>
                <td style="padding: 15px 10px; vertical-align: top;">
                    <strong style="font-size: 15px;">{{ lineItem.label }}</strong>

                    {# Display product options (color/size/variant) #}
                    {% if lineItem.payload.variantenDisplay is defined and lineItem.payload.variantenDisplay %}
                    <br><span style="color: #F59E0B; font-size: 13px; font-weight: 600;">Variante: {{ lineItem.payload.variantenDisplay }}</span>
                    {% endif %}

                    {% if lineItem.payload.selectedColor is defined and lineItem.payload.selectedColor %}
                    <br><span style="color: #F59E0B; font-size: 13px; font-weight: 600;">Farbe: {{ lineItem.payload.selectedColor }}</span>
                    {% endif %}

                    {% if lineItem.payload.selectedSize is defined and lineItem.payload.selectedSize %}
                    <br><span style="color: #F59E0B; font-size: 13px; font-weight: 600;">Größe: {{ lineItem.payload.selectedSize }}</span>
                    {% endif %}

                    {# Display variant options from options array #}
                    {% if lineItem.payload.options is defined and lineItem.payload.options|length > 0 %}
                        {% for option in lineItem.payload.options %}
                        <br><span style="color: #F59E0B; font-size: 13px; font-weight: 600;">{{ option.group }}: {{ option.option }}</span>
                        {% endfor %}
                    {% endif %}

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
                <td style="padding: 8px 0; text-align: right;">{{ order.price.positionPrice|currency(order.currency.isoCode) }}</td>
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
            <h4 style="color: #92400E; margin: 0 0 15px 0;">Zahlungsinformationen</h4>
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
            <p style="margin: 15px 0 0 0; font-size: 13px; color: #92400E;">Nach Zahlungseingang wird Ihre Bestellung bearbeitet.</p>
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

// Get template IDs
$stmt = $pdo->query("SELECT id FROM mail_template_type WHERE technical_name = 'order_confirmation_mail'");
$typeId = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT id FROM mail_template WHERE mail_template_type_id = ?");
$stmt->execute([$typeId]);
$templateId = $stmt->fetchColumn();

echo "Updating order confirmation email template...\n";

// Update the template
$stmt = $pdo->prepare("UPDATE mail_template_translation SET content_html = ?, updated_at = NOW() WHERE mail_template_id = ?");
$result = $stmt->execute([$htmlContent, $templateId]);

if ($result) {
    $rowCount = $stmt->rowCount();
    echo "✓ Updated $rowCount template(s)\n";
    echo "  - Added: Variante display (variantenDisplay) - for Snigel size/color\n";
    echo "  - Added: Farbe display (selectedColor) - for Raven colors\n";
    echo "  - Added: Größe display (selectedSize) - for sizes\n";
    echo "  - Added: Options array display - for variant options\n";
} else {
    echo "Error updating template\n";
}

echo "\nDone!\n";
