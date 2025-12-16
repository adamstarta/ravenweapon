<?php
/**
 * Test Email Sending from Shopware
 */

require_once '/var/www/html/vendor/autoload.php';

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

$dsn = 'smtp://ravenweapon%40ortak.ch:Barsalarsa123.@asmtp.mail.hostpoint.ch:587?encryption=tls';
echo "Testing email with DSN: $dsn\n\n";

try {
    $transport = Transport::fromDsn($dsn);
    $mailer = new Mailer($transport);

    $email = (new Email())
        ->from('ravenweapon@ortak.ch')
        ->to('alamajacint@gmail.com')
        ->subject('Test Email from Raven Weapon AG')
        ->text('This is a test email to verify email configuration is working correctly.');

    $mailer->send($email);
    echo "✅ Email sent successfully to alamajacint@gmail.com!\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
