<?php
/**
 * Fix register page logo position - align with back button at top
 */

$file = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/account/register/index.html.twig';
$content = file_get_contents($file);

// Update auth-logo CSS to position at top like the back button
$old = '.auth-logo {
    text-align: center;
    margin-bottom: 2.5rem;
}';

$new = '.auth-logo {
    position: absolute;
    top: 1.5rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10;
    text-align: center;
}';

$content = str_replace($old, $new, $content);

// Also add padding to auth-wrapper to account for logo at top
$old2 = '.auth-wrapper {
    width: 100%;
    max-width: 480px;
    position: relative;';

$new2 = '.auth-wrapper {
    width: 100%;
    max-width: 480px;
    position: relative;
    padding-top: 120px;';

$content = str_replace($old2, $new2, $content);

file_put_contents($file, $content);
echo "Logo positioned at top level with back button\n";
