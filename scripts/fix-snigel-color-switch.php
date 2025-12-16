<?php
/**
 * Fix Snigel Color Switching - Make images change when selecting color
 *
 * Updates the selectSnigelColor function to:
 * 1. Map color names to Snigel color codes (Black=B01, Grey=B09, Multicam=B56)
 * 2. Search gallery thumbnails for images matching the color code
 * 3. Click the matching thumbnail to change the main image
 *
 * Run: docker exec shopware-chf php /tmp/fix-snigel-color-switch.php
 */

$path = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/element/cms-element-buy-box.html.twig';

if (!file_exists($path)) {
    die("Error: Template file not found at $path\n");
}

$content = file_get_contents($path);

// Old function to replace
$oldFunction = <<<'OLD'
function selectSnigelColor(btn, colorName, imageFilename) {
    // Update color label
    var colorLabel = document.getElementById("snigel-selected-color");
    if (colorLabel) colorLabel.textContent = colorName;

    // Update hidden input value (for JS reference)
    var colorInput = document.getElementById("snigel-color-input");
    if (colorInput) colorInput.value = colorName;

    // Save color to localStorage for cart display
    var productId = colorInput ? colorInput.getAttribute("data-product-id") : null;
    if (productId) {
        try {
            // Get current image URL for cart display
            var mainImg = document.getElementById("raven-main-product-image");
            var imageUrl = mainImg ? mainImg.src : "";
            localStorage.setItem("raven_color_" + productId, JSON.stringify({
                color: colorName,
                image: imageUrl
            }));
        } catch(e) {}
    }

    // Highlight selected button
    document.querySelectorAll(".snigel-color-btn").forEach(function(b) {
        b.classList.remove("selected");
    });
    btn.classList.add("selected");

    // Find matching thumbnail and click it to change main image
    if (imageFilename) {
        var thumbnails = document.querySelectorAll(".snigel-thumb");
        thumbnails.forEach(function(thumb) {
            var thumbUrl = thumb.getAttribute("data-image-url") || "";
            if (thumbUrl.toLowerCase().indexOf(imageFilename.toLowerCase()) !== -1) {
                thumb.click();
            }
        });
    }
}
OLD;

// New improved function with color code mapping
$newFunction = <<<'NEW'
function selectSnigelColor(btn, colorName, imageFilename) {
    // Update color label
    var colorLabel = document.getElementById("snigel-selected-color");
    if (colorLabel) colorLabel.textContent = colorName;

    // Update hidden input value (for JS reference)
    var colorInput = document.getElementById("snigel-color-input");
    if (colorInput) colorInput.value = colorName;

    // Highlight selected button
    document.querySelectorAll(".snigel-color-btn").forEach(function(b) {
        b.classList.remove("selected");
    });
    btn.classList.add("selected");

    // Color name to Snigel color code mapping
    var colorCodes = {
        'black': ['B01', '-01-', '_01_'],
        'grey': ['B09', '-09-', '_09_'],
        'gray': ['B09', '-09-', '_09_'],
        'multicam': ['B56', '-56-', '_56_'],
        'olive': ['B17', '-17-', '_17_'],
        'tan': ['B15', '-15-', '_15_'],
        'ranger green': ['B27', '-27-', '_27_'],
        'coyote': ['B14', '-14-', '_14_']
    };

    var colorKey = colorName.toLowerCase().trim();
    var patterns = colorCodes[colorKey] || [];

    // Find matching thumbnail in Shopware gallery
    var found = false;
    var thumbnails = document.querySelectorAll('.gallery-slider-thumbnails-image, .gallery-slider-thumbnails-item img, .tns-item img');

    thumbnails.forEach(function(thumb) {
        if (found) return;
        var thumbSrc = thumb.src || '';

        // Check if thumbnail matches any pattern for this color
        for (var i = 0; i < patterns.length; i++) {
            if (thumbSrc.toUpperCase().indexOf(patterns[i].toUpperCase()) !== -1) {
                // Found matching thumbnail - click its parent button/container
                var clickTarget = thumb.closest('button') || thumb.closest('.tns-item') || thumb;
                if (clickTarget && clickTarget.click) {
                    clickTarget.click();
                    found = true;
                    break;
                }
            }
        }
    });

    // Also try clicking the gallery navigation if available
    if (!found) {
        var galleryItems = document.querySelectorAll('.gallery-slider-item img, .product-detail-media img');
        galleryItems.forEach(function(img) {
            if (found) return;
            var imgSrc = img.src || '';
            for (var i = 0; i < patterns.length; i++) {
                if (imgSrc.toUpperCase().indexOf(patterns[i].toUpperCase()) !== -1) {
                    // Update main image directly
                    var mainImg = document.getElementById('raven-main-product-image') ||
                                  document.querySelector('.gallery-slider-image');
                    if (mainImg) {
                        mainImg.src = imgSrc;
                        found = true;
                        break;
                    }
                }
            }
        });
    }

    // Save color to localStorage for cart display
    var productId = colorInput ? colorInput.getAttribute("data-product-id") : null;
    if (productId) {
        try {
            var mainImg = document.getElementById("raven-main-product-image") ||
                          document.querySelector('.gallery-slider-image');
            var imageUrl = mainImg ? mainImg.src : "";
            localStorage.setItem("raven_color_" + productId, JSON.stringify({
                color: colorName,
                image: imageUrl
            }));
        } catch(e) {}
    }
}
NEW;

// Try to replace
$newContent = str_replace($oldFunction, $newFunction, $content);

if ($newContent !== $content) {
    file_put_contents($path, $newContent);
    echo "SUCCESS: Updated selectSnigelColor function with color code mapping!\n";
    echo "\nColor codes supported:\n";
    echo "  - Black  -> B01\n";
    echo "  - Grey   -> B09\n";
    echo "  - Multicam -> B56\n";
    echo "  - Olive  -> B17\n";
    echo "\nNow clear cache: bin/console cache:clear\n";
} else {
    echo "Pattern not found. Let me check the current function...\n\n";

    // Show what's currently there
    if (preg_match('/function selectSnigelColor\([^)]*\)\s*\{[\s\S]*?\n\}/', $content, $matches)) {
        echo "Current function found:\n";
        echo substr($matches[0], 0, 500) . "...\n";
    } else {
        echo "Function selectSnigelColor not found in template.\n";
    }
}
