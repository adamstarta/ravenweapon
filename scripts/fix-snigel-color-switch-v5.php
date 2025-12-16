<?php
/**
 * Fix Snigel Color Switching v5 - More flexible pattern matching
 *
 * Uses regex-style matching to find color codes in ANY position:
 *   - Matches A01, B01, -01-, -01_, _01_, 01- etc.
 *
 * Run: docker exec shopware-chf php /tmp/fix-snigel-color-switch-v5.php
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

    // Color name to Snigel color NUMBER mapping
    var colorNumbers = {
        'black': '01',
        'grey': '09',
        'gray': '09',
        'multicam': '56',
        'olive': '17',
        'tan': '15',
        'ranger green': '27',
        'coyote': '14',
        'khaki': '15',
        'highvis': '28',
        'highvis yellow': '28',
        'yellow': '28'
    };

    var colorKey = colorName.toLowerCase().trim();
    var colorNum = colorNumbers[colorKey];

    if (!colorNum) {
        console.log('Unknown color:', colorName);
        return;
    }

    // Collect ALL image URLs from the page
    var allImages = [];
    document.querySelectorAll('img').forEach(function(img) {
        if (img.src && img.src.indexOf('/media/') !== -1) {
            allImages.push(img.src);
        }
    });

    // Find an image matching the color number
    // Match patterns like: A01, B01, -01-, -01_, _01_, -01. etc.
    var matchingImage = null;
    var patterns = [
        'A' + colorNum,      // A01, A09, A56
        'B' + colorNum,      // B01, B09, B56
        '-' + colorNum + '-', // -01-, -09-
        '-' + colorNum + '_', // -01_, -09_
        '-' + colorNum + '.', // -01., -09.
        '_' + colorNum + '_', // _01_, _09_
        '_' + colorNum + '-', // _01-, _09-
        '_' + colorNum + '.', // _01., _09.
    ];

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

    // Update the VISIBLE main image
    if (matchingImage) {
        var mainImg = document.getElementById('raven-main-product-image');
        if (mainImg) {
            mainImg.src = matchingImage;
            mainImg.srcset = '';
        }

        // Also update gallery slider images
        document.querySelectorAll('.gallery-slider-image, .product-detail-media img').forEach(function(img) {
            img.src = matchingImage;
            img.srcset = '';
        });
    } else {
        console.log('No image found for color:', colorName, 'code:', colorNum);
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
    echo "SUCCESS: Updated with flexible pattern matching!\n";
    echo "\nColor codes:\n";
    echo "  - Black: 01\n";
    echo "  - Grey: 09\n";
    echo "  - Multicam: 56\n";
    echo "  - Olive: 17\n";
    echo "  - Tan/Khaki: 15\n";
    echo "  - HighVis: 28\n";
    echo "  - Ranger Green: 27\n";
    echo "  - Coyote: 14\n";
    echo "\nPattern matching: A##, B##, -##-, -##_, _##_, etc.\n";
    echo "\nRun: bin/console cache:clear\n";
} else {
    echo "Pattern not found or already updated.\n";
}
