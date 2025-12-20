<?php
/**
 * Fix Welcome Email (customer_register) - Logo
 * Replace base64 embedded logo with proper URL
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

// Complete fixed welcome email HTML
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
            Guten Tag {% if customer.salutation %}{{ customer.salutation.translated.letterName ~ ' ' }}{% endif %}{{ customer.firstName }} {{ customer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            herzlich willkommen bei <strong>Raven Weapon AG</strong>!
        </p>

        <p style="font-size: 16px; margin-bottom: 25px; line-height: 1.6;">
            Ihr Kundenkonto wurde erfolgreich erstellt. Ab sofort können Sie alle Vorteile Ihres persönlichen Kontos nutzen.
        </p>

        <!-- Benefits Box -->
        <div style="background: #f8f9fa; border-left: 4px solid #F59E0B; padding: 20px; margin-bottom: 25px; border-radius: 0 8px 8px 0;">
            <h3 style="color: #1a1a1a; margin: 0 0 15px 0; font-size: 16px;">Ihre Vorteile:</h3>
            <ul style="margin: 0; padding-left: 20px; line-height: 1.8; color: #374151;">
                <li>Schneller Checkout mit gespeicherten Daten</li>
                <li>Bestellübersicht und Sendungsverfolgung</li>
                <li>Persönliche Einstellungen verwalten</li>
                <li>Exklusive Angebote für registrierte Kunden</li>
            </ul>
        </div>

        <!-- Account Info -->
        <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
            <h3 style="color: #F59E0B; margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Ihre Kontodaten</h3>
            <p style="margin: 0; color: #ffffff; line-height: 1.6;">
                <strong>E-Mail:</strong> {{ customer.email }}
            </p>
        </div>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ rawUrl('frontend.account.home.page', {}, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
                Zum Kundenkonto
            </a>
        </div>

        <!-- Security Note -->
        <div style="background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 8px; padding: 15px; margin-bottom: 25px;">
            <p style="margin: 0; font-size: 13px; color: #92400E; line-height: 1.5;">
                <strong>Sicherheitshinweis:</strong> Geben Sie Ihre Zugangsdaten niemals an Dritte weiter. Unsere Mitarbeiter werden Sie niemals nach Ihrem Passwort fragen.
            </p>
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
Guten Tag {% if customer.salutation %}{{ customer.salutation.translated.letterName ~ ' ' }}{% endif %}{{ customer.firstName }} {{ customer.lastName }},

herzlich willkommen bei Raven Weapon AG!

Ihr Kundenkonto wurde erfolgreich erstellt. Ab sofort können Sie alle Vorteile Ihres persönlichen Kontos nutzen.

IHRE VORTEILE:
- Schneller Checkout mit gespeicherten Daten
- Bestellübersicht und Sendungsverfolgung
- Persönliche Einstellungen verwalten
- Exklusive Angebote für registrierte Kunden

IHRE KONTODATEN:
E-Mail: {{ customer.email }}

Zum Kundenkonto:
{{ rawUrl('frontend.account.home.page', {}, salesChannel.domains|first.url) }}

SICHERHEITSHINWEIS:
Geben Sie Ihre Zugangsdaten niemals an Dritte weiter. Unsere Mitarbeiter werden Sie niemals nach Ihrem Passwort fragen.

Mit freundlichen Grüssen
Raven Weapon AG

--
Raven Weapon AG | Schweiz
https://ortak.ch
PLAIN;

// Get template IDs
$stmt = $pdo->query("SELECT id FROM mail_template_type WHERE technical_name = 'customer_register'");
$typeId = $stmt->fetchColumn();
if (!$typeId) die("Could not find template type\n");
echo "Found template type: customer_register\n";

$stmt = $pdo->prepare("SELECT id FROM mail_template WHERE mail_template_type_id = ?");
$stmt->execute([$typeId]);
$templateId = $stmt->fetchColumn();
if (!$templateId) die("Could not find template\n");
echo "Found template ID: " . bin2hex($templateId) . "\n";

// Update the template
$stmt = $pdo->prepare("UPDATE mail_template_translation SET content_html = ?, content_plain = ?, updated_at = NOW() WHERE mail_template_id = ?");
$result = $stmt->execute([$htmlContent, $plainContent, $templateId]);

if ($result) {
    $rowCount = $stmt->rowCount();
    echo "✓ Updated $rowCount welcome email template(s)\n";
    echo "  - Logo: https://ortak.ch/bundles/raventheme/assets/raven-logo.png\n";
    echo "  - Removed base64 embedded image\n";
} else {
    echo "Error updating template\n";
}

echo "\nDone!\n";
