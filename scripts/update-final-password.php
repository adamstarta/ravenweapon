<?php
/**
 * Update with Infomaniak generated device password
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

// Infomaniak generated device password
$newPassword = '%2h+MvmQMwS6jSs7';

$stmt = $pdo->prepare('UPDATE system_config SET configuration_value = ? WHERE configuration_key = ?');
$stmt->execute([json_encode(['_value' => $newPassword]), 'core.mailerSettings.password']);

echo "Password updated to Infomaniak device password!\n";
echo "Email: info@ravenweapon.ch\n";
echo "SMTP: mail.infomaniak.com:587\n";
