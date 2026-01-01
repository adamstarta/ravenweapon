# PageSpeed Optimization Design

**Date:** 2025-12-31
**Goal:** Improve ravenweapon.ch performance score from 77 to 90+
**Approach:** Option C - Hybrid (code-only, free)

## Current State

| Metric | Score |
|--------|-------|
| Performance | 77 |
| LCP | 4.0s (target: <2.5s) |
| CLS | 0.01 |
| Total Transfer | 5MB |

## Key Issues

1. **Hero image as CSS background** - Can't use priority hints, delays LCP
2. **Fonts via CSS @import** - Render-blocking 160ms
3. **No lazy loading** - All images load immediately (4.7MB wasted)
4. **No explicit dimensions** - Causes CLS on image load
5. **Poor cache headers** - 3.4MB could be cached

## Implementation Plan

### 1. Font Optimization (-160ms)

**Create:** `views/storefront/layout/meta.html.twig`
- Add preconnect to fonts.googleapis.com and gstatic.com
- Preload fonts with `display=swap`
- Use `media="print" onload="this.media='all'"` pattern

**Modify:** `base.scss`
- Remove `@import url('https://fonts.googleapis.com/...')`

### 2. Hero Image LCP Fix (-500ms to -1s)

**Modify:** `views/storefront/page/content/index.html.twig`
- Convert CSS `background-image` to `<img>` element
- Add `fetchpriority="high"`
- Add `width="1920" height="1080"`
- Add `decoding="async"`

### 3. Lazy Loading + Dimensions (-40% bandwidth)

**Modify:** `views/storefront/utilities/thumbnail.html.twig`
- Add `loading="lazy"` for non-LCP images
- Add `width` and `height` attributes
- Add `sizes` attribute for responsive images
- Add `isLCP` parameter to skip lazy loading for above-fold images

**Modify:** `views/storefront/component/product/card/box-standard.html.twig`
- Pass dimensions to thumbnail macro
- Add sizes attribute

### 4. Homepage Product Grid

**Modify:** `views/storefront/page/content/index.html.twig`
- First 3 products: no lazy loading (visible on load)
- Rest of products: lazy loading enabled

### 5. Cloudflare Cache Rules (-3.4MB repeat visits)

**Configure in Cloudflare Dashboard:**
- `/media/*` - Cache 1 year
- `/theme/*` - Cache 1 month
- `/bundles/*` - Cache 1 year
- Static assets (jpg, png, js, css) - Cache appropriately

## Expected Results

| Metric | Before | After |
|--------|--------|-------|
| Performance | 77 | 90+ |
| LCP | 4.0s | <2.5s |
| Total Transfer | 5MB | ~1.5MB |

## Files to Modify

1. `src/Resources/views/storefront/layout/meta.html.twig` (CREATE)
2. `src/Resources/app/storefront/src/scss/base.scss` (MODIFY)
3. `src/Resources/views/storefront/page/content/index.html.twig` (MODIFY)
4. `src/Resources/views/storefront/utilities/thumbnail.html.twig` (MODIFY)
5. `src/Resources/views/storefront/component/product/card/box-standard.html.twig` (MODIFY)

## References

- [web.dev - Optimize LCP](https://web.dev/articles/optimize-lcp)
- [MDN - Fix Image LCP](https://developer.mozilla.org/en-US/blog/fix-image-lcp/)
- [Shopware Best Practices - Images](https://developer.shopware.com/frontends/best-practices/images.html)
