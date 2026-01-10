# Variant Image Capture Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Capture the currently displayed product image when adding to cart, so the correct variant image shows in cart, checkout, order history, and emails.

**Architecture:** JavaScript captures the visible gallery image on "Add to Cart" click, injects it as a hidden form field, PHP subscriber saves it to line item payload, Twig templates display the payload image with fallback to default cover.

**Tech Stack:** Shopware 6.6, PHP 8.3, Twig, JavaScript, Symfony Event Subscribers

---

## Task 1: Add JavaScript to Capture Variant Image

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/app/storefront/src/main.js`

**Step 1: Add the image capture code**

Add this code at the end of the file, before the closing of the module:

```javascript
// Variant Image Capture - captures current product image on add-to-cart
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        const buyButton = e.target.closest('.btn-buy, .buy-widget-submit');
        if (!buyButton) return;

        const form = buyButton.closest('form');
        if (!form) return;

        // Find the main product image currently displayed
        const galleryImage = document.querySelector('.gallery-slider-image.is-active img')
            || document.querySelector('.gallery-slider-image img')
            || document.querySelector('.product-detail-media img');

        if (!galleryImage) return;

        const imageUrl = galleryImage.src || galleryImage.dataset.src;
        if (!imageUrl) return;

        // Create or update hidden input for variant image
        let hiddenInput = form.querySelector('input[name="variantImageUrl"]');
        if (!hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'variantImageUrl';
            form.appendChild(hiddenInput);
        }
        hiddenInput.value = imageUrl;
    }, true); // Use capture phase to run before form submit
});
```

**Step 2: Verify the JS is syntactically correct**

Run: `cd shopware-theme/RavenTheme/src/Resources/app/storefront && npm run build` (if build system exists)

Or manually verify by checking browser console for errors after deployment.

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/app/storefront/src/main.js
git commit -m "feat(cart): add JS to capture variant image on add-to-cart"
```

---

## Task 2: Update CartLineItemSubscriber to Save Variant Image

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Subscriber/CartLineItemSubscriber.php`

**Step 1: Read the current file**

Read the file to understand its current structure.

**Step 2: Add variantImageUrl to payload**

In the `onLineItemAdded` method (or equivalent), add code to read and save the variant image URL:

```php
public function onLineItemAdded(BeforeLineItemAddedEvent $event): void
{
    $lineItem = $event->getLineItem();
    $request = $this->requestStack->getCurrentRequest();

    if (!$request || $lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
        return;
    }

    // Existing selectedColor logic stays here...

    // NEW: Save variant image URL to payload
    $variantImageUrl = $request->request->get('variantImageUrl');
    if ($variantImageUrl && filter_var($variantImageUrl, FILTER_VALIDATE_URL)) {
        $lineItem->setPayloadValue('variantImageUrl', $variantImageUrl);
    }
}
```

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Subscriber/CartLineItemSubscriber.php
git commit -m "feat(cart): save variant image URL to line item payload"
```

---

## Task 3: Update Off-Canvas Cart Template

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/component/checkout/offcanvas-cart.html.twig`

**Step 1: Find the image variable assignment**

Look for line ~568 where `itemImage` is set:
```twig
{% set itemImage = lineItem.cover.url|default('') %}
```

**Step 2: Update to use variant image with fallback**

Change to:
```twig
{% set itemImage = lineItem.payload.variantImageUrl|default(lineItem.cover.url)|default('') %}
```

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/component/checkout/offcanvas-cart.html.twig
git commit -m "feat(cart): display variant image in off-canvas cart"
```

---

## Task 4: Update Main Cart Page Template

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/cart/index.html.twig`

**Step 1: Find the image source**

Look for line ~79-80 where `lineItem.cover.url` is used:
```twig
<img src="{{ lineItem.cover.url }}"
```

**Step 2: Update to use variant image with fallback**

Change to:
```twig
<img src="{{ lineItem.payload.variantImageUrl|default(lineItem.cover.url) }}"
```

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/cart/index.html.twig
git commit -m "feat(cart): display variant image in main cart page"
```

---

## Task 5: Update Checkout Confirm Template

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/confirm/index.html.twig`

**Step 1: Find the image source**

Look for line ~1530 where `lineItem.cover.url` is used.

**Step 2: Update to use variant image with fallback**

Change from:
```twig
<img src="{{ lineItem.cover.url }}"
```

To:
```twig
<img src="{{ lineItem.payload.variantImageUrl|default(lineItem.cover.url) }}"
```

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/confirm/index.html.twig
git commit -m "feat(checkout): display variant image in checkout confirm"
```

