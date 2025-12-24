<?php
/**
 * Simple script to resend order confirmation using direct SMTP
 */

$orderNumber = $argv[1] ?? '10060';

// Database connection
$pdo = new PDO("mysql:host=localhost;dbname=shopware", "root", "root");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get order details - use table alias to avoid reserved word issue
$sql = "SELECT o.order_number, o.order_date_time,
        CAST(JSON_UNQUOTE(JSON_EXTRACT(o.price, '\$.totalPrice')) AS DECIMAL(10,2)) as total,
        oc.email, oc.first_name, oc.last_name
        FROM " . chr(96) . "order" . chr(96) . " o
        JOIN order_customer oc ON o.id = oc.order_id
        WHERE o.order_number = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$orderNumber]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo "Order not found: $orderNumber\n";
    exit(1);
}

echo "Order found:\n";
echo "  Number: {$order['order_number']}\n";
echo "  Email: {$order['email']}\n";
echo "  Name: {$order['first_name']} {$order['last_name']}\n";
echo "  Total: CHF {$order['total']}\n";

// Get SMTP settings from system_config
$stmt = $pdo->query("SELECT configuration_value FROM system_config WHERE configuration_key = 'core.mailerSettings.host'");
$host = json_decode($stmt->fetchColumn(), true)['_value'] ?? 'localhost';

$stmt = $pdo->query("SELECT configuration_value FROM system_config WHERE configuration_key = 'core.mailerSettings.port'");
$port = json_decode($stmt->fetchColumn(), true)['_value'] ?? 25;

// Use .env MAILER_DSN instead
echo "\nSending via Symfony Mailer...\n";

// Build simple email content
$subject = "Order Confirmation - Order {$order['order_number']} | Raven Weapon AG";

$htmlContent = <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
    <div style="text-align: center; padding: 30px 20px; border-bottom: 3px solid #F59E0B;">
        <img src="https://shop.ravenweapon.ch/bundles/raventheme/assets/raven-logo.png" alt="Raven Weapon" style="max-width: 200px; height: auto;">
    </div>
    <div style="padding: 30px 20px;">
        <p style="font-size: 16px; margin-bottom: 20px;">
            Hello {$order['first_name']} {$order['last_name']},
        </p>
        <p style="font-size: 16px; margin-bottom: 25px;">
            Thank you for your order at <strong>Raven Weapon AG</strong>!
        </p>
        <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
            <h3 style="color: #F59E0B; margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase;">Order Information</h3>
            <table style="width: 100%; color: #ffffff;">
                <tr><td style="padding: 5px 0;"><strong>Order Number:</strong></td><td>{$order['order_number']}</td></tr>
                <tr><td style="padding: 5px 0;"><strong>Total:</strong></td><td>CHF {$order['total']}</td></tr>
            </table>
        </div>
        <div style="text-align: center; margin: 30px 0;">
            <a href="https://shop.ravenweapon.ch/account/order" style="display: inline-block; background: #F59E0B; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                View Your Orders
            </a>
        </div>
        <div style="border-top: 1px solid #eee; padding-top: 20px; margin-top: 30px;">
            <p style="margin: 0; color: #666;">
                Best regards<br>
                <strong style="color: #1a1a1a;">Raven Weapon AG</strong>
            </p>
        </div>
    </div>
    <div style="background: #1a1a1a; color: #ffffff; padding: 20px; text-align: center; font-size: 13px;">
        <p style="margin: 0 0 10px 0;">Raven Weapon AG | Switzerland</p>
        <p style="margin: 0; color: #888;">
            <a href="https://shop.ravenweapon.ch" style="color: #F59E0B; text-decoration: none;">www.ravenweapon.ch</a>
        </p>
    </div>
</div>
HTML;

$plainContent = <<<TEXT
Order Confirmation - Raven Weapon AG

Hello {$order['first_name']} {$order['last_name']},

Thank you for your order at Raven Weapon AG!

Order Number: {$order['order_number']}
Total: CHF {$order['total']}

View your orders: https://shop.ravenweapon.ch/account/order

Best regards,
Raven Weapon AG

