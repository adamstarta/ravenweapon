# Snigel Scraper Enhancement Design

**Date:** 2026-01-15
**Status:** Approved

## Overview

Enhance the existing `snigel-scraper.js` to capture full product variant data (colors, sizes, types) with their associated images, then compare against staging data.

## Current State

The current scraper captures:
- Product name
- Article number
- Single colour (only first one)

## Requirements

1. Capture ALL dropdown options (Colour, Size, Type, etc.)
2. For each combination, capture the image filename
3. Compare scraped data with staging to find missing variants
4. Keep existing retry-forever logic (user requirement)

## Output Structure

### Scraped Product

```json
{
  "snigel_name": "Tactical coverall 09F",
  "snigel_article_no": "08-01056-14-XXX",
  "snigel_url": "https://products.snigel.se/product/tactical-coverall-09f/",
  "options": {
    "Colour": ["Grey", "Black"],
    "Size": ["Briefs", "Coverall", "Vest", "COMPLETE"]
  },
  "variant_images": [
    { "selection": {"Colour": "Grey", "Size": "Briefs"}, "image": "coverall-grey-briefs.jpg" },
    { "selection": {"Colour": "Grey", "Size": "Coverall"}, "image": "coverall-grey-coverall.jpg" },
    { "selection": {"Colour": "Black", "Size": "Briefs"}, "image": "coverall-black-briefs.jpg" }
  ]
}
```

### Comparison Output

```json
{
  "product": "30L Mission Backpack",
  "snigel_colors": ["Black", "Grey", "Multicam"],
  "staging_colors": ["Black", "Grey"],
  "missing_colors": ["Multicam"],
  "snigel_sizes": ["S", "M", "L", "XL"],
  "staging_sizes": ["S", "M", "L"],
  "missing_sizes": ["XL"],
  "image_comparison": [
    { "color": "Black", "snigel_image": "backpack-black.jpg", "staging_has": true },
    { "color": "Multicam", "snigel_image": "backpack-multicam.jpg", "staging_has": false }
  ]
}
```

## Scraping Logic

```
For each product page:
1. Load page (with retry-forever logic)
2. Get product name & article number
3. Find ALL dropdown selects on page
4. Extract options from each dropdown
5. Generate all combinations (e.g., 4 colors × 3 sizes = 12)
6. For each combination:
   a. Select each dropdown value
   b. Wait for image to update (500ms)
   c. Capture current main image filename
   d. Store: { selection: {...}, image: "filename.jpg" }
7. Save progress after each product (resume-friendly)
```

## New Helper Functions

| Function | Purpose |
|----------|---------|
| `getDropdownOptions(page)` | Find all dropdowns, return options map |
| `generateCombinations(options)` | Create all possible combinations |
| `selectAndCapture(page, combo)` | Select dropdown values, get image |
| `getMainImageFilename(page)` | Extract current image filename |

## CLI Usage

```bash
# Full scrape with variants
node snigel-scraper.js

# Compare with staging (after scrape)
node snigel-scraper.js --compare
```

## Output Files

- `snigel-full-scrape-YYYY-MM-DD.json` - Raw scraped data
- `snigel-comparison-YYYY-MM-DD.json` - Comparison results

## Performance Estimate

- 196 products
- ~5 combinations average per product = ~1000 selections
- ~2 seconds per selection = ~33 minutes total

## Features Preserved

- Retry forever until page loads
- Progress save/resume capability
- Block images/analytics for speed
- Headless mode
- Login handling
