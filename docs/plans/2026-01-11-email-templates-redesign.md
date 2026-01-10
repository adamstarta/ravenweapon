# Email Template Mobile Redesign

## Problem
The admin order notification email displays correctly on desktop but breaks on mobile Gmail:
- "Menge" column header displays vertically (M-e-n-g-e stacked)
- Table columns are cramped and overlapping
- Total price gets cut off at the edge

## Root Cause
The HTML email in `OrderNotificationSubscriber.php` uses a fixed-width table layout:
- `table-layout: fixed` with percentage widths
- On 320px mobile screens, the 10% "Menge" column becomes just 32px wide
- Email clients don't support media queries reliably

## Solution: Mobile Card Layout

Replace the rigid 4-column table with a card-based layout that works on all screen sizes.

### Design

**Each product item becomes a card:**
```
+------------------------------------------+
| [IMG]  Product Name                      |
|        (Variant info)                    |
|------------------------------------------|
| Menge: 1  |  Stück: CHF 15.25  |  CHF 15.25 |
+------------------------------------------+
```

**Summary section uses 2-column layout:**
```
+------------------------------------------+
|  Zwischensumme:              CHF 15.25   |
|  Versandkosten:              CHF 11.15   |
+==========================================+
|  Gesamtbetrag:               CHF 26.40   |
+------------------------------------------+
```

### Key Changes

1. **Product items**: Stack vertically with image/name on top, details below
2. **No fixed column widths**: Use flexbox-style inline-block divs
3. **Minimum font sizes**: 14px minimum for readability
4. **Price alignment**: Right-aligned with `white-space: nowrap`
5. **Responsive container**: `max-width: 600px` (email standard)

## Files to Modify

- `shopware-theme/RavenTheme/src/Subscriber/OrderNotificationSubscriber.php`
  - Update `buildHtmlEmail()` method (lines 202-374)

## Testing

1. Deploy to staging
2. Place a test order
3. Check email on:
   - Desktop Gmail
   - Mobile Gmail (iOS/Android)
   - Apple Mail
