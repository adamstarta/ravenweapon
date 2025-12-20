<?php
/**
 * Fix SEO URLs to Lowercase
 *
 * This script updates all category SEO URLs in the database to lowercase.
 * Run this script on the production server inside the Docker container.
 *
 * Usage:
 * docker exec shopware-chf php /var/www/html/custom/plugins/scripts/fix-seo-urls-lowercase.php
 *
 * Or copy to container and run:
 * docker cp scripts/fix-seo-urls-lowercase.php shopware-chf:/tmp/
 * docker exec shopware-chf php /tmp/fix-seo-urls-lowercase.php
 */

// For running inside Shopware container
if (file_exists('/var/www/html/vendor/autoload.php')) {
    require_once '/var/www/html/vendor/autoload.php';
}

// Database connection settings
$host = getenv('DATABASE_HOST') ?: 'localhost';
$dbname = getenv('DATABASE_NAME') ?: 'shopware';
$user = getenv('DATABASE_USER') ?: 'shopware';
$password = getenv('DATABASE_PASSWORD') ?: 'shopware';

// Try to read from .env if available
$envFile = '/var/www/html/.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    if (preg_match('/DATABASE_URL=mysql:\/\/([^:]+):([^@]+)@([^:\/]+)(?::(\d+))?\/(\w+)/', $envContent, $matches)) {
        $user = $matches[1];
        $password = $matches[2];
        $host = $matches[3];
        $dbname = $matches[5];
    }
}

echo "===========================================\n";
echo "SEO URL Lowercase Fix Script\n";
echo "===========================================\n\n";

// Try multiple connection methods
$connectionMethods = [
    "mysql:host=127.0.0.1;port=3306;dbname=$dbname;charset=utf8mb4",
    "mysql:host=$host;port=3306;dbname=$dbname;charset=utf8mb4",
    "mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=$dbname;charset=utf8mb4",
];

$pdo = null;
foreach ($connectionMethods as $dsn) {
    try {
        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Connected to database: $dbname (DSN: $dsn)\n\n";
        break;
    } catch (PDOException $e) {
        echo "Connection attempt failed: " . $e->getMessage() . "\n";
        continue;
    }
}

if ($pdo === null) {
    die("All database connection attempts failed.\n");
}

// Get current SEO URLs that have uppercase characters
// Compare binary to find case differences
$query = "
    SELECT id, seo_path_info, is_canonical, is_deleted
    FROM seo_url
    WHERE BINARY seo_path_info <> BINARY LOWER(seo_path_info)
    AND is_deleted = 0
    ORDER BY seo_path_info
";

$stmt = $pdo->query($query);
$urls = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($urls) . " SEO URLs with uppercase characters\n\n";

if (count($urls) === 0) {
    echo "All SEO URLs are already lowercase. Nothing to do.\n";
    exit(0);
}

// Show what will be changed
echo "URLs to be changed:\n";
echo str_repeat("-", 80) . "\n";
foreach ($urls as $url) {
    $oldPath = $url['seo_path_info'];
    $newPath = strtolower($oldPath);
    $canonical = $url['is_canonical'] ? '[CANONICAL]' : '';
    echo sprintf("%-50s -> %-30s %s\n", $oldPath, $newPath, $canonical);
}
echo str_repeat("-", 80) . "\n\n";

// Ask for confirmation
echo "Do you want to proceed with updating these URLs? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) !== 'yes') {
    echo "Aborted.\n";
    exit(0);
}

echo "\nUpdating SEO URLs...\n";

$updated = 0;
$errors = 0;

foreach ($urls as $url) {
    $id = $url['id'];
    $oldPath = $url['seo_path_info'];
    $newPath = strtolower($oldPath);

    // Check if lowercase version already exists
    $checkStmt = $pdo->prepare("SELECT id FROM seo_url WHERE seo_path_info = ? AND id != ? AND is_deleted = 0");
    $checkStmt->execute([$newPath, $id]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        // Lowercase version exists, mark this one as deleted
        $updateStmt = $pdo->prepare("UPDATE seo_url SET is_deleted = 1 WHERE id = ?");
        $updateStmt->execute([$id]);
        echo "  DELETED (duplicate): $oldPath\n";
    } else {
        // Update to lowercase
        $updateStmt = $pdo->prepare("UPDATE seo_url SET seo_path_info = ? WHERE id = ?");
        try {
            $updateStmt->execute([$newPath, $id]);
            echo "  UPDATED: $oldPath -> $newPath\n";
            $updated++;
        } catch (PDOException $e) {
            echo "  ERROR: $oldPath - " . $e->getMessage() . "\n";
            $errors++;
        }
    }
}

echo "\n";
echo "===========================================\n";
echo "Summary:\n";
echo "  - Updated: $updated URLs\n";
echo "  - Errors: $errors\n";
echo "===========================================\n\n";

echo "IMPORTANT: Don't forget to clear the cache:\n";
echo "  docker exec shopware-chf bin/console cache:clear\n";
echo "\n";
