<?php
/**
 * Fix Order Confirmation Email - Logo URL
 */

$host = getenv('DATABASE_HOST') ?: '127.0.0.1';
$dbname = getenv('DATABASE_NAME') ?: 'shopware';
$user = getenv('DATABASE_USER') ?: 'root';
$pass = getenv('DATABASE_PASSWORD') ?: 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to database\n";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// Get current template
$stmt = $pdo->query("SELECT id FROM mail_template_type WHERE technical_name = 'order_confirmation_mail'");
$typeId = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT id FROM mail_template WHERE mail_template_type_id = ?");
$stmt->execute([$typeId]);
$templateId = $stmt->fetchColumn();

// Get current HTML
$stmt = $pdo->prepare("SELECT content_html FROM mail_template_translation WHERE mail_template_id = ? LIMIT 1");
$stmt->execute([$templateId]);
$currentHtml = $stmt->fetchColumn();

// Fix the logo URL - use the theme asset URL
$oldLogoPattern = 'https://ortak.ch/media/a9/c5/df/1734437626/raven-logo.png';
$newLogoUrl = 'https://ortak.ch/bundles/raventheme/assets/raven-logo.png';

// Also handle any other broken logo patterns
$patterns = [
    'https://ortak.ch/media/a9/c5/df/1734437626/raven-logo.png',
    'https://ortak.ch/raven-logo.png',
    '/media/a9/c5/df/1734437626/raven-logo.png',
];

$updatedHtml = $currentHtml;
foreach ($patterns as $pattern) {
    $updatedHtml = str_replace($pattern, $newLogoUrl, $updatedHtml);
}

if ($updatedHtml !== $currentHtml) {
    $stmt = $pdo->prepare("UPDATE mail_template_translation SET content_html = ?, updated_at = NOW() WHERE mail_template_id = ?");
    $result = $stmt->execute([$updatedHtml, $templateId]);
    echo "✓ Fixed logo URL: $newLogoUrl\n";
} else {
    echo "Logo URL already correct or not found in template\n";

    // Check if logo is in template at all
    if (strpos($currentHtml, 'raven-logo') === false && strpos($currentHtml, 'logo') === false) {
        echo "Warning: No logo found in template, may need manual check\n";
    }
}

echo "Done!\n";
