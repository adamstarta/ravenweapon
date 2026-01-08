# Login Redirect to Checkout (Server-Side) Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** When a user with cart items logs in at `/account/login`, redirect them to `/checkout/confirm` instead of `/account` using a server-side PHP subscriber.

**Architecture:** Create a `LoginRedirectSubscriber` that listens to `CustomerLoginEvent` to detect login, then intercepts the response via `KernelEvents::RESPONSE` to change the redirect destination if cart has items. Same pattern as existing `LogoutRedirectSubscriber`.

**Tech Stack:** PHP 8.3, Shopware 6.6, Symfony EventSubscriber

---

## Current State

| Scenario | Current Behavior | Problem |
|----------|------------------|---------|
| User with cart logs in | Redirects to `/account` | Loses checkout flow |
| JavaScript cart detection | Doesn't work reliably | Store API auth issues |

## Target State

| Scenario | New Behavior |
|----------|--------------|
| User with cart logs in | Redirects to `/checkout/confirm` |
| User without cart logs in | Redirects to `/account` (unchanged) |

---

### Task 1: Create LoginRedirectSubscriber

**Files:**
- Create: `shopware-theme/RavenTheme/src/Subscriber/LoginRedirectSubscriber.php`

**Step 1: Create the subscriber file**

```php
<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Redirects users to checkout after login if they have items in cart.
 * Instead of going to /account, users with cart items go to /checkout/confirm.
 */
class LoginRedirectSubscriber implements EventSubscriberInterface
{
    private RouterInterface $router;
    private CartService $cartService;
    private ?SalesChannelContext $salesChannelContext = null;
    private bool $isLogin = false;

    public function __construct(
        RouterInterface $router,
        CartService $cartService
    ) {
        $this->router = $router;
        $this->cartService = $cartService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLoginEvent::class => 'onCustomerLogin',
            KernelEvents::RESPONSE => ['onResponse', -100],
        ];
    }

    public function onCustomerLogin(CustomerLoginEvent $event): void
    {
        $this->isLogin = true;
        $this->salesChannelContext = $event->getSalesChannelContext();
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$this->isLogin || $this->salesChannelContext === null) {
            return;
        }

        $response = $event->getResponse();

        // Only modify redirect responses
        if (!$response instanceof RedirectResponse) {
            $this->resetState();
            return;
        }

        // Check if redirecting to account home
        $targetUrl = $response->getTargetUrl();
        $accountUrl = $this->router->generate('frontend.account.home.page');

        // Only intercept if going to account home page
        if (strpos($targetUrl, $accountUrl) === false && strpos($targetUrl, '/account') === false) {
            $this->resetState();
            return;
        }

        // Check if cart has items
        try {
            $cart = $this->cartService->getCart($this->salesChannelContext->getToken(), $this->salesChannelContext);

            if ($cart->getLineItems()->count() > 0) {
                // Cart has items - redirect to checkout
                $checkoutUrl = $this->router->generate('frontend.checkout.confirm.page');
                $event->setResponse(new RedirectResponse($checkoutUrl));
            }
        } catch (\Exception $e) {
            // If cart check fails, just continue with normal redirect
        }

        $this->resetState();
    }

    private function resetState(): void
    {
        $this->isLogin = false;
        $this->salesChannelContext = null;
    }
}
```

**Step 2: Verify file created**

Check that the file exists at the correct path.

---

### Task 2: Register Subscriber in services.xml

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/config/services.xml`

**Step 1: Add service definition**

Add this after the LogoutRedirectSubscriber service (around line 57):

```xml
        <!-- Login Redirect Subscriber - redirects to checkout after login if cart has items -->
        <service id="RavenTheme\Subscriber\LoginRedirectSubscriber">
            <argument type="service" id="router"/>
            <argument type="service" id="Shopware\Core\Checkout\Cart\SalesChannel\CartService"/>
            <tag name="kernel.event_subscriber"/>
        </service>
```

**Step 2: Verify XML is valid**

Ensure the XML structure is correct with proper indentation.

---

### Task 3: Remove JavaScript Cart Detection (Cleanup)

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/login/index.html.twig`

**Step 1: Remove the cart detection script**

Remove the entire `<script>` block that contains "CART-BASED REDIRECT" (around lines 900-951). This JavaScript approach is no longer needed since we're using server-side logic.

The script to remove starts with:
```javascript
<script>
// ========== CART-BASED REDIRECT ==========
```

And ends with:
```javascript
})();
</script>
```

**Step 2: Verify removal**

Ensure the login page still has the toast notification script but no cart detection script.

---

### Task 4: Optional - Keep Register Page JavaScript

**Files:**
- Review: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/register/index.html.twig`

**Decision:** The register page JavaScript can stay because:
1. New registrations create a new session, so server-side cart detection works differently
2. The sessionStorage flag for welcome toast is set in JavaScript
3. The server-side subscriber will also work for registrations (CustomerLoginEvent fires after registration too)

**No changes needed** - the JavaScript provides a backup and handles the welcome toast flag.

---

### Task 5: Commit and Deploy

**Step 1: Stage all changes**

```bash
git add shopware-theme/RavenTheme/src/Subscriber/LoginRedirectSubscriber.php
git add shopware-theme/RavenTheme/src/Resources/config/services.xml
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/login/index.html.twig
```

**Step 2: Commit**

```bash
git commit -m "feat(auth): server-side redirect to checkout after login if cart has items

- Add LoginRedirectSubscriber to handle login redirect
- Check cart items using CartService
- Redirect to /checkout/confirm if cart not empty
- Remove JavaScript cart detection from login page (unreliable)

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>"
```

**Step 3: Push to deploy**

```bash
git push origin main
```

---

### Task 6: Test the Flow

**Test Case 1: Login with cart items**
1. Log out (if logged in)
2. Add product to cart
3. Go to `/account/login`
4. Login with existing account
5. **Expected:** Redirect to `/checkout/confirm` with cart intact

**Test Case 2: Login without cart items**
1. Clear cart / start fresh session
2. Go to `/account/login`
3. Login with existing account
4. **Expected:** Redirect to `/account` (normal behavior)

**Test Case 3: Registration with cart items**
1. Log out
2. Add product to cart
3. Go to `/account/register`
4. Create new account
5. **Expected:** Redirect to `/checkout/confirm` with welcome toast

---

## Summary of Changes

| File | Change |
|------|--------|
| `Subscriber/LoginRedirectSubscriber.php` | NEW - Server-side login redirect logic |
| `config/services.xml` | Add LoginRedirectSubscriber service |
| `page/account/login/index.html.twig` | Remove JavaScript cart detection |

**Why server-side is better:**
- Reliable cart access via CartService
- No Store API authentication issues
- Same pattern as existing LogoutRedirectSubscriber
- Works regardless of JavaScript/cookies
