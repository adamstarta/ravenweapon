<?php
/**
 * Update double opt-in email template to match Welcome email style
 * Also updates logo URLs from ortak.ch to ravenweapon.ch
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

echo "=== Updating Double Opt-In Email Template ===\n\n";

// Get the mail template ID for double opt-in
$sql = "SELECT LOWER(HEX(mtpl.id)) as template_id, LOWER(HEX(mtt.language_id)) as language_id, l.name as lang_name
        FROM mail_template_type mt
        JOIN mail_template mtpl ON mt.id = mtpl.mail_template_type_id
        JOIN mail_template_translation mtt ON mtpl.id = mtt.mail_template_id
        JOIN language l ON mtt.language_id = l.id
        WHERE mt.technical_name = 'customer_register.double_opt_in'";
$stmt = $pdo->query($sql);
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($templates) . " template translations\n\n";

// German HTML template
$germanHtml = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
    <!-- Header with Logo -->
    <div style="text-align: center; padding: 30px 20px; border-bottom: 3px solid #F59E0B;">
        <img src="https://shop.ravenweapon.ch/bundles/raventheme/assets/raven-logo.png" alt="Raven Weapon" style="max-width: 200px; height: auto;">
    </div>

    <!-- Content -->
    <div style="padding: 30px 20px;">
        <!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Guten Tag {% if customer.salutation %}{{ customer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ customer.firstName }} {{ customer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            vielen Dank für Ihre Registrierung bei <strong>Raven Weapon AG</strong>!
        </p>

        <p style="font-size: 16px; margin-bottom: 25px; line-height: 1.6;">
            Um Ihre Registrierung abzuschliessen und Ihr Kundenkonto zu aktivieren, bestätigen Sie bitte Ihre E-Mail-Adresse.
        </p>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 35px 0;">
            <a href="{{ confirmUrl }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 16px 40px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
                E-Mail bestätigen
            </a>
        </div>

        <!-- Info Box -->
        <div style="background: #f8f9fa; border-left: 4px solid #F59E0B; padding: 20px; margin-bottom: 25px; border-radius: 0 8px 8px 0;">
            <p style="margin: 0; font-size: 14px; color: #374151; line-height: 1.6;">
                Falls der Button nicht funktioniert, kopieren Sie bitte diesen Link in Ihren Browser:<br>
                <a href="{{ confirmUrl }}" style="color: #F59E0B; word-break: break-all;">{{ confirmUrl }}</a>
            </p>
        </div>

        <!-- Security Note -->
        <div style="background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 8px; padding: 15px; margin-bottom: 25px;">
            <p style="margin: 0; font-size: 13px; color: #92400E; line-height: 1.5;">
                <strong>Hinweis:</strong> Durch die Bestätigung erklären Sie sich damit einverstanden, dass wir Ihnen im Rahmen der Vertragserfüllung weitere E-Mails senden dürfen.
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
            <a href="https://ravenweapon.ch" style="color: #F59E0B; text-decoration: none;">www.ravenweapon.ch</a>
        </p>
    </div>
</div>';

// German plain text
$germanPlain = 'Guten Tag {% if customer.salutation %}{{ customer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ customer.firstName }} {{ customer.lastName }},

vielen Dank für Ihre Registrierung bei Raven Weapon AG!

Um Ihre Registrierung abzuschliessen und Ihr Kundenkonto zu aktivieren, bestätigen Sie bitte Ihre E-Mail-Adresse über folgenden Link:

{{ confirmUrl }}

Durch die Bestätigung erklären Sie sich damit einverstanden, dass wir Ihnen im Rahmen der Vertragserfüllung weitere E-Mails senden dürfen.

Mit freundlichen Grüssen
Raven Weapon AG

---
Raven Weapon AG | Schweiz
www.ravenweapon.ch';

// German subject
$germanSubject = 'Bitte bestätigen Sie Ihre Registrierung bei Raven Weapon AG';

// English HTML template
$englishHtml = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
    <!-- Header with Logo -->
    <div style="text-align: center; padding: 30px 20px; border-bottom: 3px solid #F59E0B;">
        <img src="https://shop.ravenweapon.ch/bundles/raventheme/assets/raven-logo.png" alt="Raven Weapon" style="max-width: 200px; height: auto;">
    </div>

    <!-- Content -->
    <div style="padding: 30px 20px;">
        <!-- Greeting -->
        <p style="font-size: 16px; margin-bottom: 20px;">
            Hello {% if customer.salutation %}{{ customer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ customer.firstName }} {{ customer.lastName }},
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">
            Thank you for registering with <strong>Raven Weapon AG</strong>!
        </p>

        <p style="font-size: 16px; margin-bottom: 25px; line-height: 1.6;">
            To complete your registration and activate your customer account, please confirm your email address.
        </p>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 35px 0;">
            <a href="{{ confirmUrl }}"
               style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 16px 40px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
                Confirm Email
            </a>
        </div>

        <!-- Info Box -->
        <div style="background: #f8f9fa; border-left: 4px solid #F59E0B; padding: 20px; margin-bottom: 25px; border-radius: 0 8px 8px 0;">
            <p style="margin: 0; font-size: 14px; color: #374151; line-height: 1.6;">
                If the button doesn\'t work, please copy this link into your browser:<br>
                <a href="{{ confirmUrl }}" style="color: #F59E0B; word-break: break-all;">{{ confirmUrl }}</a>
            </p>
        </div>

        <!-- Security Note -->
        <div style="background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 8px; padding: 15px; margin-bottom: 25px;">
            <p style="margin: 0; font-size: 13px; color: #92400E; line-height: 1.5;">
                <strong>Note:</strong> By confirming, you agree that we may send you further emails as part of fulfilling our contract.
            </p>
        </div>

        <!-- Footer -->
        <div style="border-top: 1px solid #eee; padding-top: 20px; margin-top: 20px;">
            <p style="margin: 0; color: #666;">
                Best regards,<br>
                <strong style="color: #1a1a1a;">Raven Weapon AG</strong>
            </p>
        </div>
    </div>

    <!-- Email Footer -->
    <div style="background: #1a1a1a; color: #ffffff; padding: 20px; text-align: center; font-size: 13px;">
        <p style="margin: 0 0 10px 0;">Raven Weapon AG | Switzerland</p>
        <p style="margin: 0; color: #888;">
            <a href="https://ravenweapon.ch" style="color: #F59E0B; text-decoration: none;">www.ravenweapon.ch</a>
        </p>
    </div>
</div>';

// English plain text
$englishPlain = 'Hello {% if customer.salutation %}{{ customer.salutation.translated.letterName ~ \' \' }}{% endif %}{{ customer.firstName }} {{ customer.lastName }},

Thank you for registering with Raven Weapon AG!

To complete your registration and activate your customer account, please confirm your email address by clicking the link below:

{{ confirmUrl }}

By confirming, you agree that we may send you further emails as part of fulfilling our contract.

Best regards,
Raven Weapon AG

---
Raven Weapon AG | Switzerland
www.ravenweapon.ch';

// English subject
$englishSubject = 'Please confirm your registration with Raven Weapon AG';

// Update each language
foreach ($templates as $template) {
    $templateId = $template['template_id'];
    $languageId = $template['language_id'];
    $langName = $template['lang_name'];

    echo "Updating $langName template...\n";

    if (stripos($langName, 'deutsch') !== false || stripos($langName, 'german') !== false) {
        $html = $germanHtml;
        $plain = $germanPlain;
        $subject = $germanSubject;
    } else {
        $html = $englishHtml;
        $plain = $englishPlain;
        $subject = $englishSubject;
    }

    $sql = "UPDATE mail_template_translation
            SET content_html = :html,
                content_plain = :plain,
                subject = :subject,
                updated_at = NOW()
            WHERE mail_template_id = UNHEX(:templateId)
            AND language_id = UNHEX(:languageId)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'html' => $html,
        'plain' => $plain,
        'subject' => $subject,
        'templateId' => $templateId,
        'languageId' => $languageId
    ]);

    echo "  Updated: $langName (" . $stmt->rowCount() . " rows)\n";
}

echo "\n=== Also updating Welcome email URLs (ortak.ch -> ravenweapon.ch) ===\n\n";

// Update Welcome email to use ravenweapon.ch URLs
$sql = "UPDATE mail_template_translation mtt
        JOIN mail_template mt ON mtt.mail_template_id = mt.id
        JOIN mail_template_type mtt2 ON mt.mail_template_type_id = mtt2.id
        SET mtt.content_html = REPLACE(REPLACE(mtt.content_html, 'ortak.ch', 'ravenweapon.ch'), 'www.ortak.ch', 'www.ravenweapon.ch'),
            mtt.content_plain = REPLACE(REPLACE(mtt.content_plain, 'ortak.ch', 'ravenweapon.ch'), 'www.ortak.ch', 'www.ravenweapon.ch'),
            mtt.updated_at = NOW()
        WHERE mtt2.technical_name = 'customer_register'";
$stmt = $pdo->query($sql);
echo "Updated Welcome email URLs: " . $stmt->rowCount() . " rows\n";

echo "\n✓ Done! Double opt-in email now matches Welcome email style.\n";
