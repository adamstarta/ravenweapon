<?php
/**
 * Move navbar down - add margin-top to create space between logo row and nav row
 */

$file = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig';
$content = file_get_contents($file);

// Remove the previous padding change first
$content = str_replace(
    '<div class="pb-1" style="padding-right: 80px;">',
    '<div class="pb-1">',
    $content
);

// Also change gap back to gap-8
$content = str_replace(
    'id="raven-main-nav" class="flex items-center justify-center gap-5"',
    'id="raven-main-nav" class="flex items-center justify-center gap-8"',
    $content
);

// Add margin-top to move nav down
$content = str_replace(
    '{# Navigation Row - Categories with Dropdowns #}
            <div class="pb-1">',
    '{# Navigation Row - Categories with Dropdowns #}
            <div class="pb-1" style="margin-top: 12px;">',
    $content
);

file_put_contents($file, $content);
echo "Navbar moved down with margin-top: 12px\n";
