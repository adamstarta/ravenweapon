<?php
/**
 * Fix navbar spacing - reduce gap between nav items so Snigel doesn't overlap with cart icon
 */

$file = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig';
$content = file_get_contents($file);

// Change gap-8 to gap-5 (smaller spacing between nav items)
$content = str_replace(
    'id="raven-main-nav" class="flex items-center justify-center gap-8"',
    'id="raven-main-nav" class="flex items-center justify-center gap-5"',
    $content
);

// Also add some right margin to the nav row to prevent overlap with icons
$content = str_replace(
    '{# Navigation Row - Categories with Dropdowns #}
            <div class="pb-1">',
    '{# Navigation Row - Categories with Dropdowns #}
            <div class="pb-1" style="padding-right: 80px;">',
    $content
);

file_put_contents($file, $content);
echo "Navbar spacing adjusted - gap reduced and padding added\n";
