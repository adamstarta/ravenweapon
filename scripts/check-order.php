<?php
// Check order details
$orderId = '019b32201b477024b4f094137043219c';

$pdo = new PDO('mysql:host=localhost;dbname=shopware', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get order info
$stmt = $pdo->prepare("
    SELECT
        LOWER(HEX(o.id)) as order_id,
        o.order_number,
        o.order_date_time,
        ROUND(o.amount_total, 2) as total,
        o.currency_factor,
        os.technical_name as order_status,
        ps.technical_name as payment_status,
        ds.technical_name as delivery_status,
        c.first_name,
        c.last_name,
        c.email
    FROM `order` o
    LEFT JOIN order_customer c ON o.id = c.order_id
    LEFT JOIN state_machine_state os ON o.state_id = os.id
    LEFT JOIN state_machine_state ps ON o.id = (SELECT order_id FROM order_transaction WHERE state_id = ps.id LIMIT 1)
    LEFT JOIN state_machine_state ds ON o.id = (SELECT order_id FROM order_delivery WHERE state_id = ds.id LIMIT 1)
    WHERE LOWER(HEX(o.id)) = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if ($order) {
    echo "=== ORDER FOUND ===\n";
    foreach ($order as $key => $value) {
        echo "$key: $value\n";
    }

    // Get line items
    echo "\n=== LINE ITEMS ===\n";
    $stmt2 = $pdo->prepare("
        SELECT
            oli.label,
            oli.quantity,
            ROUND(oli.unit_price, 2) as unit_price,
            ROUND(oli.total_price, 2) as total_price,
            oli.payload
        FROM order_line_item oli
        WHERE LOWER(HEX(oli.order_id)) = ?
    ");
    $stmt2->execute([$orderId]);
    $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        echo "- {$item['label']} x{$item['quantity']} @ CHF {$item['unit_price']} = CHF {$item['total_price']}\n";
    }

    // Get order transactions
    echo "\n=== TRANSACTIONS ===\n";
    $stmt3 = $pdo->prepare("
        SELECT
            LOWER(HEX(ot.id)) as transaction_id,
            sms.technical_name as status,
            pm.name as payment_method
        FROM order_transaction ot
        LEFT JOIN state_machine_state sms ON ot.state_id = sms.id
        LEFT JOIN payment_method pm ON ot.payment_method_id = pm.id
        WHERE LOWER(HEX(ot.order_id)) = ?
    ");
    $stmt3->execute([$orderId]);
    $transactions = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    foreach ($transactions as $tx) {
        echo "Transaction: {$tx['transaction_id']}\n";
        echo "  Status: {$tx['status']}\n";
        echo "  Payment: {$tx['payment_method']}\n";
    }

} else {
    echo "Order not found with ID: $orderId\n";

    // List recent orders
    echo "\n=== RECENT ORDERS ===\n";
    $stmt = $pdo->query("SELECT LOWER(HEX(id)) as id, order_number, order_date_time FROM `order` ORDER BY created_at DESC LIMIT 10");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($orders as $o) {
        echo "{$o['id']} - {$o['order_number']} - {$o['order_date_time']}\n";
    }
}
