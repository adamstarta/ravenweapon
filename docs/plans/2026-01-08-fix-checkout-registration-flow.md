# Fix Checkout Registration Flow - Shopify-Style UX

**Date:** 2026-01-08
**Status:** Approved
**Priority:** High (blocking customer orders)

## Problem Statement

When a customer adds a product to cart and clicks "Zur Kasse" (checkout), they experience a broken, confusing flow:

1. User goes to `/checkout/register`
2. JavaScript immediately redirects to `/account/login` (lines 9-15 in template)
3. User clicks "Konto erstellen" → sent to `/account/register`
4. User fills form, submits → email verification sent
5. User clicks verification link → redirected to `/account` (account home)
6. **Cart context is lost, user is confused**

### Root Cause

The `/checkout/register` template has a JavaScript redirect that sends ALL non-logged-in users away before they can see the registration form:

```twig
{% if not context.customer %}
    <script>window.location.href = '{{ path('frontend.account.login.page') }}?redirectTo=frontend.checkout.confirm.page';</script>
```

The irony: The checkout/register page already HAS a beautiful Shopify-style form with guest/account options - users just never see it.

## Business Requirements

1. **Accounts are mandatory** (Swiss firearms regulations + Shopware setting)
2. **Email verification is required** before purchase
3. **Cart must persist** through the entire registration flow
4. **Single-page experience** - no bouncing between 5 different account pages
5. **Simple and intuitive** - like Shopify checkout

## Target Flow

```
Add to cart → Click "Zur Kasse" → /checkout/register →
   ↓ (user fills form on SAME page)
Account created + Email verification sent → Click email link →
   ↓
/checkout/confirm (WITH cart intact!)
```

## Technical Design

### 1. Remove JavaScript Redirect

**File:** `shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/register/index.html.twig`

**Change:** Delete lines 9-15 (the conditional redirect):
```twig
{% if not context.customer %}
    <script>window.location.href = '{{ path('frontend.account.login.page') }}...</script>
    <noscript>...</noscript>
{% else %}
```

**Result:** Non-logged-in users will see the registration form instead of being redirected.

### 2. Make Account Creation Mandatory

**Current:** Checkbox "Kundenkonto erstellen" that toggles password field visibility

**Change:**
- Remove the checkbox
- Always show password field (required)
- Add hidden input: `<input type="hidden" name="createCustomerAccount" value="1">`
- Update form labels to indicate account creation is required

### 3. Fix Post-Verification Redirect

**Challenge:** After email verification, Shopware redirects to `/account`. We need it to redirect to `/checkout/confirm`.

**Solution:** The registration form already has:
```html
<input type="hidden" name="redirectTo" value="frontend.checkout.confirm.page">
```

This should work IF:
- Shopware stores this in the session during registration
- The verification email respects this redirect

**Investigation needed:** Check if Shopware 6 automatically handles `redirectTo` through email verification or if we need a subscriber/event listener.

**Backup solution:** If Shopware doesn't pass the redirect through verification:
1. Create a PHP Subscriber that listens to `CustomerRegisterEvent`
2. Store `frontend.checkout.confirm.page` in session
3. On `CustomerLoginEvent` (after verification), check session and redirect

### 4. Update Form Structure

**Current structure (simplified):**
```
[Login Section - for existing customers]
    ──── oder ────
[Guest/Register Section]
    □ Kundenkonto erstellen (checkbox)
    [Password field - hidden by default]
    [Address fields]
    [Submit button]
```

**New structure:**
```
[Login Section - for existing customers]
    ──── oder ────
[Account Creation Section]
    [Salutation, Name fields]
    [Email field]
    [Password field - always visible, required]
    [Address fields]
    [Privacy checkbox]
    [Submit button: "Konto erstellen & weiter zur Zahlung"]
```

### 5. Handle Already-Logged-In Users

Add at the top of the template:
```twig
{% if context.customer %}
    <script>window.location.href = '{{ path('frontend.checkout.confirm.page') }}';</script>
{% endif %}
```

This redirects logged-in users directly to checkout confirm (skip registration).

## Implementation Steps

### Step 1: Modify checkout/register template
- [ ] Remove the redirect for non-logged-in users (lines 9-15)
- [ ] Keep redirect for logged-in users (to checkout confirm)
- [ ] Remove "Kundenkonto erstellen" checkbox
- [ ] Make password field always visible and required
- [ ] Add hidden input for `createCustomerAccount=1`
- [ ] Update button text to "Konto erstellen & weiter"
- [ ] Update section header text

### Step 2: Test the flow
- [ ] Add product to cart as guest
- [ ] Click checkout → should see registration form (no redirect)
- [ ] Fill form and submit
- [ ] Check email for verification link
- [ ] Click verification link
- [ ] Verify redirect goes to `/checkout/confirm` with cart

### Step 3: Handle verification redirect (if needed)
If Shopware doesn't respect `redirectTo` through verification:
- [ ] Create PHP subscriber for redirect handling
- [ ] Register subscriber in services.xml
- [ ] Test the complete flow again

## Files to Modify

1. **Primary:** `shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/register/index.html.twig`
2. **Maybe:** Create new PHP subscriber if verification redirect doesn't work
3. **Maybe:** `shopware-theme/RavenTheme/src/Resources/config/services.xml`

## Success Criteria

1. Customer can register directly on `/checkout/register` without being redirected to `/account/login`
2. After email verification, customer lands on `/checkout/confirm`
3. Cart is preserved throughout the entire flow
4. Flow feels seamless - no confusion about where to go next
5. All existing styling and layout preserved

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Email verification doesn't respect redirectTo | Create PHP subscriber to handle redirect |
| Cart gets lost during verification | Shopware ties cart to customer token - should persist |
| Existing customers confused by new flow | Login section still at top of page |

## Rollback Plan

If issues arise:
1. Restore the JavaScript redirect (revert the template change)
2. Users will see the old flow (bouncing between pages)
3. Not ideal but functional
