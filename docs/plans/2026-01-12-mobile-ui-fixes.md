# Mobile UI Fixes Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix mobile UI issues on account page and checkout confirm page without affecting desktop layout.

**Architecture:** Add mobile-specific CSS media queries to hide sidebar on account page and fix header overlap on checkout page.

**Tech Stack:** CSS media queries, existing Twig templates

---

## Issues Identified

### Issue 1: Account Page Sidebar on Mobile
- **URL:** `/account`
- **Problem:** Sidebar navigation visible on mobile, looks cramped
- **File:** `shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/index.html.twig`

### Issue 2: Checkout Header Overlap on Mobile
- **URL:** `/checkout/confirm`
- **Problem:** "Checkout" heading cut off by sticky header
- **File:** `shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/confirm/index.html.twig`

---

## Task 1: Hide Account Sidebar on Mobile

**File:** `shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/index.html.twig`

**Step 1: Add mobile styles for sidebar**

Find the `.account-sidebar` CSS block (around line 248) and add a media query BEFORE the existing styles:

```css
/* Hide sidebar on mobile */
@media (max-width: 1023px) {
    .account-sidebar {
        display: none;
    }
}
```

This should be added around line 247, before the `.account-sidebar {` definition.

**Step 2: Verify desktop is unchanged**

The existing `@media (min-width: 1024px)` for `.account-container` already handles the 2-column grid on desktop.

---

## Task 2: Fix Checkout Header Overlap on Mobile

**File:** `shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/confirm/index.html.twig`

**Step 1: Add padding-top for header on mobile**

Find the mobile media query `@media (max-width: 640px)` section (around line 1376) and update the `.raven-checkout-wrapper` styles:

**Change from:**
```css
@media (max-width: 640px) {
    .raven-checkout-wrapper {
        padding: 1rem;
    }
```

**Change to:**
```css
@media (max-width: 640px) {
    .raven-checkout-wrapper {
        padding: 1rem;
        padding-top: 1.5rem;
    }
```

**Step 2: Also add padding for medium screens (tablets)**

Add a new media query for tablet screens where the header might still overlap:

```css
@media (max-width: 1023px) {
    .checkout-main-header {
        padding-top: 0.5rem;
    }
}
```

Add this before the `@media (max-width: 640px)` block.

---

## Task 3: Test and Deploy

**Step 1: Commit changes**

```bash
git add .
git commit -m "fix(mobile): hide account sidebar and fix checkout header overlap on mobile

- Hide account sidebar navigation on screens < 1024px
- Add padding-top to checkout wrapper to prevent header overlap
- Mobile-only changes, desktop layout unchanged"
```

**Step 2: Push to staging**

```bash
git push origin main
```

**Step 3: Test on mobile**

1. Go to https://developing.ravenweapon.ch/account (mobile)
   - Sidebar should be hidden
   - Dashboard cards should show full width

2. Go to https://developing.ravenweapon.ch/checkout/confirm (mobile)
   - "Checkout" heading should be fully visible
   - "Alle Informationen auf einen Blick" should not be cut off

---

## Summary

| Task | File | Change | Impact |
|------|------|--------|--------|
| 1 | account/index.html.twig | Hide sidebar < 1024px | Mobile only |
| 2 | checkout/confirm/index.html.twig | Add padding-top | Mobile only |
| 3 | Deploy | Push to staging | - |

**Total estimated time:** ~10 minutes
