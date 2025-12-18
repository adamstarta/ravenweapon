<?php
/**
 * Test SMTP connection and send actual email
 */

require_once '/var/www/html/vendor/autoload.php';

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

echo "=== SMTP Email Test ===\n\n";

echo "1. Testing SMTP connection to asmtp.mail.hostpoint.ch:587...\n";

$socket = @fsockopen("asmtp.mail.hostpoint.ch", 587, $errno, $errstr, 10);

if ($socket) {
    echo "SUCCESS: SMTP connection established!\n";

    // Read greeting
    $response = fgets($socket, 515);
    echo "Server response: $response\n";

    // Send EHLO
    fwrite($socket, "EHLO localhost\r\n");
    $ehlo = '';
    while ($line = fgets($socket, 515)) {
        $ehlo .= $line;
        if ($line[3] == ' ') break;
    }
    echo "EHLO response:\n$ehlo\n";

    fwrite($socket, "QUIT\r\n");
    fclose($socket);
} else {
    echo "FAILED: Could not connect - $errstr ($errno)\n";
}

// Now test with Symfony Mailer
echo "\n--- Testing Symfony Mailer DSN ---\n";

// Get the actual DSN being used
$envMailer = getenv('MAILER_DSN');
echo "MAILER_DSN from env: " . ($envMailer ?: '(not set)') . "\n";

// Read from .env if not in environment
$dsn = 'smtp://ravenweapon%40ortak.ch:Barsalarsa123.@asmtp.mail.hostpoint.ch:587?encryption=tls';
if (!$envMailer && file_exists('/var/www/html/.env')) {
    $env = file_get_contents('/var/www/html/.env');
    if (preg_match('/^MAILER_DSN=(.+)$/m', $env, $m)) {
        $dsn = trim($m[1]);
        echo "MAILER_DSN from .env: " . $dsn . "\n";
    }
}

// Now try to actually send an email
echo "\n3. Sending test email via Symfony Mailer...\n";

try {
    $transport = Transport::fromDsn($dsn);
    $mailer = new Mailer($transport);

    $email = (new Email())
        ->from('ravenweapon@ortak.ch')
        ->to('alamajacint@gmail.com')
        ->subject('Test Email from Raven Weapon - ' . date('Y-m-d H:i:s'))
        ->text('This is a test email sent directly via Symfony Mailer to verify SMTP is working.')
        ->html('<h1>Test Email</h1><p>This is a test email sent directly via Symfony Mailer.</p><p>Time: ' . date('Y-m-d H:i:s') . '</p>');

    $mailer->send($email);
    echo "SUCCESS: Email sent!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
