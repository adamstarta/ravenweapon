# Mobile UI Improvements Plan

**Date:** 2026-01-02
**Status:** Ready for implementation
**Target:** Product detail page + Homepage hero

---

## Overview

Three mobile UI improvements for better user experience on ravenweapon.ch:

1. **Breadcrumb visibility** - Currently cut off on mobile
2. **Quantity selector + Add to Cart** - Compact inline design with larger CTA
3. **Hero centering** - Centered on mobile, left-aligned on desktop

---

## 1. Breadcrumb Visibility on Mobile

**Problem:** Breadcrumb text is barely visible/cut off on mobile devices.

**File:** `shopware-theme/RavenTheme/src/Resources/views/storefront/page/product-detail/index.html.twig`

**Changes:**
- Add mobile-specific styling to breadcrumb nav
- Increase font size slightly (14px)
- Enable horizontal scroll for long breadcrumbs
- Ensure proper spacing from header

**CSS Implementation:**
```css
.breadcrumb-nav {
  font-size: 14px;
  padding: 0.75rem 0;
  white-space: nowrap;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;  /* Hide scrollbar Firefox */
}

.breadcrumb-nav::-webkit-scrollbar {
  display: none;  /* Hide scrollbar Chrome/Safari */
}

@media (min-width: 768px) {
  .breadcrumb-nav {
    white-space: normal;
    overflow-x: visible;
  }
}
```

---

## 2. Compact Quantity Selector + Large Add to Cart Button

**Problem:** Quantity selector takes too much space, Add to Cart button not prominent enough.

**File:** `shopware-theme/RavenTheme/src/Resources/views/storefront/page/product-detail/index.html.twig`

### 2.1 Quantity Selector - Compact Inline Design

**Current:**
```
[−]  [1]  [+]   (40px buttons, 60px input = ~150px total)
```

**New:**
```
[− 1 +]   (single bordered container, ~100px total)
```

**HTML Structure:**
```html
<div class="quantity-selector-compact">
  <button type="button" class="qty-btn qty-decrease">−</button>
  <input type="number" id="quantity" value="1" min="1" readonly>
  <button type="button" class="qty-btn qty-increase">+</button>
</div>
```

**CSS:**
```css
.quantity-selector-compact {
  display: inline-flex;
  align-items: center;
  border: 1px solid #D1D5DB;
  border-radius: 8px;
  overflow: hidden;
  height: 44px;
  flex-shrink: 0;
}

.quantity-selector-compact .qty-btn {
  width: 36px;
  height: 100%;
  border: none;
  background: #F9FAFB;
  font-size: 18px;
  font-weight: 600;
  color: #374151;
  cursor: pointer;
  transition: background 0.2s;
}

.quantity-selector-compact .qty-btn:hover {
  background: #F3F4F6;
}

.quantity-selector-compact input {
  width: 40px;
  height: 100%;
  border: none;
  border-left: 1px solid #E5E7EB;
  border-right: 1px solid #E5E7EB;
  text-align: center;
  font-weight: 600;
  font-size: 16px;
  -moz-appearance: textfield;
}

.quantity-selector-compact input::-webkit-outer-spin-button,
.quantity-selector-compact input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
```

### 2.2 Add to Cart Button - Extra Large

**Current:** `py-4` (16px padding), `text-base` (16px font)

**New:** 56px height, 18px font, stronger presence

**CSS:**
```css
.add-to-cart-btn-large {
  flex: 1;
  height: 56px;
  font-size: 18px;
  font-weight: 700;
  border-radius: 10px;
  background: linear-gradient(to top, #F2B90D 12%, #F6CE55 88%);
  border: 1px solid #C59A0F;
  box-shadow: 0 4px 12px rgba(0,0,0,0.25);
}

.add-to-cart-btn-large svg {
  width: 24px;
  height: 24px;
}

.add-to-cart-btn-large:hover {
  box-shadow: 0 6px 16px rgba(0,0,0,0.35);
  transform: translateY(-1px);
}
```

### 2.3 Same Row Layout (All Devices)

**Layout:**
```
[− 1 +]  [████████ In den Warenkorb ████████]
```

**Container CSS:**
```css
.quantity-cart-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 1.5rem;
}
```

---

## 3. Hero Centering on Mobile

**Problem:** Hero content should be centered on mobile for better visual balance.

**File:** `shopware-theme/RavenTheme/src/Resources/views/storefront/page/content/index.html.twig`

**Current:** Left-aligned on all devices (padding-left: 6rem on desktop)

**New:**
- Mobile (< 768px): Centered text and buttons
- Desktop (>= 768px): Left-aligned (keep current)

**CSS Implementation:**
```css
.raven-hero-content {
  /* Mobile: centered */
  text-align: center;
  padding-left: 1.5rem;
  padding-right: 1.5rem;
}

.raven-hero-content ul {
  display: inline-block;
  text-align: left;  /* Keep bullet points left-aligned within centered block */
}

.raven-hero-content .hero-buttons {
  justify-content: center;
}

@media (min-width: 768px) {
  .raven-hero-content {
    text-align: left;
    padding-left: 6rem;
    padding-right: 2rem;
  }

  .raven-hero-content .hero-buttons {
    justify-content: flex-start;
  }
}
```

---

## Implementation Checklist

- [ ] **Step 1:** Update breadcrumb styles in product-detail template
- [ ] **Step 2:** Replace quantity selector with compact inline version
- [ ] **Step 3:** Update Add to Cart button to extra large size
- [ ] **Step 4:** Wrap quantity + button in same-row flex container
- [ ] **Step 5:** Add hero mobile centering CSS to homepage template
- [ ] **Step 6:** Test on mobile viewport (375px) using Chrome DevTools
- [ ] **Step 7:** Test on tablet viewport (768px)
- [ ] **Step 8:** Test on desktop viewport (1280px)
- [ ] **Step 9:** Deploy to staging (developing.ravenweapon.ch)
- [ ] **Step 10:** Verify and deploy to production

---

## Files to Modify

| File | Changes |
|------|---------|
| `page/product-detail/index.html.twig` | Breadcrumb, quantity selector, add to cart button |
| `page/content/index.html.twig` | Hero mobile centering |

---

## Testing

After implementation, verify with Chrome DevTools:
- iPhone 12 Pro (390 x 844)
- iPad (768 x 1024)
- Desktop (1280 x 800)

Check:
1. Breadcrumb visible and scrollable on mobile
2. Quantity selector compact, buttons work
3. Add to Cart button prominent and clickable
4. Hero centered on mobile, left on desktop
