# Fix Variant Image Initial Load Bug

**Date:** 2026-01-08
**Status:** Design Complete

---

## Problem

On Raven Caliber Kit and Rifle product pages:
1. The first color variant (e.g., "Graphite Black") is pre-selected on page load
2. BUT the main image shows the product's **cover image** (e.g., blue `.22LR CALIBER KIT` image)
3. It should show the **first variant's image** (e.g., black `Graphite Black caliber.jpg`)

When users click on a different color, the JavaScript correctly switches the image. But on initial page load, there's a mismatch between the selected color and the displayed image.

## Root Cause

In `product-detail.html.twig`:
- Line 101-102: Main image `src` is set to `page.product.cover.media.url`
- Lines 217-242: Raven color swatches are rendered with first one marked as `selected`
- No JavaScript runs on page load to sync the image with the selected color

## Solution

**Approach: Set initial image in Twig** (cleanest, no JavaScript dependency)

When rendering a Raven variant product:
1. Check if `raven_variant_options` has items
2. If yes, use the first variant's `imageUrl` as the main image `src`
3. If the variant image fails to load, fall back to cover image

## Implementation

### 1. Update Main Image Source

**File:** `product-detail.html.twig` (around line 99-106)

**Before:**
```twig
{% if page.product.cover.media %}
<img id="product-main-image"
     src="{{ page.product.cover.media.url }}"
     ...
```

**After:**
```twig
{% if page.product.cover.media %}
{# For Raven products, use first variant image; otherwise use cover #}
{% set initialImageUrl = page.product.cover.media.url %}
{% if isRavenVariantProduct and ravenOptions|length > 0 and ravenOptions[0].imageUrl %}
    {% set initialImageUrl = ravenOptions[0].imageUrl %}
{% endif %}
<img id="product-main-image"
     src="{{ initialImageUrl }}"
     ...
```

### 2. Add Fallback for Image Load Errors

Add `onerror` handler to fall back to cover image if variant image fails:

```twig
<img id="product-main-image"
     src="{{ initialImageUrl }}"
     onerror="this.onerror=null; this.src='{{ page.product.cover.media.url }}';"
     ...
```

## Scope Check

Products affected:
- **Raven Caliber Kits:** .22LR, .223, 300 AAC, 9mm, 7.62x39 (5 products)
- **Raven Rifles:** .22LR, .223, 300 AAC, 9mm, 7.62x39 (5 products)
- **Snigel/Ausrüstung:** Not affected (use text-based color selectors, different code path)

## Testing Plan

1. Check all 5 Caliber Kit products:
   - Page loads with first variant image matching selected color name
   - Clicking other colors changes image correctly

2. Check all 5 Rifle products:
   - Same as above

3. Check Ausrüstung products:
   - No regression - still works as before

## Files to Modify

1. `shopware-theme/RavenTheme/src/Resources/views/storefront/page/content/product-detail.html.twig`
   - Update main image `src` logic
   - Add `onerror` fallback
