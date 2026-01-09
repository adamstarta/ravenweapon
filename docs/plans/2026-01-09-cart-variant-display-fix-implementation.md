# Cart Variant Display Bug Fix - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix the bug where product variants (color/size) don't display correctly in the offcanvas cart sidebar.

**Architecture:** Three-layer fix: (1) Backend fallback reads variant data from Shopware's product options, (2) Product detail page pre-populates size input with default value, (3) Product listing page sends variant data with add-to-cart form.

**Tech Stack:** PHP 8.3, Twig, Shopware 6.6

---

## Task 1: Fix Backend Fallback in CartLineItemSubscriber

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Subscriber/CartLineItemSubscriber.php:62-74`

**Step 1: Read the existing fallback logic**

Current code at lines 62-74 only builds `variantenDisplay` from request params. We need to also check Shopware's built-in `options` payload.

**Step 2: Update the fallback logic**

Replace lines 62-74 with enhanced fallback that reads from `lineItem.payload.options`:

```php
        // Fallback: build variantenDisplay from color/size if not provided
        if (empty($payload['variantenDisplay'])) {
            $parts = [];

            // First try request parameters
            if (!empty($payload['selectedColor'])) {
                $parts[] = $payload['selectedColor'];
            }
            if (!empty($payload['selectedSize'])) {
                $parts[] = $payload['selectedSize'];
            }

            // If still empty, try to get from Shopware's built-in product options
            if (empty($parts)) {
                $options = $lineItem->getPayloadValue('options') ?? [];
                $colorFromOptions = null;
                $sizeFromOptions = null;

                foreach ($options as $option) {
                    $groupName = $option['group'] ?? '';
                    $optionName = $option['option'] ?? '';

                    if (in_array($groupName, ['Farbe', 'Color', 'Colour'])) {
                        $colorFromOptions = $optionName;
                    } elseif (in_array($groupName, ['Größe', 'Grösse', 'Size'])) {
                        $sizeFromOptions = $optionName;
                    }
                }

                if ($colorFromOptions) {
                    $parts[] = $colorFromOptions;
                    $payload['selectedColor'] = $colorFromOptions;
                }
                if ($sizeFromOptions) {
                    $parts[] = $sizeFromOptions;
                    $payload['selectedSize'] = $sizeFromOptions;
                }
            }

            if (!empty($parts)) {
                $payload['variantenDisplay'] = implode(' / ', $parts);
            }
        }
```

**Step 3: Verify the file compiles**

Run: Check PHP syntax with `php -l shopware-theme/RavenTheme/src/Subscriber/CartLineItemSubscriber.php`
Expected: No syntax errors

**Step 4: Commit**

```bash
git add shopware-theme/RavenTheme/src/Subscriber/CartLineItemSubscriber.php
git commit -m "fix(cart): add fallback to read variant options from Shopware payload

When selectedColor/selectedSize are not in the request, now falls back
to reading from Shopware's built-in options array on the line item.

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>"
```

---

## Task 2: Fix Product Detail Page - Pre-populate Size Input

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/product-detail/index.html.twig:376-379`

**Step 1: Locate the size input**

Current code at line 377-379:
```twig
{% if snigelHasSizes and snigelSizeOptions %}
    <input type="hidden" name="selectedSize" id="snigel-size-input" value="">
{% endif %}
```

The `value=""` causes the race condition - JavaScript populates it later but form may submit first.

**Step 2: Pre-populate with first size value in Twig**

Replace lines 376-379 with:

```twig
{# Snigel size selection - pre-populate with first value to avoid race condition #}
{% if snigelHasSizes and snigelSizeOptions %}
    {% set firstSizeValue = '' %}
    {% set isJsonString = snigelSizeOptions is string and snigelSizeOptions|trim|slice(0,1) == '[' %}
    {% if not isJsonString %}
        {# Handle array or comma-separated string #}
        {% if snigelSizeOptions is iterable and snigelSizeOptions is not string %}
            {% set firstSizeValue = snigelSizeOptions[0].name|default(snigelSizeOptions[0]|default('')) %}
        {% elseif snigelSizeOptions is string and ',' in snigelSizeOptions %}
            {% set firstSizeValue = (snigelSizeOptions|split(','))[0]|trim %}
        {% elseif snigelSizeOptions is string and snigelSizeOptions|trim != '' %}
            {% set firstSizeValue = snigelSizeOptions|trim %}
        {% endif %}
    {% endif %}
    <input type="hidden" name="selectedSize" id="snigel-size-input" value="{{ firstSizeValue }}">
{% endif %}
```

**Step 3: Update JavaScript to also set initial value for JSON case**

Find the JavaScript at lines 297-299 and ensure it sets the hidden input value. The current code already does this:
```javascript
var sizeInput = document.getElementById('snigel-size-input');
if (sizeInput) sizeInput.value = sizeData[0].name || sizeData[0] || '';
```