---
www.ravenweapon.ch
TEXT;

// Use PHP's mail with proper headers - but we need SMTP
// Let's use SwiftMailer or Symfony Mailer

// Read MAILER_DSN from .env
$envFile = file_get_contents('/var/www/html/.env');
preg_match('/MAILER_DSN=(.+)/', $envFile, $matches);
$mailerDsn = trim($matches[1] ?? '');

echo "Mailer DSN: " . substr($mailerDsn, 0, 50) . "...\n";

// Parse DSN: smtp://user:pass@host:port
if (preg_match('/smtp:\/\/([^:]+):([^@]+)@([^:]+):(\d+)/', $mailerDsn, $m)) {
    $smtpUser = urldecode($m[1]);
    $smtpPass = urldecode($m[2]);
    $smtpHost = $m[3];
    $smtpPort = (int)$m[4];

    echo "SMTP Host: $smtpHost:$smtpPort\n";
    echo "SMTP User: $smtpUser\n";

    // Send email using fsockopen for SMTP
    $to = $order['email'];
    $from = "info@ravenweapon.ch";
    $fromName = "Raven Weapon AG";

    $boundary = md5(time());

    $headers = [
        "From: $fromName <$from>",
        "Reply-To: $from",
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"$boundary\"",
        "X-Mailer: PHP/" . phpversion()
    ];

    $body = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= $plainContent . "\r\n\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $htmlContent . "\r\n\r\n";
    $body .= "--$boundary--";

    // Connect to SMTP
    $socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 30);
    if (!$socket) {
        // Try with TLS wrapper
        $socket = @fsockopen("tls://$smtpHost", $smtpPort, $errno, $errstr, 30);
    }

    if (!$socket) {
        echo "Could not connect to SMTP server: $errstr ($errno)\n";
        exit(1);
    }

    // SMTP conversation
    $response = fgets($socket, 515);
    echo "Server: $response";

    fputs($socket, "EHLO ravenweapon.ch\r\n");
    while ($line = fgets($socket, 515)) {
        echo "< $line";
        if (substr($line, 3, 1) == ' ') break;
    }

    fputs($socket, "STARTTLS\r\n");
    $response = fgets($socket, 515);
    echo "< $response";

    // Enable TLS
    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

    // Re-EHLO after STARTTLS
    fputs($socket, "EHLO ravenweapon.ch\r\n");
    while ($line = fgets($socket, 515)) {
        echo "< $line";
        if (substr($line, 3, 1) == ' ') break;
    }

    // AUTH LOGIN
    fputs($socket, "AUTH LOGIN\r\n");
    $response = fgets($socket, 515);
    echo "< $response";

    fputs($socket, base64_encode($smtpUser) . "\r\n");
    $response = fgets($socket, 515);
    echo "< $response";

    fputs($socket, base64_encode($smtpPass) . "\r\n");
    $response = fgets($socket, 515);
    echo "< $response";

    if (strpos($response, '235') === false) {
        echo "AUTH failed!\n";
        fclose($socket);
        exit(1);
    }

    // MAIL FROM
    fputs($socket, "MAIL FROM:<$from>\r\n");
    $response = fgets($socket, 515);
    echo "< $response";

    // RCPT TO
    fputs($socket, "RCPT TO:<$to>\r\n");
    $response = fgets($socket, 515);
    echo "< $response";

    // DATA
    fputs($socket, "DATA\r\n");
    $response = fgets($socket, 515);
    echo "< $response";

    // Message
    $message = "To: $to\r\n";
    $message .= "Subject: $subject\r\n";
    $message .= implode("\r\n", $headers) . "\r\n\r\n";
    $message .= $body . "\r\n.\r\n";

    fputs($socket, $message);
    $response = fgets($socket, 515);
    echo "< $response";

    if (strpos($response, '250') !== false) {
        echo "\n✅ Email sent successfully to: $to\n";
    } else {
        echo "\n❌ Failed to send email\n";
    }

    fputs($socket, "QUIT\r\n");
    fclose($socket);

} else {
    echo "Could not parse MAILER_DSN\n";
    exit(1);
}
