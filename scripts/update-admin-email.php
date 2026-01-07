<?php
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

$stmt = $pdo->prepare('UPDATE user SET email = ? WHERE username = ?');
$stmt->execute(['info@ravenweapon.ch', 'mirco']);

$stmt = $pdo->query("SELECT username, first_name, email FROM user WHERE username='mirco'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Username: {$row['username']}\n";
echo "Name: {$row['first_name']}\n";
echo "Email: {$row['email']}\n";
