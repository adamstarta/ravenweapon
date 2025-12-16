<?php
/**
 * Deploy the updated product-detail template to fix breadcrumbs
 *
 * This template extracts the category from the URL path to show proper breadcrumbs
 * Example: Home / Zielfernrohre / Product Name
 */

$serverHost = 'ortak.ch';
$serverPath = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/product-detail/index.html.twig';
$localPath = 'C:\\Users\\alama\\Desktop\\NIKOLA WORK\\ravenweapon\\shopware-theme\\RavenTheme\\src\\Resources\\views\\storefront\\page\\product-detail\\index.html.twig';

echo "=== BREADCRUMB TEMPLATE DEPLOYMENT ===\n\n";

echo "This deployment updates the product detail template to show proper breadcrumbs.\n";
echo "The template now extracts the category from the URL path.\n\n";

echo "Example: URL /Waffenzubehoer/Zielfernrohre/thrive-3-12x44-zeroplex-...\n";
echo "         Shows: Home / Zielfernrohre / THRIVE 3-12X44 ZEROPLEX\n\n";

echo "=== FILE TO DEPLOY ===\n";
echo "Local:  {$localPath}\n";
echo "Server: {$serverPath}\n\n";

echo "=== DEPLOYMENT COMMAND ===\n";
echo "scp \"{$localPath}\" root@{$serverHost}:{$serverPath}\n\n";

echo "=== AFTER DEPLOYMENT ===\n";
echo "ssh root@{$serverHost} \"cd /var/www/html && bin/console cache:clear\"\n\n";

// Read the file content
$content = file_get_contents($localPath);
if ($content) {
    echo "=== FILE CONTENT PREVIEW (first 100 lines) ===\n";
    $lines = explode("\n", $content);
    $preview = array_slice($lines, 0, 100);
    echo implode("\n", $preview) . "\n...\n";
}
