<?php
/**
 * Make auth page logos bigger on register and login pages
 */

$files = [
    '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/account/register/index.html.twig',
    '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/account/login/index.html.twig'
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "Not found: $file\n";
        continue;
    }

    $content = file_get_contents($file);

    // Make logo bigger - from small to prominent size
    $content = str_replace('height:50px;width:180px', 'height:70px;width:250px', $content);
    $content = str_replace('height:80px;width:280px', 'height:70px;width:250px', $content);

    file_put_contents($file, $content);
    echo "Updated: " . basename($file) . "\n";
}

echo "\nLogos made bigger!\n";
