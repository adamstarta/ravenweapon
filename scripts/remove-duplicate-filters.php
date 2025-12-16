<?php
/**
 * Remove duplicate filter controls from category pages:
 * 1. Top right "Farbe" and "Preis" dropdowns (Shopware default)
 * 2. Duplicate "Name A-Z" sorting dropdown
 */

$file = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/element/cms-element-product-listing.html.twig';
$content = file_get_contents($file);

// Find the style section and add CSS to hide duplicate elements
$hideCSS = '
/* HIDE DUPLICATE CONTROLS */
/* Hide Shopware default filter dropdowns at top right */
.cms-element-product-listing .filter-panel,
.cms-element-product-listing [data-listing-filter-panel],
.cms-element-product-listing .filter-container,
.cms-element-product-listing > .container > [role="listbox"],
.filter-multi-select-dropdown,
.cms-element-product-listing-wrapper > .container:first-child > .row:first-child [role="listbox"],
[aria-label="Produkte filtern"] {
    display: none !important;
}

/* Hide the duplicate sorting dropdown below Name A-Z button */
.cms-element-product-listing select[name="order"],
.cms-element-product-listing .sorting select,
.product-listing-sorting select,
select[class*="sorting"],
.cms-element-product-listing-wrapper select.sorting {
    display: none !important;
}
';

// Find where to insert - after opening <style> tag
if (strpos($content, '<style>') !== false) {
    $content = str_replace('<style>', '<style>' . $hideCSS, $content);
}

file_put_contents($file, $content);
echo "Added CSS to hide duplicate filter controls\n";
