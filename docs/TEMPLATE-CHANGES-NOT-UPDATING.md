# Why Template Changes Don't Update on Frontend

## Common Issues & Solutions

### 1. Shopware Template Caching
Shopware compiles Twig templates into PHP. Changes won't appear until cache is cleared.

**Solution:** Cache is cleared automatically during deployment via `bin/console cache:clear`

### 2. Block Override Doesn't Exist
If you override a block name that doesn't exist in Shopware's base template, your override is silently ignored.

**Example of WRONG approach:**
```twig
{% sw_extends '@Storefront/storefront/page/checkout/confirm/confirm-shipping.html.twig' %}
{% block page_checkout_confirm_shipping_form_method %}  {# THIS BLOCK DOESN'T EXIST! #}
    ... your code ...
{% endblock %}
```

**Solution:** Check Shopware's actual template for correct block names, or create a standalone template without `sw_extends`.

### 3. Data Not Loaded (Associations Missing)
Shopware lazy-loads entity associations. If you need `shipping.deliveryTime` but it's null, the association wasn't loaded.

**Example:** `page.shippingMethods` doesn't include `deliveryTime` by default.

**Solution:** Create a subscriber to load the association:
```php
// ShippingDeliveryTimeSubscriber.php
public function onCheckoutConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $event): void
{
    $criteria = new Criteria($shippingMethodIds);
    $criteria->addAssociation('deliveryTime');  // Load the association
    // ... reload and merge data
}
```

### 4. Cloudflare Caching
Cloudflare may cache pages. Hard refresh (Ctrl+Shift+R) or wait for cache expiry.

### 5. Template Path Must Match Exactly
Your override must be at the exact same path as Shopware's template:
- Shopware: `@Storefront/storefront/component/shipping/shipping-method.html.twig`
- Your override: `RavenTheme/src/Resources/views/storefront/component/shipping/shipping-method.html.twig`

### 6. sw_include vs sw_extends
- `sw_include` - includes a template, theme overrides ARE respected
- `sw_extends` - extends a template for block overrides

If using `sw_include '@Storefront/...'`, your theme's override at the same path will be used.

---

## Debugging Checklist

1. Is the block name correct? (Check Shopware source)
2. Is the file path correct? (Must match exactly)
3. Is the data loaded? (Check if association is null)
4. Is cache cleared? (Happens on deploy)
5. Is Cloudflare caching? (Hard refresh)

## Key Lesson Learned

When Shopware's template inheritance doesn't work as expected, sometimes it's easier to create a **standalone template** that doesn't extend anything, giving you full control over the output.
