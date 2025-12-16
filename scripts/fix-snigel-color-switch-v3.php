<?php
/**
 * Fix Snigel Color Switching v3 - Update the VISIBLE main image
 *
 * The visible product image is #raven-main-product-image, not .gallery-slider-image
 *
 * Run: docker exec shopware-chf php /tmp/fix-snigel-color-switch-v3.php
 */

$path = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/element/cms-element-buy-box.html.twig';

if (!file_exists($path)) {
    die("Error: Template file not found at $path\n");
}

$content = file_get_contents($path);

// Find and replace the current selectSnigelColor function
$pattern = '/function selectSnigelColor\([^)]*\)\s*\{[\s\S]*?\n\}/';

$newFunction = <<<'NEWJS'
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
        'black': ['B01', '-01-', '_01_', '-01.'],
        'grey': ['B09', '-09-', '_09_', '-09.'],
        'gray': ['B09', '-09-', '_09_', '-09.'],
        'multicam': ['B56', '-56-', '_56_', '-56.'],
        'olive': ['B17', '-17-', '_17_', '-17.'],
        'tan': ['B15', '-15-', '_15_', '-15.'],
        'ranger green': ['B27', '-27-', '_27_', '-27.'],
        'coyote': ['B14', '-14-', '_14_', '-14.']
    };

    var colorKey = colorName.toLowerCase().trim();
    var patterns = colorCodes[colorKey] || [];

    // Collect ALL image URLs from the page (thumbnails + gallery)
    var allImages = [];
    document.querySelectorAll('img').forEach(function(img) {
        if (img.src && img.src.indexOf('/media/') !== -1) {
            allImages.push(img.src);
        }
    });

    // Find an image matching the color pattern
    var matchingImage = null;
    for (var i = 0; i < allImages.length; i++) {
        var imgUrl = allImages[i].toUpperCase();
        for (var j = 0; j < patterns.length; j++) {
            if (imgUrl.indexOf(patterns[j].toUpperCase()) !== -1) {
                matchingImage = allImages[i];
                break;
            }
        }
        if (matchingImage) break;
    }

    // Update the VISIBLE main image - prioritize raven-main-product-image
    if (matchingImage) {
        // The visible image is #raven-main-product-image (custom Raven theme)
        var mainImg = document.getElementById('raven-main-product-image');
        if (mainImg) {
            mainImg.src = matchingImage;
            mainImg.srcset = ''; // Clear srcset to prevent override
        }

        // Also update gallery slider images (hidden but used for zoom/lightbox)
        document.querySelectorAll('.gallery-slider-image, .product-detail-media img').forEach(function(img) {
            img.src = matchingImage;
            img.srcset = '';
        });
    }

    // Save color to localStorage for cart display
    var productId = colorInput ? colorInput.getAttribute("data-product-id") : null;
    if (productId) {
        try {
            var mainImgEl = document.getElementById("raven-main-product-image");
            var imageUrl = mainImgEl ? mainImgEl.src : "";
            localStorage.setItem("raven_color_" + productId, JSON.stringify({
                color: colorName,
                image: imageUrl
            }));
        } catch(e) {}
    }
}
NEWJS;

$newContent = preg_replace($pattern, $newFunction, $content);

if ($newContent !== $content) {
    file_put_contents($path, $newContent);
    echo "SUCCESS: Updated to target #raven-main-product-image (the visible image)!\n";
} else {
    echo "Pattern not found or already updated.\n";
}