---

## Task 6: Update Order Finish Template

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/finish/index.html.twig`

**Step 1: Find the image source**

Look for line ~761 where the product image is displayed.

**Step 2: Update to use variant image with fallback**

Change from:
```twig
src="{% if lineItem.cover %}{{ lineItem.cover.url }}{% else %}...{% endif %}"
```

To:
```twig
src="{{ lineItem.payload.variantImageUrl|default(lineItem.cover.url)|default(asset('bundles/storefront/assets/icon/default/placeholder.svg')) }}"
```

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/finish/index.html.twig
git commit -m "feat(checkout): display variant image in order finish page"
```

---

## Task 7: Update Order History Detail Template

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/order-history/order-detail.html.twig`

**Step 1: Find the image source**

Look for line ~152 where `lineItem.cover.url` is used.

**Step 2: Update to use variant image with fallback**

Change to:
```twig
<img src="{{ lineItem.payload.variantImageUrl|default(lineItem.cover.url) }}"
```

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/order-history/order-detail.html.twig
git commit -m "feat(account): display variant image in order history detail"
```

---

## Task 8: Update Account Orders Template

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/order/index.html.twig`

**Step 1: Find the image sources**

Look for lines ~96 and ~173 where `lineItem.cover.url` is used.

**Step 2: Update both occurrences to use variant image with fallback**

Change both to:
```twig
<img src="{{ lineItem.payload.variantImageUrl|default(lineItem.cover.url) }}"
```

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/order/index.html.twig
git commit -m "feat(account): display variant image in account orders"
```

---

## Task 9: Update OrderNotificationSubscriber for Emails

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Subscriber/OrderNotificationSubscriber.php`

**Step 1: Find the buildItemsList method**

Look for the `buildItemsList` method around line 144-194.

**Step 2: Update image URL logic**

Find where `imageUrl` is set (around line 176-180):
```php
$imageUrl = '';
$cover = $lineItem->getCover();
if ($cover) {
    $imageUrl = $cover->getUrl();
}
```

Change to:
```php
$imageUrl = '';
$payload = $lineItem->getPayload();

// Prefer variant image if available
if (!empty($payload['variantImageUrl'])) {
    $imageUrl = $payload['variantImageUrl'];
} elseif ($cover = $lineItem->getCover()) {
    $imageUrl = $cover->getUrl();
}
```

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Subscriber/OrderNotificationSubscriber.php
git commit -m "feat(email): display variant image in order notification emails"
```

---

## Task 10: Push and Test

**Step 1: Push all changes**

```bash
git push origin main
```

**Step 2: Wait for deployment to staging**

Wait ~2 minutes for GitHub Actions to deploy to https://developing.ravenweapon.ch

**Step 3: Test the complete flow**

1. Go to https://developing.ravenweapon.ch
2. Navigate to "Tactical coverall 09F" product
3. Select "Briefs" variant - verify image shows briefs (yellow shorts)
4. Click "Add to Cart"
5. **Verify:** Off-canvas cart shows briefs image (not complete coverall)
6. Go to cart page - **Verify:** Shows briefs image
7. Proceed to checkout - **Verify:** Confirm page shows briefs image
8. Complete test order with bank transfer
9. **Verify:** Finish page shows briefs image
10. Go to Account > Orders - **Verify:** Shows briefs image
11. Check admin email - **Verify:** Shows briefs image

**Step 4: Test fallback behavior**

1. Add a product without variant options
2. **Verify:** Cart shows default product image (fallback works)

**Step 5: Final commit if any fixes needed**

```bash
git add .
git commit -m "fix: address variant image issues found in testing"
git push origin main
```

---

## Files Summary

| File | Change Type |
|------|-------------|
| `main.js` | Add image capture JS |
| `CartLineItemSubscriber.php` | Save variantImageUrl to payload |
| `offcanvas-cart.html.twig` | Use variant image with fallback |
| `checkout/cart/index.html.twig` | Use variant image with fallback |
| `checkout/confirm/index.html.twig` | Use variant image with fallback |
| `checkout/finish/index.html.twig` | Use variant image with fallback |
| `order-history/order-detail.html.twig` | Use variant image with fallback |
| `account/order/index.html.twig` | Use variant image with fallback (2 places) |
| `OrderNotificationSubscriber.php` | Use variant image in emails |
