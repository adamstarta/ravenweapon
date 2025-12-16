<?php
$path = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/element/cms-element-buy-box.html.twig';
$content = file_get_contents($path);

// Check if function already exists
if (strpos($content, 'function selectSnigelColor') !== false) {
    echo "Function already exists, skipping.\n";
    exit(0);
}

$jsFunction = '
// Snigel color selector function
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
';

// Find "})();" pattern before "</script>" and insert JS before it
$pattern = '/(}\)\(\);\s*<\/script>)/s';
$replacement = $jsFunction . "\n$1";
$newContent = preg_replace($pattern, $replacement, $content, 1);

if ($newContent !== $content) {
    file_put_contents($path, $newContent);
    echo "JavaScript function added successfully!\n";
} else {
    echo "Pattern not found, trying alternative...\n";

    // Alternative: insert before </script>{% endblock %}
    $oldEnd = "</script>\n{% endblock %}";
    $newEnd = $jsFunction . "\n</script>\n{% endblock %}";
    $newContent = str_replace($oldEnd, $newEnd, $content);

    if ($newContent !== $content) {
        file_put_contents($path, $newContent);
        echo "JavaScript function added (alternative method)!\n";
    } else {
        echo "Failed to add JavaScript function.\n";
    }
}
