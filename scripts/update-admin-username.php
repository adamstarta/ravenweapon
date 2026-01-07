<?php
/**
 * Update Shopware admin username
 * Change "Micro the CEO" to "mirco"
 */

require_once '/var/www/html/vendor/autoload.php';

$envFile = '/var/www/html/.env';
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
        putenv(trim($line));
    }
}

$databaseUrl = getenv('DATABASE_URL');
$parsedUrl = parse_url($databaseUrl);
$pdo = new PDO(
    'mysql:host=' . $parsedUrl['host'] . ';dbname=' . ltrim($parsedUrl['path'], '/') . ';charset=utf8mb4',
    $parsedUrl['user'],
    $parsedUrl['pass']
);

// First, let's see all admin users
echo "=== Current Admin Users ===\n";
$stmt = $pdo->query('SELECT HEX(id) as id, username, first_name, last_name, email, active FROM user');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $user) {
    echo "ID: {$user['id']}\n";
    echo "Username: {$user['username']}\n";
    echo "Name: {$user['first_name']} {$user['last_name']}\n";
    echo "Email: {$user['email']}\n";
    echo "Active: " . ($user['active'] ? 'Yes' : 'No') . "\n";
    echo "---\n";
}

// Update the username
$oldUsername = 'Micro the CEO';
$newUsername = 'mirco';

echo "\n=== Updating Username ===\n";
echo "Old: $oldUsername\n";
echo "New: $newUsername\n";

$stmt = $pdo->prepare('UPDATE user SET username = ? WHERE username = ?');
$result = $stmt->execute([$newUsername, $oldUsername]);

if ($stmt->rowCount() > 0) {
    echo "\nSuccess! Username updated from '$oldUsername' to '$newUsername'\n";
} else {
    echo "\nNo user found with username '$oldUsername'\n";
    echo "Checking if '$newUsername' already exists...\n";

    $stmt = $pdo->prepare('SELECT username FROM user WHERE username = ?');
    $stmt->execute([$newUsername]);
    if ($stmt->fetch()) {
        echo "Username '$newUsername' already exists in the database.\n";
    }
}

// Show updated users
echo "\n=== Updated Admin Users ===\n";
$stmt = $pdo->query('SELECT HEX(id) as id, username, first_name, last_name, email, active FROM user');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $user) {
    echo "Username: {$user['username']} | Name: {$user['first_name']} {$user['last_name']} | Email: {$user['email']}\n";
}
