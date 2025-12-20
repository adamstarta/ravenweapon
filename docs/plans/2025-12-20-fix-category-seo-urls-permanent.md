# Fix Category SEO URLs - Permanent Solution

**Date:** 2025-12-20
**Status:** In Progress

## Problem

Level 2+ categories have incorrect SEO URLs missing parent paths:
- Current: `/koerperschutz/`
- Expected: `/ausruestung/koerperschutz/`

This is a recurring issue - fixed twice but keeps reverting.

## Root Cause

1. Both correct and incorrect SEO URLs exist in `seo_url` table
2. The **incorrect short URL** is marked as `is_canonical = 1`
3. When Shopware's indexer runs (`dal:refresh:index`), it regenerates URLs
4. The indexer marks short URLs as canonical, overwriting manual fixes

## Solution

### Step 1: Delete Incorrect Short URLs
Remove all category SEO URLs that don't include parent path for Level 2+ categories.

### Step 2: Mark Correct URLs as Canonical
Set `is_canonical = 1` on URLs with full parent paths.

### Step 3: Protect URLs from Indexer
Set `is_modified = 1` on correct URLs - this tells Shopware NOT to overwrite them.

## SQL Fix Script

```sql
-- Step 1: Identify affected categories (Level 3+)
-- These are subcategories that should have parent paths

-- Step 2: Delete incorrect short URLs (no parent path)
-- We identify these by comparing URL segment count

-- Step 3: Update correct URLs to be canonical and protected
UPDATE seo_url su
JOIN category c ON su.foreign_key = c.id
SET su.is_canonical = 1, su.is_modified = 1
WHERE su.route_name = 'frontend.navigation.page'
AND c.level >= 3
AND LENGTH(su.seo_path_info) - LENGTH(REPLACE(su.seo_path_info, '/', '')) >= 2;
```

## Verification

After fix, verify:
```sql
SELECT su.seo_path_info, su.is_canonical, su.is_modified, ct.name
FROM seo_url su
JOIN category c ON su.foreign_key = c.id
JOIN category_translation ct ON c.id = ct.category_id
WHERE su.route_name = 'frontend.navigation.page'
AND c.level >= 3
AND ct.name IS NOT NULL
ORDER BY ct.name;
```

## Expected Results

| Category | URL | is_canonical | is_modified |
|----------|-----|--------------|-------------|
| Körperschutz | ausruestung/koerperschutz/ | 1 | 1 |
| Behörden & Dienst | ausruestung/behoerden-dienst/ | 1 | 1 |
| Caracal Lynx | waffen/caracal-lynx/ | 1 | 1 |

## Prevention

The `is_modified = 1` flag prevents future indexer runs from overwriting these URLs.
