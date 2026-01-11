# Add Billing Address to Customer Order Confirmation Email

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Display dynamic billing address in the customer order confirmation email.

**Architecture:** Extract billing address from `OrderEntity->getBillingAddress()`, format it as HTML, and add a new section in the email template between the greeting and bank details/product table.

**Tech Stack:** PHP 8.3, Shopware 6.6, Symfony Mailer

---

## Task 1: Extract Billing Address from Order

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Subscriber/CustomerOrderConfirmationSubscriber.php:77-95`

**Step 1: Add billing address extraction in sendOrderConfirmation()**

After line 84 (`$isBankTransfer = ...`), add:

```php
// Get billing address
$billingAddress = $order->getBillingAddress();
$billingInfo = '';
if ($billingAddress) {
    $billingInfo = sprintf(
        "%s %s\n%s\n%s %s\n%s",
        $billingAddress->getFirstName(),
        $billingAddress->getLastName(),
        $billingAddress->getStreet(),
        $billingAddress->getZipcode(),
        $billingAddress->getCity(),
        $billingAddress->getCountry()?->getName() ?? 'Schweiz'
    );
}
```

**Step 2: Verify no syntax errors**

Run: `php -l shopware-theme/RavenTheme/src/Subscriber/CustomerOrderConfirmationSubscriber.php`
Expected: `No syntax errors detected`

---

## Task 2: Update buildHtmlEmail() Method Signature

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Subscriber/CustomerOrderConfirmationSubscriber.php`

**Step 1: Add $billingInfo parameter to buildHtmlEmail()**

Change method signature from:
```php
private function buildHtmlEmail(
    string $orderNumber,
    string $orderDate,
    string $greeting,
    array $items,
    string $subtotal,
    string $shippingMethodName,
    string $shippingCost,
    string $totalAmount,
    bool $isBankTransfer
): string {
```

To:
```php
private function buildHtmlEmail(
    string $orderNumber,
    string $orderDate,
    string $greeting,
    string $billingInfo,
    array $items,
    string $subtotal,
    string $shippingMethodName,
    string $shippingCost,
    string $totalAmount,
    bool $isBankTransfer
): string {
```

**Step 2: Update the method call in sendOrderConfirmation()**

Change from:
```php
$htmlContent = $this->buildHtmlEmail(
    $orderNumber,
    $orderDate,
    $greeting,
    $items,
    ...
```

To:
```php
$htmlContent = $this->buildHtmlEmail(
    $orderNumber,
    $orderDate,
    $greeting,
    $billingInfo,
    $items,
    ...
```

---

## Task 3: Add Billing Address HTML Section

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Subscriber/CustomerOrderConfirmationSubscriber.php` (buildHtmlEmail method)

**Step 1: Add billing address section in HTML template**

After the greeting `</p>` and before `{$bankDetailsHtml}`, add:

```php
            <!-- Billing Address -->
            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                <h3 style="margin: 0 0 10px 0; color: #D97706; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    Rechnungsadresse
                </h3>
                <p style="margin: 0; font-size: 14px; color: #374151; white-space: pre-line; line-height: 1.5;">{$billingInfo}</p>
            </div>
```

---

## Task 4: Update buildTextEmail() Method

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Subscriber/CustomerOrderConfirmationSubscriber.php`

**Step 1: Add $billingInfo parameter to buildTextEmail()**

Update method signature to include `string $billingInfo` parameter (same position as HTML method).

**Step 2: Update the method call in sendOrderConfirmation()**

Add `$billingInfo` to the buildTextEmail() call.

**Step 3: Add billing address section in text template**

After the greeting, add:

```php
-------------------------------------
RECHNUNGSADRESSE
-------------------------------------
{$billingInfo}

```

---

## Task 5: Commit and Deploy

**Step 1: Verify PHP syntax**

Run: `php -l shopware-theme/RavenTheme/src/Subscriber/CustomerOrderConfirmationSubscriber.php`
Expected: `No syntax errors detected`

**Step 2: Stage and commit**

```bash
git add shopware-theme/RavenTheme/src/Subscriber/CustomerOrderConfirmationSubscriber.php
git commit -m "feat(email): add dynamic billing address to customer order confirmation"
```

**Step 3: Push to deploy**

```bash
git push origin main
```

**Step 4: Test on staging**

1. Place test order on https://developing.ravenweapon.ch
2. Check email - billing address should appear after greeting
3. Verify mobile Gmail displays correctly (no overflow)

---

## Summary

| Task | Description | Time |
|------|-------------|------|
| 1 | Extract billing address from order | 2 min |
| 2 | Update method signatures | 3 min |
| 3 | Add HTML billing section | 3 min |
| 4 | Update text email | 2 min |
| 5 | Commit and deploy | 2 min |

**Total: ~12 minutes**
