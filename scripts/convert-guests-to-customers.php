<?php
/**
 * Convert guest accounts to real customer accounts
 * Sets guest=0 and assigns a temporary password
 */

// Database connection
$host = 'localhost';
$dbname = 'shopware';
$user = 'root';
$pass = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Generate password hash for temporary password: TempPass123!
    $tempPassword = 'TempPass123!';
    $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);

    echo "=== Converting Guest Accounts to Real Customers ===\n\n";
    echo "Temporary password for all converted accounts: $tempPassword\n\n";

    // Find all guest accounts
    $stmt = $pdo->query("SELECT LOWER(HEX(id)) as id, email, first_name, last_name FROM customer WHERE guest = 1");
    $guests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($guests)) {
        echo "No guest accounts found.\n";
        exit(0);
    }

    echo "Found " . count($guests) . " guest account(s):\n";
    foreach ($guests as $guest) {
        echo "  - {$guest['first_name']} {$guest['last_name']} ({$guest['email']})\n";
    }
    echo "\n";

    // Convert each guest to a real customer
    $updateStmt = $pdo->prepare("UPDATE customer SET guest = 0, password = ? WHERE LOWER(HEX(id)) = ?");

    $converted = 0;
    foreach ($guests as $guest) {
        $updateStmt->execute([$passwordHash, $guest['id']]);
        if ($updateStmt->rowCount() > 0) {
            echo "✓ Converted: {$guest['first_name']} {$guest['last_name']} ({$guest['email']})\n";
            $converted++;
        } else {
            echo "✗ Failed: {$guest['first_name']} {$guest['last_name']} ({$guest['email']})\n";
        }
    }

    echo "\n=== Summary ===\n";
    echo "Total converted: $converted / " . count($guests) . "\n";
    echo "\nAll converted accounts can now login with:\n";
    echo "  Password: $tempPassword\n";
    echo "\nIMPORTANT: Ask customers to change their password after first login!\n";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}
