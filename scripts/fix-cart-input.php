<?php
$path = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/element/cms-element-buy-box.html.twig';
$content = file_get_contents($path);

// Remove the problematic hidden input that's causing the 500 error
// We'll use localStorage instead (already implemented)
$oldInput = <<<'OLD'
                {% if manufacturerName == 'Snigel' %}
                    {% set snigelColorOptions = product.customFields.snigel_color_options|default([]) %}
                    <input type="hidden" id="snigel-color-input"
                           name="lineItems[{{ product.id }}][payload][snigelColor]"
                           value="{{ snigelColorOptions[0].name|default('') }}">
                {% endif %}
OLD;

$newInput = <<<'NEW'
                {# Snigel color stored in localStorage (see updateCartColors function) #}
                {% if manufacturerName == 'Snigel' %}
                    {# Hidden input for JS reference (not submitted to form) #}
                    <input type="hidden" id="snigel-color-input"
                           data-product-id="{{ product.id }}"
                           value="{{ product.customFields.snigel_color_options[0].name|default('') }}"
                           disabled>
                {% endif %}
NEW;

$newContent = str_replace($oldInput, $newInput, $content);

if ($newContent !== $content) {
    file_put_contents($path, $newContent);
    echo "Fixed! Hidden input is now disabled and won't cause form errors.\n";
} else {
    echo "Pattern not found.\n";
}
