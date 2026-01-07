<?php
/**
 * Find all email-related settings in Shopware
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
echo "  ALL EMAIL SETTINGS IN SHOPWARE\n";
echo "=============================================\n\n";

$stmt = $pdo->query("
    SELECT configuration_key, JSON_UNQUOTE(configuration_value) as val
    FROM system_config
    WHERE configuration_key LIKE '%mail%'
       OR configuration_key LIKE '%email%'
       OR configuration_key LIKE '%notification%'
       OR configuration_key LIKE '%recipient%'
       OR configuration_key LIKE '%contact%'
       OR configuration_key LIKE '%sender%'
       OR configuration_key LIKE '%basicInformation%'
    ORDER BY configuration_key
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $key = $row['configuration_key'];
    $val = $row['val'];

    // Highlight if contains email address
    if (strpos($val, '@') !== false) {
        echo "[EMAIL] ";
    } else {
        echo "        ";
    }

    echo "$key\n";
    echo "        => $val\n\n";
}

// Also check for order notification recipient
echo "\n=============================================\n";
echo "  CHECKING ORDER NOTIFICATION SETTINGS\n";
echo "=============================================\n\n";

$stmt = $pdo->query("
    SELECT configuration_key, JSON_UNQUOTE(configuration_value) as val
    FROM system_config
    WHERE configuration_key LIKE '%order%'
    ORDER BY configuration_key
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['configuration_key'] . "\n";
    echo "   => " . $row['val'] . "\n\n";
}
