<?php
/**
 * Fix search price display - show 2 decimal places with Swiss 5 Rappen rounding
 */

$file = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig';
$content = file_get_contents($file);

// Old code that rounds to integer
$old = "if (priceMatch) {
                            // Remove thousands separator and parse
                            var priceNum = parseFloat(priceMatch[1].replace(/[,']/g, ''));
                            if (!isNaN(priceNum)) {
                                var roundedPrice = Math.round(priceNum);
                                price = 'CHF ' + roundedPrice.toString().replace(/\B(?=(\d{3})+(?!\d))/g, \"'\");
                            }
                        }";

// New code that keeps 2 decimal places with Swiss 5 Rappen rounding
$new = "if (priceMatch) {
                            // Remove thousands separator and parse
                            var priceNum = parseFloat(priceMatch[1].replace(/[,']/g, ''));
                            if (!isNaN(priceNum)) {
                                // Swiss 5 Rappen rounding (round to nearest 0.05)
                                var roundedPrice = Math.round(priceNum * 20) / 20;
                                // Format with 2 decimal places and Swiss thousands separator
                                var intPart = Math.floor(roundedPrice);
                                var decPart = Math.round((roundedPrice - intPart) * 100);
                                var formattedInt = intPart.toString().replace(/\\B(?=(\\d{3})+(?!\\d))/g, \"'\");
                                price = 'CHF ' + formattedInt + '.' + (decPart < 10 ? '0' : '') + decPart;
                            }
                        }";

$content = str_replace($old, $new, $content);

file_put_contents($file, $content);
echo "Search price display fixed - now shows 2 decimals with Swiss 5 Rappen rounding\n";
