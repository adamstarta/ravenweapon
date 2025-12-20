# SEO URL & Breadcrumb Native Fix - Design Document

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Use Shopware's native SEO URL and breadcrumb systems instead of custom hardcoded solutions.

**Architecture:** Fix underlying data (main_category, seo_url tables), remove custom template logic, rely on Shopware's built-in functionality.

**Tech Stack:** Shopware 6.6, PHP, Twig, MySQL

---

## Problem Statement

Products imported via API bypassed Shopware's automatic SEO URL generation:
- Products use technical URLs (`/detail/uuid`) instead of SEO-friendly URLs
- Breadcrumbs show phantom entries (product names appearing as categories)
- Many products lack `main_category` entries in the database
- Many products lack `seo_url` entries for proper URL resolution
- Custom template code tries to work around missing data with hardcoded fallbacks

## Solution Overview

### Part 1: Data Fix
Ensure every product has:
- Correct `main_category` entry (the primary category for breadcrumbs)
- Generated `seo_url` entry (created by Shopware's indexer)

### Part 2: Code Cleanup
Remove custom workarounds:
- Simplify `ProductDetailSubscriber.php`
- Remove hardcoded URL building in templates
- Use Shopware's native `seoUrl()` and `sw_breadcrumb` functions

### Part 3: Verification
Test across all categories using Playwright automation.

## Data Fix Strategy

### Step 1: Set main_category for all products

For each product, determine its primary category:
- If product is in only one category → use that category
- If product is in multiple categories → use the deepest (most specific) category
- Filter by sales channel (CHF sales channel ID)

```sql
-- main_category table structure:
-- product_id (the product)
-- category_id (the primary category)
-- sales_channel_id (your CHF channel)
```

### Step 2: Trigger Shopware's SEO URL generation

```bash
bin/console dal:refresh:index    # Refresh all indexes
bin/console seo:generate         # Generate missing SEO URLs (if available in version)
```

### Step 3: Verify SEO URL template in Admin

Check: Admin → Settings → SEO → Product URLs template

## Code Cleanup Strategy

### Files to Modify:

1. **`ProductDetailSubscriber.php`**
   - Current: Complex logic to load breadcrumbCategories extension
   - New: Minimal version that only ensures seoCategory is set correctly

2. **`page/content/product-detail.html.twig`**
   - Current: 100+ lines of custom URL path parsing, slugifying, hardcoded category mappings
   - New: Use Shopware's native breadcrumb system

### What to Remove:
- Hardcoded `categoryNames` mappings (100+ entries)
- Custom slugify logic (`|replace({'ä': 'ae'...}`)
- Multiple fallback URL building strategies
- Manual path parsing from `sw-original-request-uri`

### What to Keep:
- Shopware's native `{% sw_extends %}`
- Shopware's native `{{ seoUrl() }}` function
- Shopware's native breadcrumb blocks

## Expected Results

| Component | Before | After |
|-----------|--------|-------|
| Product URLs | `/detail/uuid` | `/munition/product-name/` |
| Breadcrumbs | Phantom entries, 404 links | Clean hierarchy, working links |
| Template code | 100+ lines hardcoded | Native Shopware functions |
| Maintenance | High (manual mappings) | Low (automatic) |

## Success Criteria

- Zero technical URLs (`/detail/uuid`) visible to users
- Zero phantom breadcrumb entries
- All breadcrumb links return 200 (no 404s)
- Consistent behavior across all categories
- No hardcoded category/URL mappings in templates

## Test Cases

1. **Munition Products**: 223 Remington, 300 AAC Blackout
2. **Waffen Products**: All weapons and subcategories
3. **Other Categories**: Ausrüstung, Zubehör, Zielhilfen, Raven Caliber Kit

---

*Created: 2025-12-18*
*Status: Ready for implementation*
