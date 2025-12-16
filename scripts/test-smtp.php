<?php
/**
 * Test SMTP connection to Hostpoint
 */

echo "Testing SMTP connection to asmtp.mail.hostpoint.ch:587...\n";

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
if (!$envMailer && file_exists('/var/www/html/.env')) {
    $env = file_get_contents('/var/www/html/.env');
    if (preg_match('/^MAILER_DSN=(.+)$/m', $env, $m)) {
        echo "MAILER_DSN from .env: " . $m[1] . "\n";
    }
}
