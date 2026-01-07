<?php
/**
 * Update Shopware email settings from ortak.ch to ravenweapon.ch
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
echo "  UPDATING EMAIL SETTINGS\n";
echo "=============================================\n\n";

// New settings
$newEmail = 'nikola@ravenweapon.ch';
$newSmtpHost = 'mail.infomaniak.com';
$newSmtpPort = 587;
$newSmtpUsername = 'nikola@ravenweapon.ch';
$newSmtpPassword = 'C*%tE3kJ16SEp0qL';
$newEncryption = 'tls';

$updates = [
    'core.basicInformation.email' => json_encode(['_value' => $newEmail]),
    'core.mailerSettings.senderAddress' => json_encode(['_value' => $newEmail]),
    'core.mailerSettings.username' => json_encode(['_value' => $newSmtpUsername]),
    'core.mailerSettings.password' => json_encode(['_value' => $newSmtpPassword]),
    'core.mailerSettings.host' => json_encode(['_value' => $newSmtpHost]),
    'core.mailerSettings.port' => json_encode(['_value' => $newSmtpPort]),
    'core.mailerSettings.encryption' => json_encode(['_value' => $newEncryption]),
];

foreach ($updates as $key => $value) {
    $stmt = $pdo->prepare("
        UPDATE system_config
        SET configuration_value = ?
        WHERE configuration_key = ?
    ");
    $result = $stmt->execute([$value, $key]);

    if ($stmt->rowCount() > 0) {
        echo "[UPDATED] $key\n";
        echo "          => $value\n\n";
    } else {
        // Check if it exists
        $checkStmt = $pdo->prepare("SELECT id FROM system_config WHERE configuration_key = ?");
        $checkStmt->execute([$key]);
        if ($checkStmt->fetch()) {
            echo "[NO CHANGE] $key (already set)\n\n";
        } else {
            // Insert if doesn't exist
            $insertStmt = $pdo->prepare("
                INSERT INTO system_config (id, configuration_key, configuration_value, created_at)
                VALUES (UNHEX(REPLACE(UUID(), '-', '')), ?, ?, NOW())
            ");
            $insertStmt->execute([$key, $value]);
            echo "[INSERTED] $key\n";
            echo "           => $value\n\n";
        }
    }
}

echo "\n=============================================\n";
echo "  VERIFYING NEW SETTINGS\n";
echo "=============================================\n\n";

$stmt = $pdo->query("
    SELECT configuration_key, JSON_UNQUOTE(configuration_value) as val
    FROM system_config
    WHERE configuration_key LIKE 'core.mailerSettings%'
       OR configuration_key = 'core.basicInformation.email'
    ORDER BY configuration_key
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['configuration_key'] . "\n";
    echo "   => " . $row['val'] . "\n\n";
}

echo "\n[SUCCESS] Email settings updated!\n";
echo "Clear cache: bin/console cache:clear\n";
