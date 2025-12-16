<?php
$files = [
    "/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig",
    "/var/www/html/custom/plugins/RavenTheme/src/Resources/views/views/storefront/layout/header/header.html.twig",
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "Not found: $file\n";
        continue;
    }

    $content = file_get_contents($file);

    // Revert ALL header logos back to original SVG style with sprite
    $content = str_replace('raven-logo.png', 'logo.svg', $content);
    $content = str_replace('background-size:contain', 'background-size:100% 455%', $content);

    // Fix sizes back to original
    $content = str_replace('height:55px;width:240px', 'height:36px;width:160px', $content);
    $content = str_replace('height:50px;width:220px', 'height:32px;width:140px', $content);
    $content = str_replace('height:90px;width:380px', 'height:32px;width:140px', $content);

    file_put_contents($file, $content);
    echo "Reverted: $file\n";
}

echo "\nHeader logos reverted to original SVG!\n";
