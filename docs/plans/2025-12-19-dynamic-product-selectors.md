# Dynamic Product Selectors Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make ALL product pages use dynamic VARIANT selectors (Varianten) instead of hardcoded values, ensuring Raven weapons, caliber kits, and Snigel products all work consistently.

**Architecture:** Extend the proven Snigel custom fields approach to ALL products using unified custom fields. Remove hardcoded variant arrays and unreliable filename-based extraction.

**IMPORTANT:** Use "Variante" (singular) / "Varianten" (plural) as the label - NOT "Farbe" (color). This is more generic and works for any variant type.

**Tech Stack:** Shopware 6.6, Twig templates, PHP scripts for data population, Custom Fields API

---

## Problem Analysis

### Current Issues

1. **index.html.twig line 150** - HARDCODED color names:
   ```twig
   {% set colorNames = ['Graphite Black', 'Flat Dark Earth', 'Northern Lights', 'Olive Drab Green', 'Sniper Grey'] %}
   ```

2. **cms-element-buy-box.html.twig lines 141-178** - Unreliable filename extraction for non-Snigel products:
   ```twig
   {% set extractedColor = mediaFileName|replace({'rifle': '', 'Rifle': ''...})|trim %}
   ```

3. **Snigel products work correctly** because they use:
   - `snigel_color_options`: JSON array of `[{name, value, imageUrl}]`
   - `snigel_has_color_variants`: Boolean flag

### Solution

Create unified custom fields for ALL product types:
- `raven_variant_options`: JSON array for Raven weapons/caliber kits (Varianten)
- `raven_has_variants`: Boolean flag
- Update templates to check both Snigel and Raven custom fields
- Use label "Variante:" on frontend (NOT "Farbe:")

---

## Task 1: Create Custom Field Set in Shopware Admin

**Files:**
- Shopware Admin UI (manual)

**Step 1: Access Custom Fields**

Navigate to: Settings > System > Custom Fields

**Step 2: Create new Custom Field Set**

- Technical name: `raven_product_options`
- Label: `Raven Product Options`
- Assign to: Products

**Step 3: Add Custom Fields**

Field 1:
- Technical name: `raven_variant_options`
- Type: Text (JSON)
- Label: `Varianten-Optionen (JSON)`

Field 2:
- Technical name: `raven_has_variants`
- Type: Switch (Boolean)
- Label: `Hat Varianten`

**Step 4: Save the Custom Field Set**

Click Save.

**Verification:**
- Go to any product in Admin
- Check "Additional fields" tab shows new fields

---

