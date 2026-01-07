<?php
/**
 * Fix password to 100%Ravenweapon...
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

// Password with THREE dots
$newPassword = '100%Ravenweapon...';

$stmt = $pdo->prepare('UPDATE system_config SET configuration_value = ? WHERE configuration_key = ?');
$stmt->execute([json_encode(['_value' => $newPassword]), 'core.mailerSettings.password']);

echo "Password updated to: $newPassword\n";
