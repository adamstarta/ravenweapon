# Mobile Table Responsive Scroll Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix the product description table (KENNGRÖSSEN) being cut off on mobile by adding horizontal scroll with visual indicator.

**Architecture:** Add CSS styles to `base.scss` that target tables inside `.description-content`, wrapping them with `overflow-x: auto` on mobile and adding a gradient shadow on the right edge to indicate scrollable content.

**Tech Stack:** SCSS (compiled by Shopware theme system)

---

## Task 1: Add Responsive Table Styles to base.scss

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/app/storefront/src/scss/base.scss` (append to end)

**Step 1: Add the responsive table styles**

Add the following SCSS at the end of `base.scss`:

```scss
// =============================================================================
// RESPONSIVE TABLES IN DESCRIPTION CONTENT
// =============================================================================

// Make tables in product descriptions responsive on mobile
.description-content {
  // Wrapper for tables to enable horizontal scroll
  table {
    display: block;
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;

    // Ensure table doesn't collapse
    tbody, thead, tfoot {
      display: table;
      width: 100%;
      min-width: 500px; // Force minimum width so values don't wrap awkwardly
    }

    // Table cell styling
    td, th {
      padding: 0.5rem 0.75rem;
      white-space: nowrap; // Prevent text wrapping in cells

      &:first-child {
        white-space: normal; // Allow first column (labels) to wrap if needed
        min-width: 150px;
      }

      &:last-child {
        text-align: right;
        font-weight: 500;
      }
    }

    // Row styling
    tr {
      border-bottom: 1px solid #e5e7eb;

      &:last-child {
        border-bottom: none;
      }
    }
  }

  // Mobile-specific scroll indicator shadow
  @media (max-width: 767px) {
    position: relative;

    // Right shadow indicator for scrollable content
    &::after {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      bottom: 0;
      width: 30px;
      background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.9) 100%);
      pointer-events: none;
      opacity: 1;
      transition: opacity 0.3s ease;
    }

    // Hide shadow when scrolled to end (handled by JS, but CSS fallback)
    &.scrolled-end::after {
      opacity: 0;
    }
  }
}

// Alternative: Wrap description in a scroll container
.description-wrapper {
  @media (max-width: 767px) {
    // Ensure description tables can scroll
    .description-content {
      max-width: 100%;

      table {
        margin-bottom: 1rem;
      }
    }
  }
}
```

**Step 2: Verify the file was modified**

Run: `grep -n "RESPONSIVE TABLES" "shopware-theme/RavenTheme/src/Resources/app/storefront/src/scss/base.scss"`

Expected: Line number with "RESPONSIVE TABLES IN DESCRIPTION CONTENT"

**Step 3: Commit the changes**

```bash
git add shopware-theme/RavenTheme/src/Resources/app/storefront/src/scss/base.scss
git commit -m "fix: add responsive horizontal scroll for tables in product descriptions on mobile"
```

---

## Task 2: Test on Staging

**Step 1: Push to trigger deployment**

```bash
git push origin main
```

**Step 2: Wait for GitHub Actions deployment**

- Check: https://github.com/adamstarta/ravenweapon/actions
- Wait for staging deployment to complete (~2 minutes)

**Step 3: Verify fix on staging**

- Open: https://developing.ravenweapon.ch
- Navigate to a product with the KENNGRÖSSEN table
- Test on mobile viewport (use browser DevTools, resize to ~375px width)
- Verify:
  - [ ] Table shows both labels AND values
  - [ ] Table can be scrolled horizontally
  - [ ] Right shadow indicator is visible
  - [ ] Shadow fades when scrolled to end

**Step 4: Approve production deployment**

If staging looks good:
- Go to GitHub Actions
- Approve the production deployment
- Verify on https://ravenweapon.ch

---

## Verification Checklist

- [ ] Tables in product descriptions are horizontally scrollable on mobile
- [ ] Both label column and value column are visible
- [ ] Scroll shadow indicator appears on right edge
- [ ] Desktop view is unchanged
- [ ] No layout breaks on other pages

---

## Rollback (if needed)

If issues occur, revert the commit:
```bash
git revert HEAD
git push origin main
```
