# Customer Order Confirmation Email - Mobile Fix

## Problem

The customer order confirmation email breaks on mobile Gmail:
1. **Bank details table** - Values cut off (Raven..., PostFi..., CH600...)
2. **Product table** - Headers cut off, layout cramped

## Approach

Create Twig template overrides in our theme to control the email HTML via Git, while ensuring both mobile and desktop Gmail display correctly.

### Key Constraints
- Gmail does NOT support CSS media queries
- Must work on ~320px mobile AND ~600px+ desktop
- Use inline styles only
- No JavaScript

### Solution Strategy

**Fluid Design (no media queries needed):**
- Use `width: 100%` with `max-width: 600px` on container
- Use `table-layout: fixed` with percentage column widths
- Use smaller, mobile-safe font sizes (13-14px base)
- Shorten labels where possible (e.g., "Konto" instead of "Kontoinhaber")
- Ensure values don't overflow with `word-break: break-all` on long strings like IBAN

## Implementation

### Step 1: Create Email Template Directory

Create directory structure:
```
shopware-theme/RavenTheme/src/Resources/views/
└── email/
    └── order_confirmation/
        └── order-confirmation.html.twig
```

### Step 2: Override Order Confirmation Template

The template will include:
1. **Header** - Order number, date
2. **Product Table** - 3 columns: Produkt (60%), Menge (15%), Preis (25%)
3. **Summary** - Subtotal, shipping, total
4. **Bank Details** (for prepayment) - Stacked layout for mobile safety
5. **CTA Button** - "Bestellung ansehen"

### Step 3: Bank Details Fix

**Current (broken on mobile):**
```html
<table>
  <tr><td>Kontoinhaber</td><td>Raven Weapon AG</td></tr>
  <!-- values overflow -->
</table>
```

**Fixed (mobile-safe):**
```html
<table style="width: 100%; table-layout: fixed;">
  <tr>
    <td style="width: 35%; font-size: 13px;">Konto</td>
    <td style="width: 65%; font-size: 13px; word-break: break-all;">Raven Weapon AG</td>
  </tr>
</table>
```

### Step 4: Product Table Fix

**Column distribution:**
| Column | Width | Alignment |
|--------|-------|-----------|
| Produkt | 55% | Left |
| Menge | 15% | Center |
| Preis | 30% | Right |

**Mobile optimizations:**
- Remove product image OR make it 30x30px max
- Use `font-size: 12px` for Art.Nr
- Use `white-space: nowrap` on price values

## File Changes

| File | Action |
|------|--------|
| `src/Resources/views/email/order_confirmation.html.twig` | CREATE - Main template override |
| `src/Resources/config/services.xml` | UPDATE - Register if needed |

## Testing

1. Deploy to staging
2. Place test order with bank transfer payment
3. Check email on:
   - Desktop Gmail (web)
   - Mobile Gmail app (Android/iOS)
   - Apple Mail (optional)

## Rollback

If issues occur, delete the template override file and Shopware will use the default template again.
