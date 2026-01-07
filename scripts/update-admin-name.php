<?php
/**
 * Update Shopware admin first name to Mirco
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

// Update name to just Mirco (clear last name)
$stmt = $pdo->prepare('UPDATE user SET first_name = ?, last_name = ? WHERE username = ?');
$stmt->execute(['Mirco', '', 'mirco']);

echo "Updated " . $stmt->rowCount() . " user(s)\n";
echo "First name set to: Mirco\n\n";

// Show updated user
$stmt = $pdo->query('SELECT username, first_name, last_name, email FROM user');
echo "=== Updated Admin User ===\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Username: {$row['username']}\n";
    echo "Name: {$row['first_name']} {$row['last_name']}\n";
    echo "Email: {$row['email']}\n";
}
