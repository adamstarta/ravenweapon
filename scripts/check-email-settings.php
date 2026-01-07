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
    'mysql:host=' . $parsedUrl['host'] . ';dbname=' . ltrim($parsedUrl['path'], '/'),
    $parsedUrl['user'],
    $parsedUrl['pass']
);

echo "=== CURRENT EMAIL SETTINGS ===\n\n";

$stmt = $pdo->query("
    SELECT configuration_key, JSON_UNQUOTE(configuration_value) as val
    FROM system_config
    WHERE configuration_key IN (
        'core.basicInformation.email',
        'core.mailerSettings.senderAddress',
        'core.mailerSettings.username',
        'core.mailerSettings.host'
    )
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $key = str_replace(['core.basicInformation.', 'core.mailerSettings.'], '', $row['configuration_key']);
    echo "$key: " . $row['val'] . "\n";
}
