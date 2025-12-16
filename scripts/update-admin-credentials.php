<?php
/**
 * Update Shopware Admin Credentials
 */

$host = '127.0.0.1';
$dbname = 'shopware';
$username = 'root';
$password = 'root';

$newUsername = 'Micro the CEO';
$newPassword = '100%Ravenweapon...';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "=== Updating Shopware Admin Credentials ===\n\n";

    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

    // Update the admin user
    $stmt = $pdo->prepare("
        UPDATE user
        SET username = ?,
            password = ?,
            updated_at = NOW()
        WHERE username = 'admin' OR username = 'Mirco the CEO' OR username = 'Micro the CEO'
    ");
    $stmt->execute([$newUsername, $hashedPassword]);

    if ($stmt->rowCount() > 0) {
        echo "✅ Admin credentials updated!\n\n";
        echo "Username: $newUsername\n";
        echo "Password: $newPassword\n";
    } else {
        echo "No user found to update.\n";
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
