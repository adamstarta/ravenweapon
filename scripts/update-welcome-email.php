<?php
/**
 * Update customer registration (welcome) email template
 */

// Read the base64 logo
$logoBase64 = trim(file_get_contents('/tmp/logo_base64.txt'));
echo "Logo base64: " . strlen($logoBase64) . " bytes\n";

// Database connection via TCP
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=shopware;charset=utf8mb4', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo "Connected to database\n";

$templateId = hex2bin('0191c12cd22972968f759527c068b06a');

// Build the HTML - clean design matching contact form
$htmlContent = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Willkommen bei Raven Weapon AG</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">

                    <!-- Header with Logo -->
                    <tr>
                        <td align="center" style="padding: 32px 40px 24px 40px; background-color: #ffffff;">
                            <img src="data:image/png;base64,' . $logoBase64 . '" alt="Raven Weapon AG" width="280" style="display: block; max-width: 280px; height: auto;">
                        </td>
                    </tr>

                    <!-- Gold Gradient Line -->
                    <tr>
                        <td style="height: 4px; background: linear-gradient(90deg, #F2B90D 0%, #F6CE55 50%, #FFD54F 100%);"></td>
                    </tr>

                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h1 style="margin: 0 0 24px 0; font-family: Arial, sans-serif; font-size: 24px; font-weight: 700; color: #111827; text-align: center;">
                                Willkommen bei Raven Weapon AG!
                            </h1>

                            <p style="margin: 0 0 16px 0; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #374151;">
                                Sehr geehrte(r) {{ customer.salutation.displayName }} {{ customer.lastName }},
                            </p>

                            <p style="margin: 0 0 16px 0; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #374151;">
                                herzlich willkommen bei <strong>Raven Weapon AG</strong>!
                            </p>

                            <p style="margin: 0 0 24px 0; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #374151;">
                                Ihr Kundenkonto wurde erfolgreich erstellt. Sie können sich ab sofort mit Ihrer E-Mail-Adresse anmelden und von exklusiven Angeboten profitieren.
                            </p>

                            <!-- CTA Button -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" style="padding: 16px 0;">
                                        <a href="{{ rawUrl(\'frontend.account.login.page\', [], salesChannel.domains|first.url) }}" style="display: inline-block; background: linear-gradient(135deg, #F2B90D 0%, #F6CE55 50%, #FFD54F 100%); color: #1f2937; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-family: Arial, sans-serif; font-weight: 600; font-size: 15px;">
                                            Jetzt einloggen
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 24px 0 0 0; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #374151;">
                                Mit freundlichen Grüssen<br>
                                <strong>Raven Weapon AG</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Gold Gradient Line -->
                    <tr>
                        <td style="height: 4px; background: linear-gradient(90deg, #F2B90D 0%, #F6CE55 50%, #FFD54F 100%);"></td>
                    </tr>

                    <!-- Dark Footer -->
                    <tr>
                        <td style="padding: 32px 40px; background-color: #111827;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center">
                                        <p style="margin: 0 0 8px 0; font-family: Arial, sans-serif; font-size: 16px; font-weight: 700; color: #ffffff;">Raven Weapon AG</p>
                                        <p style="margin: 0 0 4px 0; font-family: Arial, sans-serif; font-size: 13px; color: #9ca3af;">Gorisstrasse 1, 8735 St. Gallenkappel</p>
                                        <p style="margin: 0; font-family: Arial, sans-serif; font-size: 13px; color: #9ca3af;">
                                            <a href="mailto:info@ravenweapon.ch" style="color: #F6CE55; text-decoration: none;">info@ravenweapon.ch</a>
                                            <span style="color: #6b7280;"> | </span>
                                            <a href="tel:+41793561986" style="color: #F6CE55; text-decoration: none;">+41 79 356 19 86</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>

                <!-- Bottom spacing -->
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; width: 100%;">
                    <tr>
                        <td align="center" style="padding: 24px 20px;">
                            <p style="margin: 0; font-family: Arial, sans-serif; font-size: 11px; color: #9ca3af;">
                                Diese E-Mail wurde automatisch generiert.
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>';

$plainContent = "Willkommen bei Raven Weapon AG!

Sehr geehrte(r) {{ customer.salutation.displayName }} {{ customer.lastName }},

herzlich willkommen bei Raven Weapon AG!

Ihr Kundenkonto wurde erfolgreich erstellt. Sie können sich ab sofort mit Ihrer E-Mail-Adresse anmelden und von exklusiven Angeboten profitieren.

Jetzt einloggen: {{ rawUrl('frontend.account.login.page', [], salesChannel.domains|first.url) }}

Mit freundlichen Grüssen
Raven Weapon AG

---
Raven Weapon AG
Gorisstrasse 1, 8735 St. Gallenkappel
info@ravenweapon.ch | +41 79 356 19 86";

$subject = 'Willkommen bei Raven Weapon AG, {{ customer.firstName }}!';

// Update
$stmt = $pdo->prepare("UPDATE mail_template_translation SET content_html = ?, content_plain = ?, subject = ?, updated_at = NOW() WHERE mail_template_id = ?");
$stmt->execute([$htmlContent, $plainContent, $subject, $templateId]);

echo "SUCCESS! Updated " . $stmt->rowCount() . " translation(s) for customer_register\n";
