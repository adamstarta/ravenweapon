<?php
/**
 * Check Flow Configuration in Detail
 */

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

    echo "=== Order Placed Flow Details ===\n\n";

    // Check flow
    $stmt = $pdo->query("
        SELECT
            HEX(f.id) as flow_id,
            f.name,
            f.active,
            f.event_name,
            f.priority,
            f.invalid,
            f.custom_fields
        FROM flow f
        WHERE f.event_name = 'checkout.order.placed'
    ");

    $flows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($flows as $flow) {
        echo "Flow ID: {$flow['flow_id']}\n";
        echo "Name: {$flow['name']}\n";
        echo "Active: " . ($flow['active'] ? 'Yes' : 'No') . "\n";
        echo "Event: {$flow['event_name']}\n";
        echo "Priority: {$flow['priority']}\n";
        echo "Invalid: " . ($flow['invalid'] ? 'Yes' : 'No') . "\n\n";

        // Check flow sequences
        echo "=== Flow Sequences ===\n";
        $seqStmt = $pdo->prepare("
            SELECT
                HEX(fs.id) as seq_id,
                fs.action_name,
                fs.position,
                fs.true_case,
                fs.config,
                fs.display_group
            FROM flow_sequence fs
            WHERE HEX(fs.flow_id) = ?
            ORDER BY fs.position
        ");
        $seqStmt->execute([$flow['flow_id']]);
        $sequences = $seqStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sequences as $seq) {
            echo "Seq ID: {$seq['seq_id']}\n";
            echo "Action: {$seq['action_name']}\n";
            echo "Position: {$seq['position']}\n";
            echo "Config: " . substr($seq['config'] ?? 'null', 0, 200) . "\n";
            echo "---\n";
        }
    }

    // Check if mail template exists
    echo "\n=== Mail Template Check ===\n";
    $stmt = $pdo->query("
        SELECT
            HEX(mt.id) as template_id,
            HEX(mt.mail_template_type_id) as type_id,
            mt.system_default,
            mtt.sender_name,
            mtt.subject,
            LENGTH(mtt.content_html) as html_length,
            LENGTH(mtt.content_plain) as plain_length
        FROM mail_template mt
        JOIN mail_template_translation mtt ON mt.id = mtt.mail_template_id
        WHERE HEX(mt.id) = '0191C12CD21173B28A740D7E69C8ED29'
    ");

    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($templates as $tpl) {
        echo "Template ID: {$tpl['template_id']}\n";
        echo "Type ID: {$tpl['type_id']}\n";
        echo "System Default: " . ($tpl['system_default'] ? 'Yes' : 'No') . "\n";
        echo "Sender: {$tpl['sender_name']}\n";
        echo "Subject: {$tpl['subject']}\n";
        echo "HTML Content Length: {$tpl['html_length']} bytes\n";
        echo "Plain Content Length: {$tpl['plain_length']} bytes\n";
    }

    // Check sales channel
    echo "\n=== Sales Channel Check ===\n";
    $stmt = $pdo->query("
        SELECT
            HEX(id) as sc_id,
            sct.name
        FROM sales_channel sc
        JOIN sales_channel_translation sct ON sc.id = sct.sales_channel_id
        WHERE sc.active = 1
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $sc) {
        echo "Sales Channel: {$sc['name']} ({$sc['sc_id']})\n";
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
