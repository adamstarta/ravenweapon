# Varianten Capture Design

**Date:** 2025-12-19
**Status:** Approved

## Problem

When customers select product variants (Color/Size) on the frontend, the selection is not being captured and displayed properly in the admin order view. The "Farbe" column shows "-".

## Solution

Store variant selections in a combined `variantenDisplay` payload field using slash format (e.g., "Black / M"). Display this in all storefront templates. Accept that admin's native "Farbe" column will show "-" since we use custom fields, not native Shopware variants.

## Product Variant Types

| Type | Example Display |
|------|-----------------|
| Standard (no variants) | - |
| Color only | Black |
| Size only | M |
| Color + Size | Black / M |

## Implementation

### 1. Frontend (product-detail.html.twig)

- Rename UI labels from "Farbe" to "Variante"
- Add hidden input: `<input type="hidden" name="variantenDisplay" id="varianten-display-input">`
- JavaScript updates this field when color/size selection changes
- Format: `color / size` or just `color` or just `size`

### 2. Backend (CartLineItemSubscriber.php)

- Capture `variantenDisplay` from request
- Fallback: build from `selectedColor` + `selectedSize` if not provided
- Store in line item payload

### 3. Display Templates

Update these templates to show "Variante:" instead of "Farbe:":
- `offcanvas-cart.html.twig`
- `cart/index.html.twig`
- `checkout/finish/index.html.twig`

### 4. Admin Display

- Native "Farbe" column shows "-" (acceptable)
- Data IS saved in payload and visible when clicking line item details
- Future enhancement: Option A (custom admin column) if needed

## Data Flow

```
User selects variant → Hidden input updated → Form submitted
    → CartLineItemSubscriber captures → Stored in payload
    → Displayed in cart/checkout/order confirmation
```

## Example Payload

```json
{
  "selectedColor": "Black",
  "selectedSize": "M",
  "variantenDisplay": "Black / M",
  "productNumber": "SN-12345"
}
```

## Files to Modify

1. `shopware-theme/RavenTheme/src/Subscriber/CartLineItemSubscriber.php`
2. `shopware-theme/RavenTheme/src/Resources/views/storefront/page/product-detail/index.html.twig`
3. `shopware-theme/RavenTheme/src/Resources/views/storefront/component/checkout/offcanvas-cart.html.twig`
4. `shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/cart/index.html.twig`
5. `shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/finish/index.html.twig`
