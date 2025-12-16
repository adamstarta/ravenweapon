<?php
/**
 * Make register page logo bigger
 */

$file = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/account/register/index.html.twig';
$content = file_get_contents($file);

// Make logo bigger - prominent size at top
$content = str_replace('height:80px;width:280px', 'height:50px;width:180px', $content);

file_put_contents($file, $content);
echo "Logo size increased\n";
