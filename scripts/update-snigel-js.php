<?php
$path = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/element/cms-element-buy-box.html.twig';
$content = file_get_contents($path);

// Update the selectSnigelColor function to save color to localStorage
$oldFunction = <<<'OLD'
function selectSnigelColor(btn, colorName, imageFilename) {
    // Update color label
    var colorLabel = document.getElementById("snigel-selected-color");
    if (colorLabel) colorLabel.textContent = colorName;

    // Update hidden input for cart
    var colorInput = document.getElementById("snigel-color-input");
    if (colorInput) colorInput.value = colorName;

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

$newFunction = <<<'NEW'
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
NEW;

$newContent = str_replace($oldFunction, $newFunction, $content);

if ($newContent !== $content) {
    file_put_contents($path, $newContent);
    echo "Updated selectSnigelColor to save color to localStorage!\n";
} else {
    echo "Pattern not found.\n";
}
