# Checkout Finish Page - Single Table Redesign

## Date: 2026-01-09

## Overview

Redesign the checkout finish page (`/checkout/finish`) to use a single unified table layout instead of separate cards. The new design follows a clean document/invoice style.

## Current State

- 3 separate info cards (Lieferadresse, Rechnungsadresse, Informationen)
- Separate product table with grid layout
- Separate summary card
- Misaligned columns in product table

## New Design

Single table containing all order information:

```
┌──────────────────────────────────────────────────────────────────────────────┐
│  Lieferadresse                                                               │
│  [Address details]                                                           │
│                                                                              │
│  Rechnungsadresse                                                            │
│  [Address details]                                                           │
│                                                                              │
│  Informationen                                                               │
│  Zahlung: [method]    Versand: [method]                                      │
│──────────────────────────────────────────────────────────────────────────────│
│  PRODUKT                                           MENGE       CHF     PREIS │
│──────────────────────────────────────────────────────────────────────────────│
│  [Product name]                                        1       CHF      3.70 │
│      Art.Nr: [SKU]                                                           │
│──────────────────────────────────────────────────────────────────────────────│
│                                             Zwischensumme      CHF      3.70 │
│                                             Versand            CHF     53.00 │
│══════════════════════════════════════════════════════════════════════════════│
│                                             Gesamtsumme        CHF     56.70 │
└──────────────────────────────────────────────────────────────────────────────┘
```

## Style Specifications

| Element | Style |
|---------|-------|
| Table container | White background, subtle border, border-radius: 8px |
| Section headers | Bold, uppercase, font-size: 0.75rem, color: #6b7280 |
| Address text | Normal weight, color: #374151 |
| Name in address | Bold, color: #111827 |
| Separator lines | 1px solid #e5e7eb |
| Column headers | Uppercase, gray, font-size: 0.6875rem |
| Product name | Bold, color: #111827 |
| Art.Nr | Indented, smaller, gray |
| Numbers | Right-aligned, tabular-nums |
| Total separator | 2px solid #111827 |
| Total text | Bold, larger font |

## File to Modify

`shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/finish/index.html.twig`

## Implementation Steps

1. Remove separate card structures (order-info-row, info-card)
2. Remove separate product table (order-products)
3. Remove separate summary card (order-summary)
4. Create single `<table>` element with proper structure
5. Update CSS to match document-style design
6. Ensure responsive behavior on mobile
