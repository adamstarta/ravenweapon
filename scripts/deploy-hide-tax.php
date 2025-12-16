<?php
/**
 * Deploy script to update theme files on server - hide tax breakdown
 *
 * Files to update:
 * 1. page/checkout/confirm/index.html.twig - checkout page
 * 2. page/checkout/register/index.html.twig - register/guest checkout page
 * 3. page/checkout/finish/index.html.twig - order confirmation page
 */

// Server connection details
$serverHost = 'ortak.ch';
$serverUser = 'root';
$serverPath = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront';

echo "=== HIDE TAX BREAKDOWN DEPLOYMENT ===\n\n";

echo "Files to update on server:\n";
echo "1. {$serverPath}/page/checkout/confirm/index.html.twig\n";
echo "2. {$serverPath}/page/checkout/register/index.html.twig\n";
echo "3. {$serverPath}/page/checkout/finish/index.html.twig\n\n";

echo "Changes made:\n";
echo "- Removed 'inkl. 8.1% MwSt. CHF XXX.XX' line from checkout confirm\n";
echo "- Removed 'inkl. MwSt. CHF XXX.XX' line from checkout register\n";
echo "- Removed 'MwSt. 8.1% CHF XXX.XX' line from checkout finish\n\n";

echo "After deployment, run on server:\n";
echo "  cd /var/www/html && bin/console cache:clear\n";
echo "  cd /var/www/html && bin/console theme:compile\n\n";

echo "Local files location:\n";
$localBase = 'C:\\Users\\alama\\Desktop\\NIKOLA WORK\\ravenweapon\\shopware-theme\\RavenTheme\\src\\Resources\\views\\storefront';
echo "1. {$localBase}\\page\\checkout\\confirm\\index.html.twig\n";
echo "2. {$localBase}\\page\\checkout\\register\\index.html.twig\n";
echo "3. {$localBase}\\page\\checkout\\finish\\index.html.twig\n\n";

echo "To manually copy via SSH:\n";
echo "scp \"{$localBase}\\page\\checkout\\confirm\\index.html.twig\" root@ortak.ch:{$serverPath}/page/checkout/confirm/\n";
echo "scp \"{$localBase}\\page\\checkout\\register\\index.html.twig\" root@ortak.ch:{$serverPath}/page/checkout/register/\n";
echo "scp \"{$localBase}\\page\\checkout\\finish\\index.html.twig\" root@ortak.ch:{$serverPath}/page/checkout/finish/\n";
