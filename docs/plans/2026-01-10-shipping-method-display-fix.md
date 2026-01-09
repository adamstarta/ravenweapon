# Shipping Method Display Fix

## Problem

Shipping method names (Standard, Express, International) are NOT showing in checkout.
Only "Auswählen" displays. The radio buttons work but have no labels.

## Root Cause

1. The `confirm-shipping.html.twig` uses `{% sw_extends '@Storefront/...' %}`
2. This inherits Shopware's template structure which loops through methods and calls a component
3. The component template receives EMPTY `shippingMethod` variable
4. Result: Empty names, empty IDs, empty values

## Solution

**Create STANDALONE template - don't extend Shopware's base**

The `index.html.twig` already does:
```twig
{% sw_include 'storefront/page/checkout/confirm/confirm-shipping.html.twig' %}
```

This include passes the parent context (`page`, `context`, etc.) to the included template.

So we should create `confirm-shipping.html.twig` as a **standalone template** (no extends) that directly renders the shipping form using `page.shippingMethods`.

---

## Implementation

### Step 1: Rewrite confirm-shipping.html.twig (STANDALONE)

Replace the entire file with a standalone template:

```twig
{# STANDALONE - No extends! Directly included by index.html.twig #}
<form id="changeShippingForm"
      name="changeShippingForm"
      action="{{ path('frontend.checkout.configure') }}"
      data-form-auto-submit="true"
      data-form-auto-submit-options='{"changeTriggerSelectors":[".shipping-method-input"]}'
      method="post">
    {{ sw_csrf('frontend.checkout.configure') }}

    <input type="hidden" name="redirectTo" value="frontend.checkout.confirm.page">

    <div class="raven-shipping-methods">
        {% for shippingMethod in page.shippingMethods %}
            {% set isSelected = shippingMethod.id == context.shippingMethod.id %}

            <div class="raven-shipping-option {% if isSelected %}is-selected{% endif %}">
                <input type="radio"
                       id="shippingMethod{{ shippingMethod.id }}"
                       name="shippingMethodId"
                       value="{{ shippingMethod.id }}"
                       class="raven-shipping-radio shipping-method-input"
                       {{ isSelected ? 'checked' : '' }}>

                <label class="raven-shipping-label" for="shippingMethod{{ shippingMethod.id }}">
                    <div class="shipping-method-info">
                        <div class="shipping-method-name">
                            {{ shippingMethod.translated.name }}
                        </div>

                        {% if shippingMethod.deliveryTime %}
                            <div class="shipping-method-delivery">
                                <svg class="delivery-icon" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span>{{ shippingMethod.deliveryTime.translated.name }}</span>
                            </div>
                        {% endif %}
                    </div>

                    <div class="shipping-method-price {% if isSelected %}is-active{% endif %}">
                        {% set shippingPrice = null %}
                        {% for delivery in page.cart.deliveries %}
                            {% if delivery.shippingMethod.id == shippingMethod.id %}
                                {% set shippingPrice = delivery.shippingCosts.totalPrice %}
                            {% endif %}
                        {% endfor %}

                        {% if shippingPrice is not null %}
                            {{ shippingPrice|currency }}
                        {% else %}
                            <span class="price-on-select">Auswählen</span>
                        {% endif %}
                    </div>
                </label>
            </div>
        {% endfor %}
    </div>
</form>
```

### Step 2: Verify CSS exists in index.html.twig

The CSS for `.raven-shipping-option`, `.raven-shipping-label`, etc. should already exist.

### Step 3: Commit and deploy

```bash
git add -A
git commit -m "fix(checkout): use standalone shipping template without extends"
git push origin main
```

### Step 4: Approve production deployment in GitHub Actions

---

## Why This Works

1. **No extends** = No inheritance chain = No variable scope issues
2. **Direct include** = Gets full `page` and `context` from parent
3. `page.shippingMethods` contains all available shipping methods with full data
4. `context.shippingMethod.id` is the currently selected method

---

## Expected Result

After deployment, shipping options will show:
- ✅ Method name (Standard, Express, International)
- ✅ Delivery time (if configured)
- ✅ Price (calculated or "Auswählen")
- ✅ Radio buttons with proper values
