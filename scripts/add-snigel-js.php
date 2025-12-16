<?php
$path = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/element/cms-element-buy-box.html.twig';
$content = file_get_contents($path);

$jsFunction = <<<'JS'

// Snigel color selector function
function selectSnigelColor(btn, colorName, imageFilename) {
    // Update color label
    var colorLabel = document.getElementById('snigel-selected-color');
    if (colorLabel) colorLabel.textContent = colorName;

    // Update hidden input for cart
    var colorInput = document.getElementById('snigel-color-input');
    if (colorInput) colorInput.value = colorName;

    // Highlight selected button
    document.querySelectorAll('.snigel-color-btn').forEach(function(b) {
        b.classList.remove('selected');
    });
    btn.classList.add('selected');

    // Find matching thumbnail and click it to change main image
    if (imageFilename) {
        var thumbnails = document.querySelectorAll('.snigel-thumb');
        thumbnails.forEach(function(thumb) {
            var thumbUrl = thumb.getAttribute('data-image-url') || '';
            if (thumbUrl.toLowerCase().indexOf(imageFilename.toLowerCase()) !== -1) {
                thumb.click();
            }
        });
    }
}
JS;

// Find the last </script> before {% endblock %}
$pattern = '/(}\s*\)\(\);\s*<\/script>)\s*({% endblock %})/s';
$replacement = '$1' . $jsFunction . "\n</script>\n\$2";

// Alternative: just add before the closing script
$oldEnd = "})();\n</script>\n{% endblock %}";
$newEnd = "})();\n" . $jsFunction . "\n</script>\n{% endblock %}";

$content = str_replace($oldEnd, $newEnd, $content);

file_put_contents($path, $content);
echo "JavaScript function added!\n";
