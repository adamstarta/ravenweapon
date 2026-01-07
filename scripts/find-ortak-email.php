<?php
/**
 * Script to find all occurrences of 'ortak.ch' email in Shopware database
 * Run this on the server inside the Shopware container
 *
 * Usage: docker exec shopware-chf php /var/www/html/custom/plugins/find-ortak-email.php
 */

require_once '/var/www/html/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

// Load environment variables
$envFile = '/var/www/html/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            putenv(trim($line));
        }
    }
}

// Parse DATABASE_URL
$databaseUrl = getenv('DATABASE_URL');
if (!$databaseUrl) {
    die("ERROR: DATABASE_URL not found in environment\n");
}

// Parse the URL
$parsedUrl = parse_url($databaseUrl);
$dbHost = $parsedUrl['host'] ?? 'localhost';
$dbPort = $parsedUrl['port'] ?? 3306;
$dbUser = $parsedUrl['user'] ?? 'root';
$dbPass = $parsedUrl['pass'] ?? '';
$dbName = ltrim($parsedUrl['path'] ?? '', '/');

echo "===========================================\n";
echo "  SEARCHING FOR 'ortak.ch' IN DATABASE\n";
echo "===========================================\n\n";

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $searchTerm = '%ortak.ch%';
    $foundItems = [];

    // 1. Check system_config table (most common place for email settings)
    echo "1. Checking system_config table...\n";
    echo "   --------------------------------\n";
    $stmt = $pdo->prepare("
        SELECT
            HEX(id) as id,
            configuration_key,
            JSON_UNQUOTE(configuration_value) as configuration_value,
            HEX(sales_channel_id) as sales_channel_id
        FROM system_config
        WHERE JSON_UNQUOTE(configuration_value) LIKE ?
           OR configuration_key LIKE '%mail%'
           OR configuration_key LIKE '%email%'
           OR configuration_key LIKE '%sender%'
    ");
    $stmt->execute([$searchTerm]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        $value = $row['configuration_value'];
        if (stripos($value, 'ortak.ch') !== false) {
            echo "   [FOUND] Key: {$row['configuration_key']}\n";
            echo "           Value: $value\n";
            echo "           Sales Channel: " . ($row['sales_channel_id'] ?: 'Global') . "\n\n";
            $foundItems[] = [
                'table' => 'system_config',
                'key' => $row['configuration_key'],
                'value' => $value,
                'id' => $row['id']
            ];
        }
    }

    // 2. Check mail_template_type table
    echo "2. Checking mail_template_type table...\n";
    echo "   ------------------------------------\n";
    $stmt = $pdo->prepare("
        SELECT HEX(id) as id, technical_name, available_entities
        FROM mail_template_type
    ");
    $stmt->execute();
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Found " . count($templates) . " mail template types\n\n";

    // 3. Check mail_template table for any hardcoded emails
    echo "3. Checking mail_template content...\n";
    echo "   ----------------------------------\n";
    $stmt = $pdo->prepare("
        SELECT
            HEX(mt.id) as id,
            mtt.technical_name,
            mt.system_default
        FROM mail_template mt
        LEFT JOIN mail_template_type mtt ON mt.mail_template_type_id = mtt.id
    ");
    $stmt->execute();
    $mailTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check translations for email content
    $stmt = $pdo->prepare("
        SELECT
            HEX(mail_template_id) as template_id,
            sender_name,
            subject,
            content_html,
            content_plain
        FROM mail_template_translation
        WHERE sender_name LIKE ?
           OR content_html LIKE ?
           OR content_plain LIKE ?
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $translations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($translations as $row) {
        echo "   [FOUND] Template ID: {$row['template_id']}\n";
        if (stripos($row['sender_name'] ?? '', 'ortak.ch') !== false) {
            echo "           Sender Name: {$row['sender_name']}\n";
        }
        if (stripos($row['content_html'] ?? '', 'ortak.ch') !== false) {
            echo "           Found in HTML content\n";
        }
        if (stripos($row['content_plain'] ?? '', 'ortak.ch') !== false) {
            echo "           Found in Plain text content\n";
        }
        echo "\n";
        $foundItems[] = [
            'table' => 'mail_template_translation',
            'id' => $row['template_id']
        ];
    }

    // 4. Check sales_channel for email settings
    echo "4. Checking sales_channel settings...\n";
    echo "   -----------------------------------\n";
    $stmt = $pdo->prepare("
        SELECT
            HEX(sc.id) as id,
            sct.name as channel_name,
            sc.configuration
        FROM sales_channel sc
        LEFT JOIN sales_channel_translation sct ON sc.id = sct.sales_channel_id
    ");
    $stmt->execute();
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($channels as $row) {
        $config = $row['configuration'];
        if ($config && stripos($config, 'ortak.ch') !== false) {
            echo "   [FOUND] Sales Channel: {$row['channel_name']}\n";
            echo "           ID: {$row['id']}\n\n";
            $foundItems[] = [
                'table' => 'sales_channel',
                'name' => $row['channel_name'],
                'id' => $row['id']
            ];
        }
    }

    // 5. Check .env file for mail configuration
    echo "5. Checking .env file...\n";
    echo "   ----------------------\n";
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        if (stripos($envContent, 'ortak.ch') !== false) {
            echo "   [FOUND] ortak.ch found in .env file\n";
            // Find the specific line
            $lines = explode("\n", $envContent);
            foreach ($lines as $line) {
                if (stripos($line, 'ortak.ch') !== false) {
                    echo "           Line: $line\n";
                }
            }
            $foundItems[] = ['table' => '.env file'];
        } else {
            echo "   Not found in .env\n";
        }
    }
    echo "\n";

    // 6. Search ALL tables for ortak.ch (comprehensive search)
    echo "6. Comprehensive search in all text columns...\n";
    echo "   --------------------------------------------\n";

    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // Get text columns
        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND DATA_TYPE IN ('varchar', 'text', 'longtext', 'mediumtext', 'json')
        ");
        $stmt->execute([$dbName, $table]);
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($columns)) continue;

        // Build search query
        $conditions = [];
        foreach ($columns as $col) {
            $conditions[] = "`$col` LIKE ?";
        }

        $sql = "SELECT * FROM `$table` WHERE " . implode(' OR ', $conditions) . " LIMIT 5";

        try {
            $stmt = $pdo->prepare($sql);
            $params = array_fill(0, count($columns), $searchTerm);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($results)) {
                echo "   [FOUND] Table: $table (" . count($results) . " rows)\n";
                foreach ($results as $row) {
                    foreach ($row as $col => $val) {
                        if (is_string($val) && stripos($val, 'ortak.ch') !== false) {
                            $displayVal = strlen($val) > 100 ? substr($val, 0, 100) . '...' : $val;
                            echo "           Column '$col': $displayVal\n";
                        }
                    }
                }
                echo "\n";
            }
        } catch (Exception $e) {
            // Skip tables with errors
        }
    }

    // Summary
    echo "\n===========================================\n";
    echo "  SUMMARY\n";
    echo "===========================================\n";

    if (empty($foundItems)) {
        echo "No occurrences of 'ortak.ch' found in database.\n";
        echo "The email might be configured in:\n";
        echo "- Server mail transport (SMTP settings)\n";
        echo "- External mail service\n";
    } else {
        echo "Found " . count($foundItems) . " location(s) with 'ortak.ch'\n\n";
        echo "TO FIX: Go to Shopware Admin:\n";
        echo "- Settings → Basic Information → Email addresses\n";
        echo "- Settings → Email → Sender email\n";
        echo "- Sales Channels → [Channel] → Email settings\n";
    }

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";
