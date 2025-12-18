# Fix Product Categories & SEO URLs

## Problem Statement

Products are assigned to multiple categories (up to 7!), causing:
1. Confusing SEO URLs that don't match navigation path
2. Breadcrumbs showing unexpected category paths
3. Poor user experience when clicking products from category pages

**Example:** User navigates to `/koerperschutz/westen-chest-rigs/`, clicks "Covert equipment vest", but URL shows `/ausruestung/zubehoer-sonstiges/taktische-ausruestung/...` with different breadcrumb.

## Analysis

- 336 total products
- 323 products (98%) have multiple category assignments
- Common pattern: products in "Alle Produkte" + parent category + specific subcategory
- Some products in 5-7 categories

## Solution: One Product = One Category

### Approach

1. For each product, identify the **deepest/most specific** category
2. Remove all other category assignments
3. Set main_category to the single remaining category
4. Regenerate SEO URLs based on the new single category

### Category Selection Logic

Priority order for determining the "correct" category:
1. **Deepest category** (most levels in breadcrumb path)
2. **Product type matching** (vests → Westen, pouches → Halter & Taschen)
3. **Exclude generic categories** (skip "Alle Produkte", top-level "Ausrüstung")

### Implementation Steps

#### Phase 1: Preparation Script
- Create script to analyze and propose category assignments
- Generate CSV/report showing: Product | Current Categories | Proposed Single Category
- Allow manual review before execution

#### Phase 2: Execute Category Cleanup
- Remove products from all categories except the chosen one
- Update main_category assignment in `main_category` table
- Log all changes for rollback if needed

#### Phase 3: Regenerate SEO URLs
- Delete existing product SEO URLs
- Create new SEO URLs based on single category path
- Format: `/{category-path}/{product-slug}`

#### Phase 4: Clear Cache & Verify
- Clear Shopware cache
- Verify breadcrumbs match URL paths
- Test navigation from category → product

## Technical Details

### API Endpoints Used
- `POST /search/product` - Get products with categories
- `POST /search/category` - Get category hierarchy
- `PATCH /product/{id}` - Update product categories
- `DELETE /seo-url/{id}` - Remove old SEO URLs
- `POST /seo-url` - Create new SEO URLs

### Key Decisions

1. **"Alle Produkte" category**: Remove from all products (it's a virtual listing, not a real category)
2. **Parent categories**: Products should NOT be in parent categories (e.g., not in "Ausrüstung" if already in "Ausrüstung > Körperschutz > Westen")
3. **Multiple valid categories**: When product fits multiple leaf categories, prefer the one with product-type keywords (vest, pouch, backpack, etc.)

## Rollback Plan

1. Backup current category assignments before execution
2. Store old SEO URLs for restoration
3. Script to restore from backup if needed

## Expected Outcome

- Each product in exactly ONE category
- SEO URL matches category breadcrumb path
- Consistent user experience: Category page → Product page → Same breadcrumb trail
- Example: `/koerperschutz/westen-chest-rigs/` → click product → `/koerperschutz/westen-chest-rigs/covert-equipment-vest-12`