This is correct - JavaScript will override the empty value for JSON strings. No change needed here.

**Step 4: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/product-detail/index.html.twig
git commit -m "fix(cart): pre-populate size input to fix race condition

Size hidden input now gets default value in Twig for non-JSON cases,
eliminating the race condition where form submits before JavaScript
populates the value.

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>"
```

---

## Task 3: Fix Product Listing Page - Add Variant Hidden Inputs

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/component/product/card/box-standard.html.twig:190-201`

**Step 1: Locate the add-to-cart form**

Current form at lines 190-201 has no variant inputs:
```twig
<form action="{{ path('frontend.checkout.line-item.add') }}" method="post" data-add-to-cart="true">
    <input type="hidden" name="redirectTo" value="frontend.cart.offcanvas"/>
    <input type="hidden" name="lineItems[{{ product.id }}][id]" value="{{ product.id }}"/>
    ...
    <button type="submit" ...>
```

**Step 2: Add variant detection and hidden inputs**

Replace lines 190-201 with:

```twig
<form action="{{ path('frontend.checkout.line-item.add') }}" method="post" data-add-to-cart="true">
    <input type="hidden" name="redirectTo" value="frontend.cart.offcanvas"/>
    <input type="hidden" name="lineItems[{{ product.id }}][id]" value="{{ product.id }}"/>
    <input type="hidden" name="lineItems[{{ product.id }}][referencedId]" value="{{ product.id }}"/>
    <input type="hidden" name="lineItems[{{ product.id }}][type]" value="product"/>
    <input type="hidden" name="lineItems[{{ product.id }}][stackable]" value="1"/>
    <input type="hidden" name="lineItems[{{ product.id }}][removable]" value="1"/>
    <input type="hidden" name="lineItems[{{ product.id }}][quantity]" value="1"/>

    {# Capture variant options from product for cart display #}
    {% set variantColor = null %}
    {% set variantSize = null %}
    {% if product.options is defined and product.options|length > 0 %}
        {% for option in product.options %}
            {% set groupName = option.group.translated.name|default(option.group.name|default('')) %}
            {% if groupName in ['Farbe', 'Color', 'Colour'] and variantColor is null %}
                {% set variantColor = option.translated.name|default(option.name) %}
            {% elseif groupName in ['Größe', 'Grösse', 'Size'] and variantSize is null %}
                {% set variantSize = option.translated.name|default(option.name) %}
            {% endif %}
        {% endfor %}
    {% endif %}
    {% if variantColor %}
        <input type="hidden" name="selectedColor" value="{{ variantColor }}">
    {% endif %}
    {% if variantSize %}
        <input type="hidden" name="selectedSize" value="{{ variantSize }}">
    {% endif %}

    <button type="submit" class="raven-cart-btn" title="In den Warenkorb" aria-label="In den Warenkorb">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px;"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
    </button>
</form>
```

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/component/product/card/box-standard.html.twig
git commit -m "fix(cart): add variant hidden inputs to product listing cards

Product cards now send selectedColor and selectedSize from the product's
options when adding to cart, ensuring variant info displays in cart sidebar.

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>"
```

---

## Task 4: Test All Scenarios

**Step 1: Deploy to staging**

```bash
git push origin main
```

Wait for GitHub Actions to deploy to staging (~2 minutes).

**Step 2: Test on staging site**

Visit: https://developing.ravenweapon.ch

**Test Case 1: Add color-only product from listing page**
1. Go to a category with color-only products
2. Click the cart button on a product card
3. Expected: Cart shows "Variante: [Color Name]"

**Test Case 2: Add color+size product from listing page**
1. Go to a category with products that have color and size
2. Click the cart button on a product card
3. Expected: Cart shows "Variante: [Color] / [Size]"

**Test Case 3: Add from product detail page (first load)**
1. Go to a product detail page with color and size options
2. Without changing any selections, click "In den Warenkorb"
3. Expected: Cart shows full variant info immediately

**Test Case 4: Change variant and add**
1. On product detail page, select a different size
2. Click "In den Warenkorb"
3. Expected: Cart shows the newly selected variant

**Step 3: Approve production deployment**

If all tests pass on staging, approve the production deployment in GitHub Actions.

---

## Summary

| Task | File | Change |
|------|------|--------|
| 1 | `CartLineItemSubscriber.php` | Add fallback to read from Shopware's options payload |
| 2 | `product-detail/index.html.twig` | Pre-populate size input with default value |
| 3 | `box-standard.html.twig` | Add hidden inputs for variant options |
| 4 | - | Test all scenarios on staging |

**Total commits:** 3
**Estimated testing:** Verify 4 test cases on staging site
