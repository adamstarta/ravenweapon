# RAVEN Shopware Branding Design

**Date:** 2025-11-26
**Status:** Approved
**Approach:** Minimal Override Strategy (Option A)

## Overview

Apply RAVEN luxury branding to Shopware's default storefront templates using SCSS variable overrides. Keep all default Shopware functionality (account, cart, checkout, product pages) while customizing colors, buttons, and logo placement.

## Color Palette

| Element | Color | Value |
|---------|-------|-------|
| **Gold Gradient** | Buttons, H1 headings | `linear-gradient(135deg, #FDE047 0%, #F59E0B 50%, #D97706 100%)` |
| **Dark Text** | H2+, body | `#111827` (gray-900) |
| **Body Text** | Paragraphs | `#374151` (gray-700) |
| **Subtle Text** | Placeholders | `#6b7280` (gray-500) |
| **Backgrounds** | All pages | `#FFFFFF` |
| **Borders** | Inputs, cards | `#E5E7EB` (gray-200) |

## Typography Hierarchy

- **H1**: Gold gradient text (hero titles, page titles)
- **H2 and smaller**: Dark gray/near black (#111827)
- **Body**: Dark gray (#374151)
- **Font**: Inter (already configured)

## Button Styling

All primary buttons use gold gradient:
- Buy Now
- Add to Cart
- Login / Register
- Checkout / Place Order
- Save (forms)

```scss
.btn-primary, .btn-buy {
  background: linear-gradient(135deg, #FDE047 0%, #F59E0B 50%, #D97706 100%);
  color: #000;
  border: none;
  font-weight: 600;
}
```

## Logo Placement

| Location | Status |
|----------|--------|
| Header | Done |
| Footer | To implement |
| Favicon | To implement (via Admin) |

## Architecture

```
RavenTheme/
├── src/Resources/
│   ├── theme.json                    # Color variables
│   ├── views/storefront/layout/
│   │   ├── header/header.html.twig   # Custom RAVEN header
│   │   └── footer/footer.html.twig   # Custom RAVEN footer
│   └── app/storefront/src/scss/
│       ├── overrides.scss            # SCSS variable overrides
│       └── base.scss                 # Custom component styles
```

## Pages Using Default Shopware Templates

These pages automatically inherit branding via SCSS:
- Account (login, register, dashboard, orders, addresses, profile)
- Cart
- Checkout
- Product listing
- Product detail
- Search results
- CMS pages
- Error pages

## Implementation Steps

1. Update `overrides.scss` with RAVEN color variables
2. Update `base.scss` with button and heading styles
3. Add logo to footer template
4. Upload favicon via Shopware Admin
5. Clear cache and compile theme
6. Test all pages

## Success Criteria

- All buttons show gold gradient
- H1 headings show gold gradient text
- Body text is dark gray on white
- Logo appears in header, footer, and favicon
- All Shopware default pages work with RAVEN branding
