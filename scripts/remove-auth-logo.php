<?php
/**
 * Remove logo from register and login pages
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

    // Remove the logo section - pattern 1 (outside wrapper)
    $content = preg_replace(
        '/<div class="auth-logo">\s*<a href="[^"]*">\s*<div class="logo-image"[^>]*><\/div>\s*<\/a>\s*<\/div>/s',
        '',
        $content
    );

    // Remove any standalone logo link
    $content = preg_replace(
        '/<a[^>]*>\s*<div class="logo-image"[^>]*><\/div>\s*<\/a>/s',
        '',
        $content
    );

    // Remove {# Logo Section #} comments
    $content = preg_replace('/\{#\s*Logo Section[^#]*#\}\s*/s', '', $content);

    file_put_contents($file, $content);
    echo "Removed logo from: " . basename($file) . "\n";
}

echo "\nLogos removed from auth pages!\n";
