<?php
/**
 * Fix Payment Method Account Holder
 * Change "Nikola Mitrovic" to "Raven Weapon AG" for Paid in Advance payment
 */

$host = '77.42.19.154';
$port = 3307;
$dbname = 'shopware';
$username = 'shopware';
$password = 'shopware';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "Connected to database\n\n";

    // Find the payment method "Paid in advance"
    $stmt = $pdo->query("
        SELECT
            pm.id,
            HEX(pm.id) as id_hex,
            pmt.name,
            pmt.description,
            pmt.custom_fields
        FROM payment_method pm
        JOIN payment_method_translation pmt ON pm.id = pmt.payment_method_id
        WHERE pmt.name LIKE '%advance%' OR pmt.name LIKE '%Vorkasse%' OR pmt.description LIKE '%Nikola%'
    ");

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "=== Payment Methods Found ===\n";
    foreach ($results as $row) {
        echo "ID: " . $row['id_hex'] . "\n";
        echo "Name: " . $row['name'] . "\n";
        echo "Description: " . substr($row['description'] ?? 'N/A', 0, 200) . "...\n";
        echo "Custom Fields: " . ($row['custom_fields'] ?? 'N/A') . "\n";
        echo "---\n";
    }

    // Search in description for Nikola Mitrovic
    $stmt = $pdo->query("
        SELECT
            HEX(payment_method_id) as id_hex,
            name,
            description
        FROM payment_method_translation
        WHERE description LIKE '%Nikola%' OR description LIKE '%Kontoinhaber%'
    ");

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\n=== Payment Methods with Account Info in Description ===\n";
    foreach ($results as $row) {
        echo "ID: " . $row['id_hex'] . "\n";
        echo "Name: " . $row['name'] . "\n";
        echo "Description:\n" . $row['description'] . "\n";
        echo "---\n";
    }

    // Now let's update the description
    echo "\n=== Updating Account Holder ===\n";

    $updateStmt = $pdo->prepare("
        UPDATE payment_method_translation
        SET description = REPLACE(description, 'Nikola Mitrovic', 'Raven Weapon AG'),
            updated_at = NOW()
        WHERE description LIKE '%Nikola Mitrovic%'
    ");

    $updateStmt->execute();
    $affected = $updateStmt->rowCount();

    echo "Updated $affected payment method translation(s)\n";

    // Verify the change
    $stmt = $pdo->query("
        SELECT
            HEX(payment_method_id) as id_hex,
            name,
            description
        FROM payment_method_translation
        WHERE description LIKE '%Kontoinhaber%'
    ");

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\n=== Verification - Updated Description ===\n";
    foreach ($results as $row) {
        echo "ID: " . $row['id_hex'] . "\n";
        echo "Name: " . $row['name'] . "\n";
        echo "Description:\n" . $row['description'] . "\n";
        echo "---\n";
    }

    echo "\n✅ Done! Account holder changed from 'Nikola Mitrovic' to 'Raven Weapon AG'\n";
    echo "Clear cache on server: docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
