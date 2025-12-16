<?php
/**
 * Send a test email using Symfony Mailer (same as Shopware uses)
 */

require '/var/www/html/vendor/autoload.php';

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

// Get DSN from .env
$dsnString = 'smtp://ravenweapon%40ortak.ch:Barsalarsa123.@asmtp.mail.hostpoint.ch:587?encryption=tls';

echo "Using DSN: $dsnString\n\n";

try {
    $transport = Transport::fromDsn($dsnString);
    $mailer = new Mailer($transport);

    $email = (new Email())
        ->from('ravenweapon@ortak.ch')
        ->to('nikola.starta@gmail.com')
        ->subject('Test Email from ortak.ch - ' . date('Y-m-d H:i:s'))
        ->text('This is a test email sent from ortak.ch Shopware shop.')
        ->html('<h1>Test Email</h1><p>This is a test email sent from ortak.ch Shopware shop at ' . date('Y-m-d H:i:s') . '</p>');

    echo "Sending email...\n";
    $mailer->send($email);
    echo "SUCCESS: Email sent!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "\nFull stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
