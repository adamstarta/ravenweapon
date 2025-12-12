# Snigel Scraper Update - Color Variants & Subcategories

**Date:** 2025-12-12
**Status:** Ready for Implementation
**Estimated Products:** ~193 Snigel products

## Problem Statement

The current Snigel scraper has two issues:

1. **Fake "Graphite Black" Property**: When products have multiple images, the system auto-creates a fake "Farbe: Graphite Black" property option. This is incorrect - not all products have color variants.

2. **Missing Color Variants**: Some products DO have actual color variants (selectable via dropdown on the product page), but these are not being scraped.

3. **Missing Subcategories**: Products should be assigned to proper Snigel subcategories (Backpacks, Chest Rigs, etc.) but are all dumped into the main "Snigel" category.

## Solution Overview

Update the scraping pipeline to:
1. Visit each product page and detect if there's a color dropdown
2. If dropdown exists, scrape all color options and their associated images
3. Extract the product's category from the page
4. Store this data properly for Shopware import with correct variant structure

## Implementation Plan

### Phase 1: Update Product Page Scraper

**File:** `scripts/snigel-variants-scraper.js` (new file)

**Purpose:** Visit each product page and extract:
- Color variant options from dropdown (if present)
- Variant-specific image URLs
- Product subcategory

```javascript
// Key extraction logic to add:
const pageData = await page.evaluate(() => {
    const data = {
        hasColorVariants: false,
        colorOptions: [],
        subcategory: null
    };

    // Check for color/variant dropdown
    const variantSelect = document.querySelector('select[name*="attribute"], .variations select');
    if (variantSelect) {
        data.hasColorVariants = true;
        const options = variantSelect.querySelectorAll('option');
        options.forEach(opt => {
            if (opt.value && opt.value !== '') {
                data.colorOptions.push({
                    name: opt.textContent.trim(),
                    value: opt.value
                });
            }
        });
    }

    // Get subcategory from breadcrumb or category link
    const categoryLink = document.querySelector('.product_meta a[href*="product-category"]');
    if (categoryLink) {
        data.subcategory = categoryLink.textContent.trim();
    }

    return data;
});
```

**Output:** `scripts/snigel-data/products-with-variants.json`

### Phase 2: Update Shopware Import Script

**File:** `scripts/shopware-import-snigel-variants.php` (new file)

**Changes from current `shopware-import-chf.php`:**

1. **Create Snigel Subcategories First:**
   - Backpacks / Ryggsäckar
   - Chest Rigs
   - Pouches
   - Belts & Harnesses
   - Accessories
   - etc. (based on scraped categories)

2. **Handle Products WITH Color Variants:**
   ```php
   // For products with color variants:
   // 1. Create parent product (configurator product)
   // 2. Create "Farbe" property group if not exists
   // 3. Create color options (Graphite Black, Olive Drab, etc.)
   // 4. Create variant products for each color
   // 5. Assign correct images to each variant
   ```

3. **Handle Products WITHOUT Color Variants:**
   ```php
   // For products without variants:
   // - Create simple product (no configurator)
   // - Attach all images as gallery (no fake color property)
   ```

### Phase 3: Image Association

**File:** `scripts/upload-snigel-variant-images.php` (new file)

**Logic:**
- For variant products: Match images to specific color variants
- For simple products: Upload as gallery images
- Use image filename patterns or scraping data to determine which image goes with which variant

## File Structure

```
scripts/
├── snigel-variants-scraper.js          # NEW - Scrapes color variants
├── shopware-import-snigel-variants.php # NEW - Imports with proper variants
├── upload-snigel-variant-images.php    # NEW - Uploads variant images
└── snigel-data/
    ├── products.json                    # Existing base product data
    ├── products-with-descriptions.json  # Existing enriched data
    └── products-with-variants.json      # NEW - Variant data
```

## Detailed Tasks

### Task 1: Create Variant Scraper (snigel-variants-scraper.js)

