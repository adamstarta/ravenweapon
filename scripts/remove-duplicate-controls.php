<?php
/**
 * Remove duplicate pagination and sorting from category pages
 * - Hide top pagination row (keep only bottom)
 * - Hide duplicate Name A-Z sorting dropdown
 */

$file = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/element/cms-element-product-listing.html.twig';
$content = file_get_contents($file);

// Find the pagination row div and hide it completely
// Change display from flex to none
$old = '.raven-pagination-row { display: flex !important; justify-content: flex-end !important; }';
$new = '.raven-pagination-row { display: none !important; }';
$content = str_replace($old, $new, $content);

// Also hide the entire pagination row element
$old2 = '<div class="raven-pagination-row" id="raven-pagination-row">';
$new2 = '<div class="raven-pagination-row" id="raven-pagination-row" style="display:none !important;">';
$content = str_replace($old2, $new2, $content);

file_put_contents($file, $content);
echo "Removed duplicate pagination row from category pages\n";
