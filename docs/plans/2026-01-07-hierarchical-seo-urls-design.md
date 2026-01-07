# Hierarchical Category SEO URLs - Design Document

**Date:** 2026-01-07
**Status:** Ready for Implementation

## Problem Statement

Category SEO URLs are flat while product SEO URLs are hierarchical, causing a mismatch in breadcrumb navigation:

| Element | Current URL | Expected URL |
|---------|-------------|--------------|
| Product | `/ausruestung/taschen-transport/bags-backpacks/100l-backpack-2.0` | Same (correct) |
| Category | `/bags-backpacks/` | `/ausruestung/taschen-transport/bags-backpacks/` |

When users click breadcrumb links, they land on flat category URLs that don't reflect the hierarchy shown in breadcrumbs.

## Solution: Update SEO URL Template

Update the category SEO URL template in Shopware admin to generate hierarchical paths.

## Implementation Steps

### Step 1: Access SEO URL Templates

1. Log into Shopware Admin
2. Navigate to **Settings** → **SEO** (under Shop section)
3. Click on **SEO URL templates** tab

### Step 2: Update Category Template

Find the **Category** entity row and update the template.

**Current template (likely):**
```twig
{{ category.translated.name|slugify }}/
```

**New template:**
```twig
{% for breadcrumb in category.seoBreadcrumb|slice(1) %}{{ breadcrumb|slugify }}/{% endfor %}
```

Or with German character handling:
```twig
{% for breadcrumb in category.seoBreadcrumb|slice(1) %}{{ breadcrumb|lower|replace({' ': '-', 'ä': 'ae', 'ö': 'oe', 'ü': 'ue', 'ß': 'ss', '&': '-', '/': '-'})|trim('-') }}/{% endfor %}
```

**Note:** The `|slice(1)` skips the root "Home" category.

### Step 3: Regenerate SEO URLs

1. Click **Save** on the SEO URL template
2. Click **Regenerate SEO URLs** or run via CLI:
   ```bash
   bin/console dal:refresh:index
   bin/console seo:url:regenerate
   ```

### Step 4: Verify Redirects

Shopware automatically keeps old URLs as non-canonical redirects. Verify:
1. Old flat URLs (`/bags-backpacks/`) redirect to new hierarchical URLs
2. HTTP 301 redirects are in place

### Step 5: Test Breadcrumbs

Test the following pages:
- [ ] `/ausruestung/` - Category listing
- [ ] `/ausruestung/taschen-transport/bags-backpacks/` - Subcategory
- [ ] Product detail pages - Breadcrumb links

## Code Analysis

### Files That Use SEO URLs

| File | Method | Will Work? |
|------|--------|------------|
| `page/product-detail/index.html.twig` | `category.seoUrls[].seoPathInfo` | Yes |
| `page/navigation/index.html.twig` | `seoUrl('frontend.navigation.page', {...})` | Yes |
| `layout/breadcrumb.html.twig` | `seoUrls` association + fallback | Yes |

All files read SEO URLs from database, so updating the template will automatically fix all breadcrumb links.

### ProductDetailSubscriber.php

Loads categories with SEO URLs filtered by:
- `languageId` (current language)
- `isCanonical = true`

No code changes needed.

## Database Impact

**Table:** `seo_url`

| Column | Before | After |
|--------|--------|-------|
| `seo_path_info` | `bags-backpacks/` | `ausruestung/taschen-transport/bags-backpacks/` |
| `is_canonical` | 1 | 1 |

Old URLs remain with `is_canonical = 0` for redirects.

## Rollback Plan

If issues occur:
1. Revert template to original: `{{ category.translated.name|slugify }}/`
2. Regenerate SEO URLs
3. Old hierarchical URLs will redirect to flat URLs

## Testing Checklist

- [ ] Category SEO URLs include parent paths
- [ ] Product detail breadcrumb links work correctly
- [ ] Category page breadcrumb links work correctly
- [ ] Old flat URLs redirect to new hierarchical URLs
- [ ] No 404 errors on existing bookmarks
- [ ] Sitemap updated with new URLs

## Notes

- **Language ID:** SEO URLs use English language ID (`2fbb5fe2e29a4d70aa5854ce7ce3e20b`) even for German content
- **Cloudflare Cache:** May need to purge cache after regenerating URLs