## Task 2: Remove Hardcoded Colors from index.html.twig

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/product-detail/index.html.twig:143-171`

**Step 1: Read the current hardcoded section**

Current code (lines 143-171):
```twig
{# Color Variants - using product media images #}
{% if page.product.media|length > 1 %}
<div class="mb-5">
    <p class="text-sm text-gray-600 mb-3" style="font-family: 'Inter', sans-serif;">
        Farbe : <span id="selected-color-name" class="font-semibold text-gray-900">Black</span>
    </p>
    <div class="flex flex-wrap gap-2">
        {% set colorNames = ['Graphite Black', 'Flat Dark Earth', 'Northern Lights', 'Olive Drab Green', 'Sniper Grey'] %}
        {% for media in page.product.media %}
        ...
        {% endfor %}
    </div>
</div>
{% endif %}
```

**Step 2: Replace with dynamic custom field logic**

Replace lines 143-171 with:
```twig
{# VARIANTEN - DYNAMIC from custom fields #}
{% set hasVariants = page.product.translated.customFields.raven_has_variants|default(false) or page.product.translated.customFields.snigel_has_color_variants|default(false) %}
{% set variantOptions = page.product.translated.customFields.raven_variant_options|default(page.product.translated.customFields.snigel_color_options|default([])) %}

{% if hasVariants and variantOptions|length > 0 %}
<div class="mb-5">
    <p class="text-sm text-gray-600 mb-3" style="font-family: 'Inter', sans-serif;">
        Variante: <span id="selected-variant-name" class="font-semibold text-gray-900">{{ variantOptions[0].name|default('Standard') }}</span>
    </p>
    <div class="flex flex-wrap gap-2">
        {% for variantOption in variantOptions %}
        {% set variantName = variantOption.name|default('Variante ' ~ loop.index) %}
        {% set variantImageUrl = variantOption.imageUrl|default('') %}

        {% if variantImageUrl %}
        <button class="variant-image-swatch {% if loop.first %}selected{% endif %}"
                data-variant-name="{{ variantName }}"
                title="{{ variantName }}"
                onclick="document.getElementById('product-main-image').src='{{ variantImageUrl }}';
                         document.getElementById('zoom-result-img').src='{{ variantImageUrl }}';
                         document.getElementById('selected-variant-name').textContent='{{ variantName }}';
                         document.querySelectorAll('.variant-image-swatch').forEach(el => el.classList.remove('selected'));
                         this.classList.add('selected');">
            <img src="{{ variantImageUrl }}" alt="{{ variantName }}" class="w-full h-full object-contain">
            <span class="swatch-check">
                <svg viewBox="0 0 24 24" fill="none" class="w-4 h-4">
                    <path d="M5 13l4 4L19 7" stroke="#374151" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
        </button>
        {% endif %}
        {% endfor %}
    </div>
</div>
{% endif %}
```

**Step 3: Verify no syntax errors**

Run: `docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console theme:compile"`

Expected: No errors

**Step 4: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/product-detail/index.html.twig
git commit -m "fix: replace hardcoded colors with dynamic custom fields in product-detail

- Remove hardcoded colorNames array
- Use raven_color_options and snigel_color_options custom fields
- Support both Snigel and Raven product types"
```

---

## Task 3: Update cms-element-buy-box.html.twig for Unified Logic

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/element/cms-element-buy-box.html.twig:103-178`

**Step 1: Read current logic**

Current code has:
- Lines 103-137: Snigel products with custom fields (WORKING)
- Lines 138-178: Non-Snigel products with filename extraction (BROKEN)

**Step 2: Replace with unified dynamic logic**

Replace lines 103-178 with:
```twig
{# VARIANTEN Logic - UNIFIED DYNAMIC from custom fields #}
{% set hasVariants = product.translated.customFields.raven_has_variants|default(false) or product.translated.customFields.snigel_has_color_variants|default(false) %}
{% set variantOptions = product.translated.customFields.raven_variant_options|default(product.translated.customFields.snigel_color_options|default([])) %}

{% if hasVariants and variantOptions|length > 1 %}
    <div class="raven-variant-section">
        <p class="raven-variant-label">
            Variante: <span id="selected-variant-name" class="raven-variant-value">{{ variantOptions[0].name|default('Standard') }}</span>
        </p>
        <div class="raven-variant-swatches">
            {% for variantOption in variantOptions %}
            {% set variantName = variantOption.name|default('Variante ' ~ loop.index) %}
            {% set variantImageUrl = variantOption.imageUrl|default('') %}

            {% if variantImageUrl %}
            <button class="raven-image-swatch {% if loop.first %}selected{% endif %}"
                    data-variant-name="{{ variantName }}"
                    data-image-url="{{ variantImageUrl }}"
                    title="{{ variantName }}"
                    onclick="
                        document.getElementById('raven-main-product-image').src='{{ variantImageUrl }}';
                        document.getElementById('raven-zoom-result-img').src='{{ variantImageUrl }}';
                        document.getElementById('selected-variant-name').textContent='{{ variantName }}';
                        document.querySelectorAll('.raven-image-swatch').forEach(el => el.classList.remove('selected'));
                        this.classList.add('selected');
                        try { localStorage.setItem('raven_variant_{{ product.id }}', JSON.stringify({variant: '{{ variantName }}', image: '{{ variantImageUrl }}'})); } catch(e) {}">
                <img src="{{ variantImageUrl }}" alt="{{ variantName }}">
            </button>
            {% endif %}
            {% endfor %}
        </div>
    </div>
{% endif %}
```

**Step 3: Verify compilation**

Run: `docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console theme:compile && bin/console cache:clear"`

Expected: No errors

**Step 4: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/element/cms-element-buy-box.html.twig
git commit -m "fix: unify color selector logic for all product types

- Remove unreliable filename-based color extraction
- Use unified raven_color_options and snigel_color_options custom fields
- Same dynamic approach for Snigel, Raven weapons, and caliber kits"
```

---

## Task 4: Create PHP Script to Populate Raven Product Custom Fields

**Files:**
- Create: `scripts/populate-raven-variant-options.php`

**Step 1: Create the population script**

```php
<?php
/**
 * Populate raven_variant_options custom field for Raven weapons and caliber kits
 *
 * This script reads product media and creates proper variant option entries
 * Run: php scripts/populate-raven-variant-options.php
 */

require_once __DIR__ . '/shopware-api-client.php';

// Variant name mapping based on common Raven weapon variant names
$variantMapping = [
    'graphite' => 'Graphite Black',
    'black' => 'Graphite Black',
    'fde' => 'Flat Dark Earth',
    'flat-dark-earth' => 'Flat Dark Earth',
    'earth' => 'Flat Dark Earth',
    'northern' => 'Northern Lights',
    'lights' => 'Northern Lights',
    'olive' => 'Olive Drab Green',
    'odg' => 'Olive Drab Green',
    'green' => 'Olive Drab Green',
    'sniper' => 'Sniper Grey',
    'grey' => 'Sniper Grey',
    'gray' => 'Sniper Grey',
];

function detectVariantFromFilename($filename, $variantMapping) {
    $filenameLower = strtolower($filename);

    foreach ($variantMapping as $key => $variantName) {
        if (strpos($filenameLower, $key) !== false) {
            return $variantName;
        }
    }

    return null;
}

echo "=== Raven Product Variant Options Population Script ===\n\n";

// Get all products that are NOT Snigel (manufacturer != Snigel)
$products = shopwareApiGet('/api/product', [
    'filter' => [
        ['type' => 'not', 'queries' => [
            ['type' => 'contains', 'field' => 'manufacturer.name', 'value' => 'Snigel']
        ]]
    ],
    'associations' => [
        'media' => ['sort' => [['field' => 'position', 'order' => 'ASC']]],
        'manufacturer' => []
    ],
    'limit' => 500
]);

$updated = 0;
$skipped = 0;

foreach ($products['data'] as $product) {
    $productName = $product['name'] ?? $product['translated']['name'] ?? 'Unknown';
    $productId = $product['id'];
    $media = $product['media'] ?? [];

    // Skip products with less than 2 media items (no variants)
    if (count($media) < 2) {
        echo "SKIP: {$productName} - only " . count($media) . " media item(s)\n";
        $skipped++;
        continue;
    }

    // Build variant options from media
    $variantOptions = [];
    $usedVariants = [];

    foreach ($media as $index => $mediaItem) {
        $mediaUrl = $mediaItem['media']['url'] ?? '';
        $mediaFilename = $mediaItem['media']['fileName'] ?? '';

        if (!$mediaUrl) continue;

        // Try to detect variant from filename
        $detectedVariant = detectVariantFromFilename($mediaFilename, $variantMapping);

        // If no variant detected, use position-based default
        if (!$detectedVariant) {
            $defaultVariants = ['Graphite Black', 'Flat Dark Earth', 'Northern Lights', 'Olive Drab Green', 'Sniper Grey'];
            $detectedVariant = $defaultVariants[$index] ?? 'Variante ' . ($index + 1);
        }

        // Avoid duplicates
        if (in_array($detectedVariant, $usedVariants)) {
            $detectedVariant = $detectedVariant . ' ' . ($index + 1);
        }
        $usedVariants[] = $detectedVariant;

        $variantOptions[] = [
            'name' => $detectedVariant,
            'value' => strtolower(str_replace(' ', '-', $detectedVariant)),
            'imageUrl' => $mediaUrl
        ];
    }

    if (count($variantOptions) > 1) {
        // Update product custom fields
        $updatePayload = [
            'customFields' => [
                'raven_variant_options' => json_encode($variantOptions),
                'raven_has_variants' => true
            ]
        ];

        $result = shopwareApiPatch("/api/product/{$productId}", $updatePayload);

        if ($result) {
            echo "UPDATED: {$productName} - " . count($variantOptions) . " Varianten\n";
            foreach ($variantOptions as $opt) {
                echo "  - {$opt['name']}\n";
            }
            $updated++;
        } else {
            echo "ERROR: Failed to update {$productName}\n";
        }
    } else {
        echo "SKIP: {$productName} - insufficient distinct variants\n";
        $skipped++;
    }
}

echo "\n=== Summary ===\n";
echo "Updated: {$updated} products\n";
echo "Skipped: {$skipped} products\n";
```

**Step 2: Run the script**

Run: `php scripts/populate-raven-variant-options.php`

Expected: Products updated with variant options

**Step 3: Verify in Shopware Admin**

- Go to any Raven weapon product
- Check "Additional fields" tab
- Verify `raven_variant_options` contains JSON array
- Verify `raven_has_variants` is true

**Step 4: Commit**

```bash
git add scripts/populate-raven-variant-options.php
git commit -m "feat: add script to populate Raven product variant options

- Detects variants from media filenames
- Creates proper JSON structure matching Snigel format
- Sets raven_has_variants flag for eligible products"
```

---

## Task 5: Deploy to Production

**Files:**
- All modified theme files

**Step 1: Copy theme to production**

Run:
```bash
scp -r shopware-theme/RavenTheme root@77.42.19.154:/tmp/
```

**Step 2: Deploy theme on server**

Run:
```bash
ssh root@77.42.19.154 "docker cp /tmp/RavenTheme shopware-chf:/var/www/html/custom/plugins/ && docker exec shopware-chf bash -c 'cd /var/www/html && bin/console theme:compile && bin/console cache:clear'"
```

**Step 3: Run population script on production**

Copy and run the population script on the production server.

**Step 4: Verify on production**

- Visit https://ortak.ch
- Navigate to a Raven weapon product
- Verify color selector shows dynamic options
- Test selecting different colors

---

## Task 6: Manual Verification Checklist

**Step 1: Test Snigel product**

- Navigate to any Snigel product
- Verify color selector appears
- Click each color swatch
- Verify main image changes
- Verify color name updates

**Step 2: Test Raven weapon product**

- Navigate to any Raven weapon (e.g., RAPAX rifle)
- Verify color selector appears (after running population script)
- Click each color swatch
- Verify main image changes
- Verify color name updates

**Step 3: Test caliber kit product**

- Navigate to any caliber kit
- Verify color selector appears (after running population script)
- Test color switching

**Step 4: Test cart flow**

- Add product with selected color to cart
- Verify color shows in off-canvas cart
- Proceed to checkout
- Verify color saved in order

---

---

## Task 7: Add SIZE SELECTOR Support

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/content/product-detail.html.twig`

### Current State (Snigel Size Options - Already Working)

The Snigel products already have size options stored in:
- `snigel_size_options`: JSON array `[{name: "S", value: "s"}, {name: "M", value: "m"}, ...]`
- `snigel_has_sizes`: Boolean flag
- `snigel_variants`: JSON with price per variant `[{name: "S", sellingPriceCHF: 240}, ...]`

### Step 1: Create Unified Size Custom Fields

In Shopware Admin, add to the `raven_product_options` custom field set:

| Technical Name | Type | Label |
|---------------|------|-------|
| `raven_size_options` | Text (JSON) | Grössen-Optionen (JSON) |
| `raven_has_sizes` | Switch | Hat Grössen |
| `raven_variant_prices` | Text (JSON) | Preise pro Variante (JSON) |

### Step 2: Size Options JSON Structure

```json
[
  {"name": "S", "value": "s"},
  {"name": "M", "value": "m"},
  {"name": "L", "value": "l"},
  {"name": "XL", "value": "xl"},
  {"name": "XXL", "value": "xxl"}
]
```

### Step 3: Variant Prices JSON Structure (optional - for dynamic pricing)

```json
[
  {"name": "S", "sellingPriceCHF": 240},
  {"name": "M", "sellingPriceCHF": 240},
  {"name": "L", "sellingPriceCHF": 260},
  {"name": "XL", "sellingPriceCHF": 280}
]
```

### Step 4: Unified Size Selector Twig Template

Add this code block in the product-detail templates:

```twig
{# ========== SIZE SELECTOR - Dynamic from custom fields ========== #}
{% set hasSizeVariants =
    page.product.translated.customFields.raven_has_sizes|default(false) or
    page.product.translated.customFields.snigel_has_sizes|default(false)
%}
{% set sizeOptionsRaw =
    page.product.translated.customFields.raven_size_options|default(
        page.product.translated.customFields.snigel_size_options|default('[]')
    )
%}
{% set sizeOptions = sizeOptionsRaw is iterable ? sizeOptionsRaw : [] %}
{% set variantPricesRaw =
    page.product.translated.customFields.raven_variant_prices|default(
        page.product.translated.customFields.snigel_variants|default('[]')
    )
%}
{% set variantPrices = variantPricesRaw is iterable ? variantPricesRaw : [] %}

{% if hasSizeVariants and sizeOptions|length > 0 %}
<div class="mb-8" id="size-variant-container"
     data-sizes="{{ sizeOptions|json_encode|e('html_attr') }}"
     data-variants="{{ variantPrices|json_encode|e('html_attr') }}">
    <p class="text-sm text-gray-500 mb-3" style="font-family: 'Inter', sans-serif; text-transform: uppercase; letter-spacing: 0.05em;">
        Grösse: <span id="selected-size-name" class="font-semibold text-gray-800">{{ sizeOptions[0].name|default('Standard') }}</span>
    </p>
    <div class="flex flex-wrap gap-3" id="size-swatches">
        {% for sizeData in sizeOptions %}
            <button type="button"
                    class="size-tag {% if loop.first %}selected{% endif %}"
                    data-size-name="{{ sizeData.name }}"
                    data-size-value="{{ sizeData.value|default(sizeData.name|lower) }}"
                    title="{{ sizeData.name }}">
                {{ sizeData.name }}
            </button>
        {% endfor %}
    </div>
</div>
{% endif %}
```

### Step 5: CSS for Size Tags

```css
.size-tag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 48px;
    padding: 0.5rem 1rem;
    background: #F3F4F6;
    border: 2px solid #E5E7EB;
    border-radius: 8px;
    font-family: 'Inter', sans-serif;
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    transition: all 0.2s ease;
}

.size-tag:hover {
    border-color: #9CA3AF;
    background: #E5E7EB;
}

.size-tag.selected {
    border-color: #D4A847;
    background: #FFFBEB;
    color: #92400E;
    font-weight: 600;
}
```

### Step 6: JavaScript for Dynamic Price Update

The existing JavaScript in product-detail.html.twig already handles:
- Size selection click events
- Dynamic price update from `variantPrices`
- Saving selected size to localStorage
- Updating hidden form inputs for cart

---

## Task 8: Frontend Display Order

The product page should display options in this order:
1. **Color selector** (if product has colors)
2. **Size selector** (if product has sizes)
3. **Quantity selector**
4. **Add to Cart button**

---

## Summary

| File | Change |
|------|--------|
| `index.html.twig:143-171` | Replace hardcoded variants with dynamic custom fields |
| `cms-element-buy-box.html.twig:103-178` | Unify variant logic for all product types |
| `product-detail.html.twig` | Add unified size selector |
| `scripts/populate-raven-variant-options.php` | New script to populate custom fields |
| Shopware Admin | Create custom fields (see below) |

**Custom Fields to Create:**

| Field | Type | Purpose | Frontend Label |
|-------|------|---------|----------------|
| `raven_has_variants` | Boolean | Enable variant selector | - |
| `raven_variant_options` | JSON | Array of `{name, imageUrl}` | **Variante:** |
| `raven_has_sizes` | Boolean | Enable size selector | - |
| `raven_size_options` | JSON | Array of `{name, value}` | **Grösse:** |
| `raven_variant_prices` | JSON | Array of `{name, sellingPriceCHF}` | (dynamic pricing) |

**Frontend Labels:**
- Variant selector: **"Variante:"** (NOT "Farbe:")
- Size selector: **"Grösse:"**

**Result:** All product pages will use dynamic VARIANT (Varianten) AND SIZE (Grösse) selectors from custom fields, with optional dynamic pricing per variant.
