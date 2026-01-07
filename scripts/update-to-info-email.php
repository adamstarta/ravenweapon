<?php
/**
 * Update Shopware email settings to info@ravenweapon.ch
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

echo "=============================================\n";
echo "  UPDATING TO info@ravenweapon.ch\n";
echo "=============================================\n\n";

$newEmail = 'info@ravenweapon.ch';
$newPassword = '100%Ravenweapon.';

$updates = [
    'core.basicInformation.email' => json_encode(['_value' => $newEmail]),
    'core.mailerSettings.senderAddress' => json_encode(['_value' => $newEmail]),
    'core.mailerSettings.username' => json_encode(['_value' => $newEmail]),
    'core.mailerSettings.password' => json_encode(['_value' => $newPassword]),
];

foreach ($updates as $key => $value) {
    $stmt = $pdo->prepare('UPDATE system_config SET configuration_value = ? WHERE configuration_key = ?');
    $stmt->execute([$value, $key]);
    echo "[UPDATED] $key\n";
}

echo "\n[SUCCESS] Settings updated to info@ravenweapon.ch\n";
