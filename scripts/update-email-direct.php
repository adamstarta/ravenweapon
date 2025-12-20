<?php
/**
 * Update email template directly via PDO with TCP connection
 */

// Read the base64 logo
$logoBase64 = trim(file_get_contents('/tmp/logo_base64.txt'));
echo "Logo base64: " . strlen($logoBase64) . " bytes\n";

// Database connection via TCP
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=shopware;charset=utf8mb4', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo "Connected to database\n";

$templateId = hex2bin('0191c12ce3597167887615a792f468a0');

// Build the HTML
$htmlContent = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neue Kontaktanfrage</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    <tr>
                        <td align="center" style="padding: 32px 40px 24px 40px; background-color: #ffffff;">
                            <img src="data:image/png;base64,' . $logoBase64 . '" alt="Raven Weapon AG" width="280" style="display: block; max-width: 280px; height: auto;">
                        </td>
                    </tr>
                    <tr>
                        <td style="height: 4px; background: linear-gradient(90deg, #F2B90D 0%, #F6CE55 50%, #FFD54F 100%);"></td>
                    </tr>
                    <tr>
                        <td style="padding: 40px;">
                            <h1 style="margin: 0 0 32px 0; font-family: Arial, sans-serif; font-size: 24px; font-weight: 700; color: #111827; text-align: center;">Neue Kontaktanfrage</h1>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;"><span style="font-family: Arial, sans-serif; font-size: 14px; font-weight: 600; color: #F2B90D;">Name:</span></td>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb; text-align: right;"><span style="font-family: Arial, sans-serif; font-size: 14px; color: #374151;">{{ contactFormData.firstName }} {{ contactFormData.lastName }}</span></td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;"><span style="font-family: Arial, sans-serif; font-size: 14px; font-weight: 600; color: #F2B90D;">E-Mail:</span></td>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb; text-align: right;"><a href="mailto:{{ contactFormData.email }}" style="font-family: Arial, sans-serif; font-size: 14px; color: #374151; text-decoration: none;">{{ contactFormData.email }}</a></td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;"><span style="font-family: Arial, sans-serif; font-size: 14px; font-weight: 600; color: #F2B90D;">Telefon:</span></td>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb; text-align: right;"><a href="tel:{{ contactFormData.phone }}" style="font-family: Arial, sans-serif; font-size: 14px; color: #374151; text-decoration: none;">{{ contactFormData.phone }}</a></td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 0;"><span style="font-family: Arial, sans-serif; font-size: 14px; font-weight: 600; color: #F2B90D;">Betreff:</span></td>
                                    <td style="padding: 12px 0; text-align: right;"><span style="font-family: Arial, sans-serif; font-size: 14px; color: #374151;">{{ contactFormData.subject }}</span></td>
                                </tr>
                            </table>
                            <div style="background-color: #f9fafb; border-radius: 8px; padding: 20px; border-left: 4px solid #F2B90D;">
                                <p style="margin: 0 0 8px 0; font-family: Arial, sans-serif; font-size: 14px; font-weight: 600; color: #F2B90D;">Nachricht:</p>
                                <p style="margin: 0; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #374151;">{{ contactFormData.comment|nl2br }}</p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="height: 4px; background: linear-gradient(90deg, #F2B90D 0%, #F6CE55 50%, #FFD54F 100%);"></td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 40px; background-color: #111827;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center">
                                        <p style="margin: 0 0 8px 0; font-family: Arial, sans-serif; font-size: 16px; font-weight: 700; color: #ffffff;">Raven Weapon AG</p>
                                        <p style="margin: 0 0 4px 0; font-family: Arial, sans-serif; font-size: 13px; color: #9ca3af;">Gorisstrasse 1, 8735 St. Gallenkappel</p>
                                        <p style="margin: 0; font-family: Arial, sans-serif; font-size: 13px; color: #9ca3af;"><a href="mailto:info@ravenweapon.ch" style="color: #F6CE55; text-decoration: none;">info@ravenweapon.ch</a> | <a href="tel:+41793561986" style="color: #F6CE55; text-decoration: none;">+41 79 356 19 86</a></p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; width: 100%;">
                    <tr>
                        <td align="center" style="padding: 24px 20px;"><p style="margin: 0; font-family: Arial, sans-serif; font-size: 11px; color: #9ca3af;">Diese E-Mail wurde automatisch generiert.</p></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

$plainContent = "Neue Kontaktanfrage\n\nName: {{ contactFormData.firstName }} {{ contactFormData.lastName }}\nE-Mail: {{ contactFormData.email }}\nTelefon: {{ contactFormData.phone }}\nBetreff: {{ contactFormData.subject }}\n\nNachricht:\n{{ contactFormData.comment }}\n\n---\nRaven Weapon AG\nGorisstrasse 1, 8735 St. Gallenkappel\ninfo@ravenweapon.ch | +41 79 356 19 86";

$subject = 'Neue Kontaktanfrage von {{ contactFormData.firstName }} {{ contactFormData.lastName }}';

// Update
$stmt = $pdo->prepare("UPDATE mail_template_translation SET content_html = ?, content_plain = ?, subject = ?, updated_at = NOW() WHERE mail_template_id = ?");
$stmt->execute([$htmlContent, $plainContent, $subject, $templateId]);

echo "SUCCESS! Updated " . $stmt->rowCount() . " translation(s)\n";
