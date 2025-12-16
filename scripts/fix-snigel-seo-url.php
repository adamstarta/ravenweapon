<?php
/**
 * Fix Snigel category SEO URL to free up /snigel for the manufacturer page
 *
 * This script changes the Snigel category's SEO URL from "Snigel/" to "snigel-kategorie/"
 * so that the /snigel URL can be used by the ManufacturerPageController
 */

// Load .env to get database credentials
$envFile = '/var/www/html/.env';
$env = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
}

// Parse DATABASE_URL
$dbUrl = $env['DATABASE_URL'] ?? getenv('DATABASE_URL');
$parsed = parse_url($dbUrl);
$host = $parsed['host'] ?? 'localhost';
$port = $parsed['port'] ?? 3306;
$user = $parsed['user'] ?? 'root';
$pass = $parsed['pass'] ?? '';
$dbname = ltrim($parsed['path'] ?? '', '/');

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get the Snigel category ID
$categoryId = '019b0857613474e6a799cfa07d143c76';

// Find the SEO URL for Snigel category
$stmt = $pdo->prepare("SELECT HEX(id) as id, seo_path_info, path_info, HEX(sales_channel_id) as sales_channel_id, is_canonical
     FROM seo_url
     WHERE path_info LIKE :pathInfo");
$stmt->execute(['pathInfo' => '%' . $categoryId . '%']);
$seoUrls = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found SEO URLs for Snigel category:\n";
foreach ($seoUrls as $url) {
    echo "  ID: {$url['id']}, Path: {$url['seo_path_info']}, Canonical: {$url['is_canonical']}\n";
}

// Update the SEO URL from "Snigel/" to "snigel-kategorie/"
$pdo->exec("UPDATE seo_url
     SET seo_path_info = REPLACE(seo_path_info, 'Snigel/', 'snigel-kategorie/')
     WHERE seo_path_info LIKE 'Snigel/%' OR seo_path_info = 'Snigel/'");

echo "\nUpdated Snigel category SEO URLs to snigel-kategorie/\n";

// Verify the change
$stmt = $pdo->prepare("SELECT HEX(id) as id, seo_path_info, path_info, HEX(sales_channel_id) as sales_channel_id, is_canonical
     FROM seo_url
     WHERE path_info LIKE :pathInfo");
$stmt->execute(['pathInfo' => '%' . $categoryId . '%']);
$seoUrlsAfter = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\nSEO URLs after update:\n";
foreach ($seoUrlsAfter as $url) {
    echo "  ID: {$url['id']}, Path: {$url['seo_path_info']}, Canonical: {$url['is_canonical']}\n";
}

echo "\nDone! Now /snigel should route to the manufacturer page.\n";
echo "Clear cache with: bin/console cache:clear\n";
