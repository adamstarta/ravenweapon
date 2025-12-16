<?php
/**
 * Debug email sending issues in Shopware
 */

require '/var/www/html/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

// Load environment
$dotenv = new Dotenv();
$dotenv->load('/var/www/html/.env');

echo "=== EMAIL DEBUG SCRIPT ===\n\n";

// 1. Check MAILER_DSN
echo "1. MAILER_DSN Configuration:\n";
$mailerDsn = $_ENV['MAILER_DSN'] ?? getenv('MAILER_DSN') ?: '(not set)';
echo "   DSN: $mailerDsn\n\n";

// 2. Check MESSENGER_TRANSPORT_DSN
echo "2. MESSENGER Configuration:\n";
$messengerDsn = $_ENV['MESSENGER_TRANSPORT_DSN'] ?? getenv('MESSENGER_TRANSPORT_DSN') ?: '(not set)';
echo "   DSN: $messengerDsn\n\n";

// 3. Database connection
$pdo = new PDO('mysql:host=127.0.0.1;dbname=shopware', 'root', 'root');

// 3. Check recent orders
echo "3. Recent Orders:\n";
$stmt = $pdo->query("SELECT o.order_number, o.order_date_time, oc.email
                     FROM `order` o
                     JOIN order_customer oc ON o.id = oc.order_id
                     ORDER BY o.order_date_time DESC LIMIT 5");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($orders)) {
    echo "   No orders found!\n";
} else {
    foreach ($orders as $order) {
        echo "   Order #{$order['order_number']} | {$order['order_date_time']} | Email: {$order['email']}\n";
    }
}
echo "\n";

// 4. Check flow for Order placed
echo "4. Order Placed Flow Configuration:\n";
$stmt = $pdo->query("SELECT f.name, f.active, fs.action_name, fs.config
                     FROM flow f
                     JOIN flow_sequence fs ON f.id = fs.flow_id
                     WHERE f.name = 'Order placed' AND fs.action_name IS NOT NULL");
$flows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($flows as $flow) {
    $config = json_decode($flow['config'], true);
    $recipientType = $config['recipient']['type'] ?? 'unknown';
    echo "   Action: {$flow['action_name']} | Active: {$flow['active']} | Recipient: $recipientType\n";
}
echo "\n";

// 5. Check mail template
echo "5. Order Confirmation Mail Template:\n";
$stmt = $pdo->query("SELECT mtt.subject, mtt.sender_name
                     FROM mail_template mt
                     JOIN mail_template_translation mtt ON mt.id = mtt.mail_template_id
                     JOIN mail_template_type mttype ON mt.mail_template_type_id = mttype.id
                     WHERE mttype.technical_name = 'order_confirmation_mail'");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($templates as $t) {
    echo "   Subject: {$t['subject']}\n";
    echo "   Sender: {$t['sender_name']}\n";
}
echo "\n";

// 6. Check system config
echo "6. Email System Configuration:\n";
$stmt = $pdo->query("SELECT configuration_key, CONVERT(configuration_value USING utf8) as val
                     FROM system_config
                     WHERE configuration_key LIKE '%mail%' OR configuration_key LIKE '%email%'");
$configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($configs as $c) {
    $val = json_decode($c['val'], true);
    $value = $val['_value'] ?? $c['val'];
    echo "   {$c['configuration_key']}: $value\n";
}
echo "\n";

// 7. Messenger queue
echo "7. Messenger Queue Status:\n";
$stmt = $pdo->query("SELECT queue_name, COUNT(*) as cnt FROM messenger_messages GROUP BY queue_name");
$queues = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($queues)) {
    echo "   Queue is empty\n";
} else {
    foreach ($queues as $q) {
        echo "   {$q['queue_name']}: {$q['cnt']} messages\n";
    }
}
echo "\n";

// 8. Test SMTP
echo "8. Testing SMTP to alamajacint@gmail.com:\n";
try {
    $transport = Transport::fromDsn($mailerDsn);
    $mailer = new Mailer($transport);

    $email = (new Email())
        ->from('ravenweapon@ortak.ch')
        ->to('alamajacint@gmail.com')
        ->subject('ortak.ch Debug Test - ' . date('Y-m-d H:i:s'))
        ->text('This is a debug test email from ortak.ch.')
        ->html('<h1>Debug Test</h1><p>If you receive this, SMTP is working! Time: ' . date('Y-m-d H:i:s') . '</p>');

    $mailer->send($email);
    echo "   SUCCESS: Email sent!\n";
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== END DEBUG ===\n";
