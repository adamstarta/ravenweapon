<?php
/**
 * Update the buy-box template to add Snigel color variant support
 */

$templatePath = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/element/cms-element-buy-box.html.twig';

// Read current template
$content = file_get_contents($templatePath);

// Find the position after the closing of "raven-buy-box-wrapper" div's grid-template-columns style
// We need to add Snigel-specific layout

// 1. Add Snigel thumbnail gallery - insert after raven-main-image-container opening for Snigel products
$snigelThumbnailGallery = <<<'TWIG'

        {# ========== SNIGEL PRODUCTS: Thumbnail Gallery + Main Image ========== #}
        {% if manufacturerName == 'Snigel' %}
        <div class="snigel-product-images">
            {# Left: Thumbnail Gallery #}
            <div class="snigel-thumbnail-gallery">
                {% for mediaItem in allMedia %}
                    {% if mediaItem.media %}
                    <button class="snigel-thumb {% if loop.first %}active{% endif %}"
                            data-image-url="{{ mediaItem.media.url }}"
                            onclick="document.getElementById('raven-main-product-image').src='{{ mediaItem.media.url }}';
                                     document.getElementById('raven-zoom-result-img').src='{{ mediaItem.media.url }}';
                                     document.querySelectorAll('.snigel-thumb').forEach(t => t.classList.remove('active'));
                                     this.classList.add('active');">
                        <img src="{{ mediaItem.media.url }}" alt="Thumbnail {{ loop.index }}">
                    </button>
                    {% endif %}
                {% endfor %}
            </div>

            {# Right: Main Product Image with Zoom #}
            <div class="snigel-main-image-wrapper">
                <div class="raven-main-image" id="raven-zoom-container">
                    {% set snigelMainImage = product.cover.media.url|default('') %}
                    {% if snigelMainImage %}
                    <img id="raven-main-product-image"
                         src="{{ snigelMainImage }}"
                         alt="{{ product.translated.name }}"
                         class="raven-product-img">
                    <div id="raven-zoom-lens" class="raven-zoom-lens"></div>
                    <div id="raven-zoom-result" class="raven-zoom-result">
                        <img id="raven-zoom-result-img" src="{{ snigelMainImage }}" alt="Zoomed">
                    </div>
                    {% else %}
                    <div class="raven-no-image">Kein Bild verfügbar</div>
                    {% endif %}
                </div>
            </div>
        </div>
        {% else %}
TWIG;

// 2. Add Snigel color selector section
$snigelColorSelector = <<<'TWIG'

            {# ========== SNIGEL COLOR SELECTOR ========== #}
            {% if manufacturerName == 'Snigel' %}
                {% set snigelColorOptions = product.customFields.snigel_color_options|default([]) %}
                {% set snigelHasColors = product.customFields.snigel_has_colors|default(false) %}
                {% if snigelColorOptions|length > 0 %}
                <div class="snigel-color-section">
                    <p class="raven-color-label">
                        Farbe: <span id="snigel-selected-color" class="raven-color-value">{{ snigelColorOptions[0].name }}</span>
                    </p>
                    <div class="snigel-color-buttons">
                        {% for colorOption in snigelColorOptions %}
                        <button type="button"
                                class="snigel-color-btn {% if loop.first %}selected{% endif %}"
                                data-color-name="{{ colorOption.name }}"
                                data-image-filename="{{ colorOption.imageFilename }}"
                                onclick="selectSnigelColor(this, '{{ colorOption.name }}', '{{ colorOption.imageFilename }}')">
                            {{ colorOption.name }}
                        </button>
                        {% endfor %}
                    </div>
                </div>
                {% endif %}
            {% endif %}
TWIG;

// 3. Add hidden color input for cart (Snigel)
$snigelHiddenInput = <<<'TWIG'

                {# Snigel Color Selection #}
                {% if manufacturerName == 'Snigel' %}
                    {% set snigelColorOptions = product.customFields.snigel_color_options|default([]) %}
                    <input type="hidden" id="snigel-color-input"
                           name="lineItems[{{ product.id }}][payload][snigelColor]"
                           value="{{ snigelColorOptions[0].name|default('') }}">
                {% endif %}
TWIG;

// 4. Add Snigel JavaScript
$snigelJavaScript = <<<'TWIG'

{# ========== SNIGEL COLOR SELECTOR JAVASCRIPT ========== #}
<script>
function selectSnigelColor(btn, colorName, imageFilename) {
    // Update color label
    document.getElementById('snigel-selected-color').textContent = colorName;

    // Update hidden input for cart
    var colorInput = document.getElementById('snigel-color-input');
    if (colorInput) {
        colorInput.value = colorName;
    }

    // Highlight selected button
    document.querySelectorAll('.snigel-color-btn').forEach(function(b) {
        b.classList.remove('selected');
    });
    btn.classList.add('selected');

    // Try to find and show the color image
    if (imageFilename) {
        var thumbnails = document.querySelectorAll('.snigel-thumb');
        thumbnails.forEach(function(thumb) {
            var thumbUrl = thumb.getAttribute('data-image-url') || '';
            if (thumbUrl.indexOf(imageFilename) !== -1) {
                // Found matching thumbnail - click it to change main image
                thumb.click();
            }
        });
    }
}
</script>
TWIG;

// 5. Add Snigel CSS
$snigelCSS = <<<'TWIG'

    /* ========== SNIGEL THUMBNAIL GALLERY STYLES ========== */
    .snigel-product-images {
        display: flex;
        gap: 1rem;
        min-height: 400px;
    }

    .snigel-thumbnail-gallery {
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-height: 500px;
        overflow-y: auto;
        padding-right: 8px;
        width: 80px;
        flex-shrink: 0;
    }

    .snigel-thumbnail-gallery::-webkit-scrollbar {
        width: 4px;
    }

    .snigel-thumbnail-gallery::-webkit-scrollbar-track {
        background: #F3F4F6;
        border-radius: 2px;
    }

    .snigel-thumbnail-gallery::-webkit-scrollbar-thumb {
        background: #D1D5DB;
        border-radius: 2px;
    }

    .snigel-thumb {
        width: 70px;
        height: 70px;
        flex-shrink: 0;
        border: 2px solid #E5E7EB;
        border-radius: 8px;
        padding: 4px;
        background: white;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .snigel-thumb img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .snigel-thumb:hover {
        border-color: #D4A847;
    }

    .snigel-thumb.active {
        border-color: #F2C200;
        border-width: 3px;
        box-shadow: 0 0 0 2px rgba(242, 194, 0, 0.2);
    }

    .snigel-main-image-wrapper {
        flex: 1;
        min-width: 0;
    }

    /* ========== SNIGEL COLOR SELECTOR STYLES ========== */
    .snigel-color-section {
        margin-bottom: 1.5rem;
    }

    .snigel-color-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .snigel-color-btn {
        padding: 8px 16px;
        border: 2px solid #E5E7EB;
        border-radius: 6px;
        background: white;
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        font-weight: 500;
        color: #374151;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .snigel-color-btn:hover {
        border-color: #D4A847;
        background: #FFFBEB;
    }

    .snigel-color-btn.selected {
        border-color: #F2C200;
        background: #FEF3C7;
        color: #92400E;
        font-weight: 600;
    }
TWIG;

// Now apply the modifications

// 1. Find the raven-main-image-container section and wrap it with Snigel condition
$oldImageContainer = '<div class="raven-main-image-container">';
$newImageContainer = $snigelThumbnailGallery . "\n        " . $oldImageContainer;

// We need to close the else block after the original image container
$oldImageContainerEnd = '</div>

        {# RIGHT: Product Info #}';
$newImageContainerEnd = '</div>
        {% endif %}

        {# RIGHT: Product Info #}';

// 2. Find position after VAT info to insert color selector
$oldVatSection = '{# VAT included text #}
            <p class="raven-vat-info">inkl. MwSt.</p>';
$newVatSection = $oldVatSection . $snigelColorSelector;

// 3. Find the form inputs section to add hidden color input
$oldFormInputs = '<input type="hidden" name="lineItems[{{ product.id }}][removable]" value="1">';
$newFormInputs = $oldFormInputs . $snigelHiddenInput;

// 4. Find end of template to add JavaScript (before closing div)
// Add JS before the closing </div> of cms-element-buy-box

// 5. Add CSS to the style section
$oldStyleEnd = '/* Hide the default Shopware';
$newStyleEnd = $snigelCSS . "\n\n    " . $oldStyleEnd;

// Apply all replacements
$content = str_replace($oldImageContainer, $newImageContainer, $content);
$content = str_replace($oldImageContainerEnd, $newImageContainerEnd, $content);
$content = str_replace($oldVatSection, $newVatSection, $content);
$content = str_replace($oldFormInputs, $newFormInputs, $content);
$content = str_replace($oldStyleEnd, $newStyleEnd, $content);

// Add JavaScript before the closing </style> tag at the end
$content = str_replace('</style>

{% endblock %}', '</style>' . $snigelJavaScript . "\n\n{% endblock %}", $content);

// Write updated template
file_put_contents($templatePath, $content);

echo "Template updated successfully!\n";
echo "Changes made:\n";
echo "- Added Snigel thumbnail gallery\n";
echo "- Added Snigel color selector buttons\n";
echo "- Added hidden color input for cart\n";
echo "- Added Snigel CSS styles\n";
echo "- Added Snigel JavaScript\n";
