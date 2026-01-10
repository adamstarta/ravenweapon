# Checkout UI Cleanup Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Clean up checkout shipping section UI - show delivery time, remove visual clutter (shadows/borders), remove numbered circles

**Architecture:** Modify inline CSS in confirm/index.html.twig to simplify shipping card styling and hide accordion numbers

**Tech Stack:** Twig, CSS

---

## Current Issues (from screenshot)

1. **Delivery time not visible** - Template has the code but it's not rendering properly
2. **Too many shadows/borders** - Double borders from container + inner card
3. **Dark numbered circles (1, 2, 3)** - Accordion step numbers need removal

---

### Task 1: Show Delivery Time in Shipping Options

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/confirm/index.html.twig` (lines 1005-1015)

**Problem:** Delivery time element exists in shipping-method.html.twig but CSS may be hiding it or it's not styled properly.

**Step 1: Add delivery time styling to ensure visibility**

Find this CSS block (around line 1005-1015):
```css
.shipping-method-delivery {
    display: flex !important;
    align-items: center !important;
    gap: 0.375rem !important;
    font-size: 0.8125rem !important;
    color: var(--gray-600) !important;
}
```

Verify it exists. If shipping-method-delivery is not styled, add it after `.shipping-method-name` styles.

**Step 2: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/confirm/index.html.twig
git commit -m "style(checkout): ensure delivery time is visible in shipping options"
```

---

### Task 2: Remove Numbered Circles from Accordion

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/confirm/index.html.twig`

**Step 1: Hide the accordion number circles with CSS**

Find the `.accordion-number` styles (around lines 196-214):
```css
.accordion-number {
    width: 28px;
    height: 28px;
    background: var(--gray-100);
    color: var(--gray-500);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8125rem;
    font-weight: 600;
    transition: all 0.2s;
}
```

**Replace with:**
```css
.accordion-number {
    display: none !important;
}
```

**Step 2: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/confirm/index.html.twig
git commit -m "style(checkout): remove numbered circles from accordion sections"
```

---

### Task 3: Clean Up Shipping Card - Remove Double Borders/Shadows

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/confirm/index.html.twig`

**Step 1: Simplify raven-shipping-option styling**

Find `.raven-shipping-option` styles (around lines 951-974):
```css
.raven-shipping-option,
.raven-shipping-method {
    display: flex !important;
    align-items: center !important;
    gap: 0.75rem !important;
    padding: 1rem 1.25rem !important;
    background: var(--white) !important;
    border: 2px solid var(--gray-200) !important;
    border-radius: 10px !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
}

.raven-shipping-option.is-selected,
.raven-shipping-method.is-selected {
    border-color: var(--gold) !important;
    background: rgba(212, 168, 71, 0.06) !important;
    box-shadow: 0 0 0 3px rgba(212, 168, 71, 0.1) !important;
}
```

**Replace with cleaner flat design:**
```css
.raven-shipping-option,
.raven-shipping-method {
    display: flex !important;
    align-items: center !important;
    gap: 0.75rem !important;
    padding: 0.875rem 1rem !important;
    background: var(--white) !important;
    border: none !important;
    border-bottom: 1px solid var(--gray-100) !important;
    border-radius: 0 !important;
    cursor: pointer !important;
    transition: background 0.15s ease !important;
}

.raven-shipping-option:last-child,
.raven-shipping-method:last-child {
    border-bottom: none !important;
}

.raven-shipping-option:hover,
.raven-shipping-method:hover {
    background: var(--gray-50) !important;
}

.raven-shipping-option.is-selected,
.raven-shipping-method.is-selected {
    background: rgba(212, 168, 71, 0.08) !important;
    border-bottom: 1px solid var(--gray-100) !important;
    box-shadow: none !important;
}
```

**Step 2: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/confirm/index.html.twig
git commit -m "style(checkout): clean shipping options - remove shadows and double borders"
```

---

### Task 4: Verify Deployment and Test

**Step 1: Push changes to trigger staging deployment**

```bash
git push origin main
```

**Step 2: Wait for staging deployment (~2 min)**

**Step 3: Test on staging**

Visit: https://developing.ravenweapon.ch/checkout/confirm
- Verify delivery time shows (e.g., "1-2 Wochen" for International)
- Verify no numbered circles (1, 2, 3)
- Verify clean flat list design (no double borders/shadows)

**Step 4: Approve production deployment if staging looks good**

Go to: https://github.com/adamstarta/ravenweapon/actions

---

## Summary of Changes

| Change | Location | Lines |
|--------|----------|-------|
| Hide accordion numbers | `.accordion-number` | ~196-214 |
| Clean shipping card borders | `.raven-shipping-option` | ~951-974 |
| Ensure delivery time visible | `.shipping-method-delivery` | ~1005-1015 |

---

## Expected Result

**Before:**
- Numbered circles (1, 2, 3) on accordion
- Double bordered shipping cards with shadows
- No delivery time visible

**After:**
- Clean accordion headers without numbers
- Flat list design for shipping options
- Delivery time visible (e.g., "1-3 Tage", "1-2 Wochen")
