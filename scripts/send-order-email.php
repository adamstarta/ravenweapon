<?php
/**
 * Manually Send Order Confirmation Email
 */

require_once '/var/www/html/vendor/autoload.php';

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

// Database connection
$host = '127.0.0.1';
$dbname = 'shopware';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Get latest order
    $stmt = $pdo->query("
        SELECT
            o.order_number,
            o.order_date_time,
            oc.email as customer_email,
            oc.first_name,
            oc.last_name,
            o.amount_total
        FROM `order` o
        JOIN order_customer oc ON o.id = oc.order_id
        ORDER BY o.order_date_time DESC
        LIMIT 1
    ");

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("No orders found\n");
    }

    echo "Found order: #{$order['order_number']}\n";
    echo "Customer: {$order['first_name']} {$order['last_name']}\n";
    echo "Email: {$order['customer_email']}\n";
    echo "Total: CHF " . number_format($order['amount_total'], 2) . "\n\n";

    // Send email directly using SMTP
    $dsn = 'smtp://ravenweapon%40ortak.ch:Barsalarsa123.@asmtp.mail.hostpoint.ch:587?encryption=tls';

    $transport = Transport::fromDsn($dsn);
    $mailer = new Mailer($transport);

    $htmlBody = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #111827; color: #F2B90D; padding: 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; }
            .content { padding: 20px; background: #f8f9fa; }
            .order-info { background: white; padding: 15px; border-radius: 8px; margin: 15px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>RAVEN WEAPON AG</h1>
            </div>
            <div class='content'>
                <h2>Vielen Dank für Ihre Bestellung!</h2>
                <p>Hallo {$order['first_name']} {$order['last_name']},</p>
                <p>Ihre Bestellung wurde erfolgreich aufgenommen. Hier sind Ihre Bestelldetails:</p>

                <div class='order-info'>
                    <p><strong>Bestellnummer:</strong> #{$order['order_number']}</p>
                    <p><strong>Bestelldatum:</strong> " . date('d.m.Y H:i', strtotime($order['order_date_time'])) . "</p>
                    <p><strong>Gesamtbetrag:</strong> CHF " . number_format($order['amount_total'], 2, '.', "'") . "</p>
                </div>

                <h3>Zahlungsinformationen (Vorkasse)</h3>
                <div class='order-info'>
                    <p><strong>Kontoinhaber:</strong> Raven Weapon AG</p>
                    <p><strong>Bank:</strong> PostFinance</p>
                    <p><strong>IBAN:</strong> CH6009000000165059892</p>
                    <p><strong>BIC/SWIFT:</strong> POFICHBEXXX</p>
                    <p><strong>Verwendungszweck:</strong> {$order['order_number']}</p>
                </div>

                <p>Nach Zahlungseingang wird Ihre Bestellung umgehend bearbeitet.</p>
                <p>Bei Fragen stehen wir Ihnen gerne zur Verfügung.</p>
                <p>Mit freundlichen Grüssen<br>Ihr Raven Weapon AG Team</p>
            </div>
            <div class='footer'>
                <p>Raven Weapon AG | Sunnenbergstrasse 2 | 8633 Wolfhausen</p>
                <p>Tel: +41 79 356 19 86 | Email: info@ravenweapon.ch</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $email = (new Email())
        ->from(new Address('ravenweapon@ortak.ch', 'Raven Weapon AG'))
        ->to($order['customer_email'])
        ->subject("Bestellbestätigung - Bestellung #{$order['order_number']}")
        ->html($htmlBody);

    $mailer->send($email);
    echo "✅ Order confirmation email sent to {$order['customer_email']}!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
