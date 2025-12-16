<?php
/**
 * Fix Swiss Rappen rounding (5 cent increments)
 * Formula: round(price * 20) / 20
 */

$pdo = new PDO("mysql:host=127.0.0.1;dbname=shopware", "root", "root");

$files = [
    "/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/component/product/card/box-standard.html.twig",
    "/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/navigation/index.html.twig",
    "/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/search/index.html.twig",
    "/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/manufacturer/index.html.twig",
    "/var/www/html/custom/plugins/RavenTheme/src/Resources/views/views/storefront/component/product/card/box-standard.html.twig",
    "/var/www/html/custom/plugins/RavenTheme/src/Resources/views/views/storefront/page/navigation/index.html.twig",
    "/var/www/html/custom/plugins/RavenTheme/src/Resources/views/views/storefront/page/search/index.html.twig"
];

$fixed = 0;
foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "Not found: $file\n";
        continue;
    }

    $content = file_get_contents($file);
    $original = $content;

    // Old pattern with cents conditional
    $oldPattern1 = 'CHF {% if cents == 0 %}{{ priceValue|number_format(0, \'.\', "\'") }}{% else %}{{ priceValue|number_format(2, \'.\', "\'") }}{% endif %}';
    $oldPattern2 = "CHF {% if cents == 0 %}{{ priceValue|number_format(0, '.', \"'\") }}{% else %}{{ priceValue|number_format(2, '.', \"'\") }}{% endif %}";
    $oldPattern3 = "CHF {% if cents == 0 %}{{ priceValue|number_format(0, '.', '') }}{% else %}{{ priceValue|number_format(2, '.', '') }}{% endif %}";

    // New pattern with Swiss rounding
    $newPattern = 'CHF {{ ((priceValue * 20)|round / 20)|number_format(2, \'.\', "\'") }}';
    $newPattern2 = 'CHF {{ ((priceValue * 20)|round / 20)|number_format(2, \'.\', \'\') }}';

    $content = str_replace($oldPattern1, $newPattern, $content);
    $content = str_replace($oldPattern2, $newPattern, $content);
    $content = str_replace($oldPattern3, $newPattern2, $content);

    // Remove cents calculation line
    $content = preg_replace('/\s*\{% set cents = \(\(priceValue \* 100\) % 100\)\|round %\}/', '', $content);

    if ($content !== $original) {
        file_put_contents($file, $content);
        $fixed++;
        echo "Fixed: " . basename($file) . "\n";
    } else {
        echo "No match: " . basename($file) . "\n";
    }
}

echo "\nApplied Swiss Rappen rounding to $fixed files!\n";
