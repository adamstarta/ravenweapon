<?php
/**
 * Deploy script to fix product breadcrumb - adds ProductDetailSubscriber
 *
 * Files to deploy:
 * 1. Subscriber/ProductDetailSubscriber.php - new subscriber class
 * 2. Resources/config/services.xml - updated services configuration
 */

$serverHost = 'ortak.ch';
$serverUser = 'root';
$serverBasePath = '/var/www/html/custom/plugins/RavenTheme/src';

$localBasePath = 'C:\\Users\\alama\\Desktop\\NIKOLA WORK\\ravenweapon\\shopware-theme\\RavenTheme\\src';

echo "=== BREADCRUMB FIX DEPLOYMENT ===\n\n";

echo "This deployment adds a ProductDetailSubscriber that loads the seoCategory\n";
echo "for products, enabling proper breadcrumb display (Home / Zielfernrohre / Product).\n\n";

echo "Files to deploy:\n";
echo "1. NEW: Subscriber/ProductDetailSubscriber.php\n";
echo "2. UPDATE: Resources/config/services.xml\n\n";

// Check if files exist locally
$subscriberFile = $localBasePath . '\\Subscriber\\ProductDetailSubscriber.php';
$servicesFile = $localBasePath . '\\Resources\\config\\services.xml';

echo "Local files check:\n";
echo "  Subscriber: " . (file_exists($subscriberFile) ? "EXISTS" : "MISSING") . "\n";
echo "  Services: " . (file_exists($servicesFile) ? "EXISTS" : "MISSING") . "\n\n";

echo "=== DEPLOYMENT COMMANDS ===\n\n";

echo "Step 1: Create Subscriber folder on server:\n";
echo "ssh {$serverUser}@{$serverHost} \"mkdir -p {$serverBasePath}/Subscriber\"\n\n";

echo "Step 2: Copy files to server:\n";
echo "scp \"{$subscriberFile}\" {$serverUser}@{$serverHost}:{$serverBasePath}/Subscriber/\n";
echo "scp \"{$servicesFile}\" {$serverUser}@{$serverHost}:{$serverBasePath}/Resources/config/\n\n";

echo "Step 3: Clear cache and rebuild on server:\n";
echo "ssh {$serverUser}@{$serverHost} \"cd /var/www/html && bin/console cache:clear && bin/console theme:compile\"\n\n";

echo "=== COMBINED COMMAND ===\n\n";
echo "Run this single command to deploy everything:\n\n";

$cmd = "ssh {$serverUser}@{$serverHost} \"mkdir -p {$serverBasePath}/Subscriber\" && ";
$cmd .= "scp \"{$subscriberFile}\" {$serverUser}@{$serverHost}:{$serverBasePath}/Subscriber/ && ";
$cmd .= "scp \"{$servicesFile}\" {$serverUser}@{$serverHost}:{$serverBasePath}/Resources/config/ && ";
$cmd .= "ssh {$serverUser}@{$serverHost} \"cd /var/www/html && bin/console cache:clear && bin/console theme:compile\"";

echo $cmd . "\n";
