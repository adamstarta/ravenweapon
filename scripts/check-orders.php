<?php
/**
 * Check Recent Orders and Email Status
 */

$host = '127.0.0.1';
$port = 3306;
$dbname = 'shopware';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "=== Recent Orders ===\n";
    $stmt = $pdo->query("
        SELECT
            HEX(o.id) as order_id,
            o.order_number,
            o.order_date_time,
            oc.email as customer_email
        FROM `order` o
        LEFT JOIN order_customer oc ON o.id = oc.order_id
        ORDER BY o.order_date_time DESC
        LIMIT 5
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "Order: #{$row['order_number']} | Date: {$row['order_date_time']} | Email: {$row['customer_email']}\n";
    }

    echo "\n=== Mail Template for Order Confirmation ===\n";
    $stmt = $pdo->query("
        SELECT
            HEX(mt.id) as template_id,
            mtt.sender_name,
            mtt.subject
        FROM mail_template mt
        JOIN mail_template_translation mtt ON mt.id = mtt.mail_template_id
        JOIN mail_template_type mtype ON mt.mail_template_type_id = mtype.id
        WHERE mtype.technical_name = 'order_confirmation_mail'
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "Template ID: {$row['template_id']}\n";
        echo "Sender: {$row['sender_name']}\n";
        echo "Subject: {$row['subject']}\n";
    }

    echo "\n=== Checking Order Placed Flow ===\n";
    $stmt = $pdo->query("
        SELECT
            f.name,
            f.active,
            fs.action_name,
            fs.config
        FROM flow f
        JOIN flow_sequence fs ON f.id = fs.flow_id
        WHERE f.event_name = 'checkout.order.placed'
        AND fs.action_name LIKE '%mail%'
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "Flow: {$row['name']} | Active: {$row['active']}\n";
        echo "Action: {$row['action_name']}\n";
        $config = json_decode($row['config'], true);
        if (isset($config['mailTemplateId'])) {
            echo "Mail Template ID: {$config['mailTemplateId']}\n";
        }
        echo "---\n";
    }

    echo "\n=== Pending Messages in Queue ===\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM messenger_messages");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Pending messages: $count\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
