<?php
/**
 * Fix navbar spacing v2 - add more padding to keep nav items away from cart icon
 */

$file = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig';
$content = file_get_contents($file);

// Update the padding - increase to 120px on right side
$content = str_replace(
    '<div class="pb-1" style="padding-right: 80px;">',
    '<div class="pb-1" style="padding-left: 100px; padding-right: 150px;">',
    $content
);

file_put_contents($file, $content);
echo "Navbar padding increased to 150px right, 100px left\n";