1. Load existing `products-with-descriptions.json`
2. For each product:
   - Navigate to product URL
   - Check for variant dropdown (`.variations select` or similar)
   - If found:
     - Extract all color options
     - For each color option, trigger selection and capture image change
   - Extract subcategory from page
   - Save enriched data

**Key Code Section:**
```javascript
// Detect variant selector
const hasVariants = await page.evaluate(() => {
    return !!document.querySelector('table.variations select, .variation-selector');
});

if (hasVariants) {
    // Get all color options
    const options = await page.$$eval('table.variations select option', opts =>
        opts.filter(o => o.value).map(o => ({
            name: o.textContent.trim(),
            value: o.value
        }))
    );

    // For each option, select it and capture the image
    for (const option of options) {
        await page.selectOption('table.variations select', option.value);
        await page.waitForTimeout(1000); // Wait for image to update

        const variantImage = await page.$eval('.woocommerce-product-gallery__image img',
            img => img.src
        );

        option.imageUrl = variantImage;
    }
}
```

### Task 2: Create Subcategory Mapping

Map Snigel categories to Shopware subcategories:

```php
$categoryMapping = [
    'Backpacks' => 'Snigel > Rucksäcke',
    'Ryggsäckar' => 'Snigel > Rucksäcke',
    'Chest Rigs' => 'Snigel > Chest Rigs',
    'Pouches' => 'Snigel > Taschen',
    'Belts' => 'Snigel > Gürtel',
    'Accessories' => 'Snigel > Zubehör',
    // ... add more as discovered during scraping
];
```

### Task 3: Create Variant Import Script

```php
// Pseudo-code for variant import
foreach ($products as $product) {
    if ($product['hasColorVariants']) {
        // Create configurator group "Farbe" if needed
        $colorGroupId = getOrCreatePropertyGroup('Farbe');

        // Create parent product
        $parentId = createParentProduct($product, $colorGroupId);

        // Create variant for each color
        foreach ($product['colorOptions'] as $color) {
            $optionId = getOrCreatePropertyOption($colorGroupId, $color['name']);
            createVariantProduct($parentId, $optionId, $color);
        }
    } else {
        // Create simple product (no variants)
        createSimpleProduct($product);
    }
}
```

### Task 4: Test with Sample Products

Before running on all 193 products:
1. Test scraper on 5 products (mix of variant/non-variant)
2. Verify variant data structure
3. Test import on staging/localhost
4. Verify frontend display shows correct variant switching

## Verification Steps

1. **After Scraping:**
   - Check `products-with-variants.json` for correct structure
   - Verify color options match what's on the Snigel website
   - Verify subcategories are captured

2. **After Import:**
   - Products with variants should show color selector in Shopware frontend
   - Products without variants should have no "Farbe" property
   - Each variant should have its correct image
   - Products should appear in correct subcategories

3. **Frontend Test:**
   - Visit Snigel category page
   - Click on a product with variants
   - Verify color selector works
   - Verify image changes when selecting different color

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Snigel website structure changes | Add robust error handling, fallback selectors |
| Rate limiting | Add delays between requests (2-3 seconds) |
| Missing variant images | Log missing images, handle gracefully |
| Category name mismatches | Create category mapping lookup table |

## Execution Order

1. Run `snigel-variants-scraper.js` to gather variant data
2. Review output JSON for accuracy
3. Run `shopware-import-snigel-variants.php` to import products
4. Run `upload-snigel-variant-images.php` to upload images
5. Clear Shopware cache
6. Verify on frontend

## Time Estimate

- Phase 1 (Scraper): ~2-3 hours (193 products @ ~30 sec each = 1.5 hours + testing)
- Phase 2 (Import Script): ~1-2 hours development
- Phase 3 (Image Upload): ~1-2 hours development
- Testing & Fixes: ~1-2 hours

**Total:** ~6-9 hours
