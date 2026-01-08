# Registration Redirect to Checkout Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** After login/registration at `/account/login` or `/account/register`, redirect users to checkout if they have items in cart, with welcome toast for new registrations.

**Architecture:** JavaScript-based cart detection using Shopware's Store API. On page load, check if cart has items. If yes, update the hidden `redirectTo` input to point to checkout instead of account home. Welcome toast already works via sessionStorage flag.

**Tech Stack:** Twig templates, JavaScript, Shopware Store API

---

## Current State

| Page | Current Redirect | Problem |
|------|------------------|---------|
| `/account/login` | `frontend.account.home.page` | Loses checkout flow |
| `/account/register` | `frontend.account.home.page` | Loses checkout flow |

## Target State

| Page | New Redirect (if cart has items) | New Redirect (empty cart) |
|------|----------------------------------|---------------------------|
| `/account/login` | `frontend.checkout.confirm.page` | `frontend.account.home.page` |
| `/account/register` | `frontend.checkout.confirm.page` | `frontend.account.home.page` |

---

### Task 1: Add Cart Detection to Register Page

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/register/index.html.twig`

**Step 1: Add cart detection JavaScript**

Find the existing JavaScript section (around line 1166) and add cart detection code at the beginning of the DOMContentLoaded handler:

```javascript
// ========== CART-BASED REDIRECT ==========
// If user has items in cart, redirect to checkout after registration
(async function() {
    try {
        const response = await fetch('/store-api/checkout/cart', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'sw-access-key': document.querySelector('meta[name="sw-access-key"]')?.content || ''
            }
        });
        const cart = await response.json();

        if (cart.lineItems && cart.lineItems.length > 0) {
            // Cart has items - update redirect to checkout
            const redirectInput = document.querySelector('input[name="redirectTo"]');
            if (redirectInput && redirectInput.value === 'frontend.account.home.page') {
                redirectInput.value = 'frontend.checkout.confirm.page';
            }
        }
    } catch (e) {
        console.log('Cart check failed, using default redirect');
    }
})();
```

**Step 2: Verify the change**

1. Clear cart, go to `/account/register`, inspect hidden input - should show `frontend.account.home.page`
2. Add item to cart, go to `/account/register`, inspect hidden input - should show `frontend.checkout.confirm.page`

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/register/index.html.twig
git commit -m "feat(auth): redirect to checkout after registration if cart has items"
```

---

### Task 2: Add Cart Detection to Login Page

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/login/index.html.twig`

**Step 1: Add cart detection JavaScript**

Add a script block at the end of the template (before `{% endblock %}`):

```javascript
<script>
// ========== CART-BASED REDIRECT ==========
// If user has items in cart, redirect to checkout after login
(async function() {
    try {
        const response = await fetch('/store-api/checkout/cart', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'sw-access-key': document.querySelector('meta[name="sw-access-key"]')?.content || ''
            }
        });
        const cart = await response.json();

        if (cart.lineItems && cart.lineItems.length > 0) {
            // Cart has items - update redirect to checkout
            const redirectInput = document.querySelector('input[name="redirectTo"]');
            if (redirectInput && redirectInput.value === 'frontend.account.home.page') {
                redirectInput.value = 'frontend.checkout.confirm.page';
            }
        }
    } catch (e) {
        console.log('Cart check failed, using default redirect');
    }
})();
</script>
```

**Step 2: Verify the change**

1. Clear cart, go to `/account/login`, inspect hidden input - should show `frontend.account.home.page`
2. Add item to cart, go to `/account/login`, inspect hidden input - should show `frontend.checkout.confirm.page`

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/login/index.html.twig
git commit -m "feat(auth): redirect to checkout after login if cart has items"
```

---

### Task 3: Ensure Welcome Toast Works for All Registration Paths

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/register/index.html.twig`

**Step 1: Update sessionStorage flag logic**

The current code (lines 1173-1179) only sets the flag if redirecting to checkout. This is correct - we only want the welcome toast when going to checkout. Verify this still works with the new cart detection.

Current code:
```javascript
registerForm.addEventListener('submit', function() {
    const redirectInput = registerForm.querySelector('input[name="redirectTo"]');
    if (redirectInput && redirectInput.value.includes('checkout')) {
        sessionStorage.setItem('ravenJustRegistered', 'true');
    }
});
```

This will work because by the time the form is submitted, the cart detection will have already updated the `redirectTo` input value.

**Step 2: Verify end-to-end flow**

1. Add item to cart (not logged in)
2. Go to `/account/register`
3. Fill form and submit
4. Should redirect to `/checkout/confirm`
5. Should see welcome toast "Willkommen [Name] bei Raven Weapon AG!"

**Step 3: Commit (if any changes needed)**

```bash
git add -A
git commit -m "fix(auth): ensure welcome toast works with cart-based redirect"
```

---

### Task 4: Test Complete Flow

**Test Case 1: Registration with cart items**
1. Add product to cart (not logged in)
2. Go to `/account/register` directly
3. Fill registration form
4. Submit
5. **Expected:** Redirect to `/checkout/confirm` with cart intact + welcome toast

**Test Case 2: Registration without cart items**
1. Clear cart
2. Go to `/account/register`
3. Fill registration form
4. Submit
5. **Expected:** Redirect to `/account/home` (account dashboard)

**Test Case 3: Login with cart items**
1. Log out
2. Add product to cart
3. Go to `/account/login`
4. Login with existing account
5. **Expected:** Redirect to `/checkout/confirm` with cart intact

**Test Case 4: Login without cart items**
1. Clear cart
2. Go to `/account/login`
3. Login with existing account
4. **Expected:** Redirect to `/account/home`

**Test Case 5: Checkout flow (existing)**
1. Add product to cart
2. Click checkout button
3. Should go to `/account/login`
4. Click register link
5. Go to `/account/register`
6. Fill form and submit
7. **Expected:** Redirect to `/checkout/confirm` with welcome toast

---

### Task 5: Final Commit and Deploy

**Step 1: Verify all changes**

```bash
git status
git diff
```

**Step 2: Push to trigger deployment**

```bash
git push origin main
```

**Step 3: Test on staging**

- Test at https://developing.ravenweapon.ch
- Verify all 5 test cases pass

**Step 4: Approve production deployment**

- Go to GitHub Actions
- Approve production deployment
- Test at https://ravenweapon.ch

---

## Summary of Changes

| File | Change |
|------|--------|
| `page/account/register/index.html.twig` | Add cart detection JS to update redirectTo |
| `page/account/login/index.html.twig` | Add cart detection JS to update redirectTo |

**No backend changes required.** Pure frontend JavaScript solution using Shopware's Store API.
