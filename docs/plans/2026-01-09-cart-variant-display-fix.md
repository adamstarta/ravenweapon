# Cart Variant Display Bug Fix

**Date:** 2026-01-09
**Status:** Approved
**Author:** Claude Code

## Problem Statement

Product variants (color/size) are not displaying correctly in the offcanvas cart sidebar:

1. **From product listing page:** No variant info shown at all
2. **From product detail page (initial add):** Only shows color, missing size (e.g., "Variante: Navy" instead of "Variante: Navy / Small")
3. **After reselecting variants:** Shows correctly (e.g., "Variante: Navy / Small")

## Root Cause Analysis

### Problem 1: Product Listing Cards - No Variant Data Sent
**File:** `box-standard.html.twig` (lines 190-201)

The "Add to Cart" form on product listing pages has no hidden inputs for variants. Zero variant data is sent to the backend.

### Problem 2: Product Detail Page - Size Input Starts Empty
**File:** `product-detail/index.html.twig` (lines 367-379)

The size hidden input is initialized with `value=""` (empty). JavaScript fills it later, but there's a race condition - if the form submits before JavaScript finishes, the size is lost.

### Problem 3: Why Reselecting Works
**File:** `product-detail/index.html.twig` (lines 1081-1140)

When user manually clicks a size button, `selectSnigelSize()` explicitly sets `sizeInput.value = sizeName` before form submission, guaranteeing the data is present.

## Solution Design

### Approach: Fix at Data Capture Layer

Fix the source of the problem by ensuring variant data is always sent with the form.

### Fix 1: Product Listing Page (`box-standard.html.twig`)

Add hidden inputs that capture the product's default variant options:

```twig
{# In the add-to-cart form on product cards #}
<form action="{{ path('frontend.checkout.line-item.add') }}" method="post">
    {# Existing inputs... #}

    {# NEW: Capture default variant options from product #}
    {% set defaultOptions = product.options.elements|default([]) %}
    {% set colorOption = null %}
    {% set sizeOption = null %}

    {% for option in defaultOptions %}
        {% if option.group.name in ['Farbe', 'Color', 'Colour'] %}
            {% set colorOption = option.name %}
        {% elseif option.group.name in ['Größe', 'Grösse', 'Size'] %}
            {% set sizeOption = option.name %}
        {% endif %}
    {% endfor %}

    {% if colorOption %}
        <input type="hidden" name="selectedColor" value="{{ colorOption }}">
    {% endif %}
    {% if sizeOption %}
        <input type="hidden" name="selectedSize" value="{{ sizeOption }}">
    {% endif %}
</form>
```

### Fix 2: Product Detail Page (`product-detail/index.html.twig`)

Pre-populate the size input with the first/default size value directly in Twig:

```twig
{% if snigelHasSizes and snigelSizeOptions %}
    {# Pre-populate with first available size - no race condition #}
    {% set firstSize = null %}
    {% if snigelSizeOptions is iterable %}
        {% for sizeOpt in snigelSizeOptions %}
            {% if firstSize is null %}
                {% set firstSize = sizeOpt.name|default(sizeOpt) %}
            {% endif %}
        {% endfor %}
    {% endif %}
    <input type="hidden" name="selectedSize" id="snigel-size-input" value="{{ firstSize|default('') }}">
{% endif %}
```

### Fix 3: Backend Fallback (`CartLineItemSubscriber.php`)

Enhance the subscriber to read from Shopware's product options as a fallback:

```php
public function onLineItemAdded(BeforeLineItemAddedEvent $event): void
{
    $lineItem = $event->getLineItem();

    // Try to get from request first (user selections)
    $request = $this->requestStack->getCurrentRequest();
    $selectedColor = $request?->request->get('selectedColor');
    $selectedSize = $request?->request->get('selectedSize');

    // If both empty, try to get from product's default options
    if (empty($selectedColor) && empty($selectedSize)) {
        $options = $lineItem->getPayloadValue('options') ?? [];
        foreach ($options as $option) {
            $groupName = $option['group'] ?? '';
            $optionName = $option['option'] ?? '';

            if (in_array($groupName, ['Farbe', 'Color', 'Colour'])) {
                $selectedColor = $optionName;
            } elseif (in_array($groupName, ['Größe', 'Grösse', 'Size'])) {
                $selectedSize = $optionName;
            }
        }
    }

    // Build variantenDisplay from whatever we found
    $parts = array_filter([$selectedColor, $selectedSize]);
    if (!empty($parts)) {
        $lineItem->setPayloadValue('variantenDisplay', implode(' / ', $parts));
        $lineItem->setPayloadValue('selectedColor', $selectedColor);
        $lineItem->setPayloadValue('selectedSize', $selectedSize);
    }
}
```

## Implementation Order

1. **Fix backend first** (CartLineItemSubscriber) - provides safety net
2. **Fix product detail page** - resolves the race condition
3. **Fix product listing page** - ensures listing adds work correctly
4. **Test all scenarios**

## Test Cases

| Scenario | Expected Result |
|----------|-----------------|
| Add color-only product from listing | Shows "Variante: Navy" |
| Add color+size product from listing | Shows "Variante: Navy / Small" |
| Add from product detail (first load) | Shows full variant info |
| Change variant and add | Shows updated variant info |
| Add same product multiple times with different variants | Each line item shows correct variant |

## Risk Assessment

- **Low risk:** Template changes (Twig) - easy to revert
- **Low risk:** PHP subscriber change - fallback logic, doesn't break existing flow
- **Testing needed:** Verify Shopware's `options` payload structure matches expectations

## Files to Modify

| File | Change |
|------|--------|
| `shopware-theme/RavenTheme/src/Resources/views/storefront/component/product/card/box-standard.html.twig` | Add hidden inputs for default variant options |
| `shopware-theme/RavenTheme/src/Resources/views/storefront/page/product-detail/index.html.twig` | Pre-populate size input with first value |
| `shopware-theme/RavenTheme/src/Subscriber/CartLineItemSubscriber.php` | Add fallback to read from product options |
