<?php
/**
 * Fix Order Confirmation Email Template
 * The |currency filter was failing because currencyIsoCode couldn't be resolved
 */

$pdo = new PDO("mysql:host=localhost;dbname=shopware", "root", "root");

// Fix English template - use currency symbol directly
$englishPlain = <<<'EOT'
Order Confirmation - Raven Weapon AG

Order Number: {{ order.orderNumber }}
Total: CHF {{ order.amountTotal|number_format(2, '.', '') }}

Thank you for your order!

Best regards,
Raven Weapon AG
EOT;

// Fix German template
$germanPlain = <<<'EOT'
Bestellbestätigung - Raven Weapon AG

Bestellnummer: {{ order.orderNumber }}
Gesamtsumme: CHF {{ order.amountTotal|number_format(2, '.', '') }}

Vielen Dank für Ihre Bestellung!

Mit freundlichen Grüssen
Raven Weapon AG
EOT;

// Update English (language_id: 2fbb5fe2e29a4d70aa5854ce7ce3e20b)
$stmt = $pdo->prepare("UPDATE mail_template_translation SET content_plain = ?, updated_at = NOW() WHERE LOWER(HEX(mail_template_id)) = ? AND LOWER(HEX(language_id)) = ?");
$stmt->execute([$englishPlain, "0191c12cd21173b28a740d7e69c8ed29", "2fbb5fe2e29a4d70aa5854ce7ce3e20b"]);
echo "English template updated: " . $stmt->rowCount() . " rows\n";

// Update German (language_id: 0191c12cc15e72189d57328fb3d2d987)
$stmt->execute([$germanPlain, "0191c12cd21173b28a740d7e69c8ed29", "0191c12cc15e72189d57328fb3d2d987"]);
echo "German template updated: " . $stmt->rowCount() . " rows\n";

echo "\nEmail template fix complete!\n";
