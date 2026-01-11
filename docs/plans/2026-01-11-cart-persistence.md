# Cart Persistence - Save on Logout, Restore on Login

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Persist customer cart across logout/login sessions so products don't disappear.

**Architecture:** Create a `CartPersistenceSubscriber` that saves the cart to a custom database table on logout (keyed by customer ID), then restores and merges it with any guest cart on login.

**Tech Stack:** PHP 8.3, Shopware 6.6, Doctrine DBAL

---

## Task 1: Create Database Migration for Customer Cart Storage

**Files:**
- Create: `shopware-theme/RavenTheme/src/Migration/Migration1736600000CreateCustomerCartTable.php`

**Step 1: Create the migration file**

```php
<?php declare(strict_types=1);

namespace RavenTheme\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1736600000CreateCustomerCartTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1736600000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `raven_customer_cart` (
                `customer_id` BINARY(16) NOT NULL,
                `cart_data` LONGTEXT NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`customer_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // No destructive changes
    }
}
```

**Step 2: Verify syntax**

Run: `php -l shopware-theme/RavenTheme/src/Migration/Migration1736600000CreateCustomerCartTable.php`
Expected: `No syntax errors detected`

---

## Task 2: Create CartPersistenceSubscriber

**Files:**
- Create: `shopware-theme/RavenTheme/src/Subscriber/CartPersistenceSubscriber.php`

**Step 1: Create the subscriber**

```php
<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Persists customer cart across logout/login sessions.
 *
 * On logout: Saves cart to raven_customer_cart table
 * On login: Restores saved cart and merges with any guest cart items
 */
class CartPersistenceSubscriber implements EventSubscriberInterface
{
    private CartService $cartService;
    private Connection $connection;
    private LoggerInterface $logger;

    public function __construct(
        CartService $cartService,
        Connection $connection,
        LoggerInterface $logger
    ) {
        $this->cartService = $cartService;
        $this->connection = $connection;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLogoutEvent::class => ['onCustomerLogout', 100], // Run early, before session is destroyed
            CustomerLoginEvent::class => ['onCustomerLogin', 200],   // Run early, before redirect subscriber
        ];
    }

    public function onCustomerLogout(CustomerLogoutEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $customer = $event->getCustomer();

        if (!$customer) {
            return;
        }

        try {
            $cart = $this->cartService->getCart($context->getToken(), $context);

            // Only save if cart has items
            if ($cart->getLineItems()->count() === 0) {
                return;
            }

            $this->saveCustomerCart($customer->getId(), $cart);

            $this->logger->info('Cart saved for customer on logout', [
                'customerId' => $customer->getId(),
                'itemCount' => $cart->getLineItems()->count(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to save cart on logout', [
                'customerId' => $customer->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function onCustomerLogin(CustomerLoginEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $customer = $event->getCustomer();

        if (!$customer) {
            return;
        }

        try {
            // Get current guest cart (if any)
            $guestCart = $this->cartService->getCart($context->getToken(), $context);
            $guestItemCount = $guestCart->getLineItems()->count();

            // Load saved customer cart
            $savedCart = $this->loadCustomerCart($customer->getId());

            if ($savedCart === null) {
                $this->logger->debug('No saved cart found for customer', [
                    'customerId' => $customer->getId(),
                ]);
                return;
            }

            // Merge saved cart items into current cart
            $this->mergeCartItems($savedCart, $guestCart, $context);

            // Delete saved cart after successful restore
            $this->deleteCustomerCart($customer->getId());

            $this->logger->info('Cart restored for customer on login', [
                'customerId' => $customer->getId(),
                'restoredItems' => count($savedCart),
                'guestItems' => $guestItemCount,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to restore cart on login', [
                'customerId' => $customer->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function saveCustomerCart(string $customerId, Cart $cart): void
    {
        $lineItemsData = [];

        foreach ($cart->getLineItems() as $lineItem) {
            $lineItemsData[] = [
                'id' => $lineItem->getId(),
                'referencedId' => $lineItem->getReferencedId(),
                'type' => $lineItem->getType(),
                'quantity' => $lineItem->getQuantity(),
                'payload' => $lineItem->getPayload(),
            ];
        }

        $cartData = json_encode($lineItemsData);

        $this->connection->executeStatement(
            'INSERT INTO `raven_customer_cart` (`customer_id`, `cart_data`, `created_at`, `updated_at`)
             VALUES (:customerId, :cartData, NOW(), NOW())
             ON DUPLICATE KEY UPDATE `cart_data` = :cartData, `updated_at` = NOW()',
            [
                'customerId' => Uuid::fromHexToBytes($customerId),
                'cartData' => $cartData,
            ]
        );
    }

    private function loadCustomerCart(string $customerId): ?array
    {
        $result = $this->connection->fetchOne(
            'SELECT `cart_data` FROM `raven_customer_cart` WHERE `customer_id` = :customerId',
            ['customerId' => Uuid::fromHexToBytes($customerId)]
        );

        if (!$result) {
            return null;
        }

        return json_decode($result, true);
    }

    private function deleteCustomerCart(string $customerId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `raven_customer_cart` WHERE `customer_id` = :customerId',
            ['customerId' => Uuid::fromHexToBytes($customerId)]
        );
    }

    private function mergeCartItems(array $savedItems, Cart $currentCart, SalesChannelContext $context): void
    {
        foreach ($savedItems as $itemData) {
            // Skip if item already exists in current cart
            if ($currentCart->getLineItems()->has($itemData['id'])) {
                continue;
            }

            // Add product to cart using CartService
            if ($itemData['type'] === 'product' && !empty($itemData['referencedId'])) {
                $this->cartService->add(
                    $currentCart,
                    [
                        new \Shopware\Core\Checkout\Cart\LineItem\LineItem(
                            $itemData['id'],
                            $itemData['type'],
                            $itemData['referencedId'],
                            $itemData['quantity']
                        )
                    ],
                    $context
                );

                // Restore payload data (variant info, custom fields)
                $lineItem = $currentCart->getLineItems()->get($itemData['id']);
                if ($lineItem && !empty($itemData['payload'])) {
                    foreach ($itemData['payload'] as $key => $value) {
                        $lineItem->setPayloadValue($key, $value);
                    }
                }
            }
        }
    }
}
```

**Step 2: Verify syntax**

Run: `php -l shopware-theme/RavenTheme/src/Subscriber/CartPersistenceSubscriber.php`
Expected: `No syntax errors detected`

---

## Task 3: Register Subscriber in services.xml

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/config/services.xml`

**Step 1: Add the subscriber service definition**

After the existing `LoginRedirectSubscriber` service (around line 64), add:

```xml
        <!-- Cart Persistence Subscriber - saves cart on logout, restores on login -->
        <service id="RavenTheme\Subscriber\CartPersistenceSubscriber">
            <argument type="service" id="Shopware\Core\Checkout\Cart\SalesChannel\CartService"/>
            <argument type="service" id="Doctrine\DBAL\Connection"/>
            <argument type="service" id="logger"/>
            <tag name="kernel.event_subscriber"/>
        </service>
```

**Step 2: Verify XML syntax**

Run: `php -r "simplexml_load_file('shopware-theme/RavenTheme/src/Resources/config/services.xml') or die('Invalid XML');" && echo "XML is valid"`
Expected: `XML is valid`

---

## Task 4: Run Migration on Server

**Step 1: Deploy the code**

```bash
git add shopware-theme/RavenTheme/src/Migration/Migration1736600000CreateCustomerCartTable.php
git add shopware-theme/RavenTheme/src/Subscriber/CartPersistenceSubscriber.php
git add shopware-theme/RavenTheme/src/Resources/config/services.xml
git commit -m "feat(cart): add cart persistence across logout/login sessions"
git push origin main
```

**Step 2: Run migration on staging server**

After deployment completes (~2 min), SSH to server or use admin to run:
```bash
bin/console database:migrate --all RavenTheme
```

Or clear cache to trigger auto-migration:
```bash
bin/console cache:clear
```

---

## Task 5: Test Cart Persistence

**Step 1: Test on staging**

1. Go to https://developing.ravenweapon.ch
2. Login with test account: `alamajacint@gmail.com` / `jacint123`
3. Add 2 products to cart
4. Logout
5. Login again
6. Verify cart still has the 2 products

**Step 2: Test edge cases**

- Guest adds items → Login → Verify guest items + any saved items are merged
- Logout with empty cart → Login → Verify no errors
- Different customer logs in → Verify they get their own cart, not another customer's

---

## Summary

| Task | Description |
|------|-------------|
| 1 | Create database migration for `raven_customer_cart` table |
| 2 | Create `CartPersistenceSubscriber` with save/restore logic |
| 3 | Register subscriber in services.xml |
| 4 | Deploy and run migration |
| 5 | Test cart persistence on staging |

**Architecture Notes:**
- Cart data is serialized as JSON (line items + payloads)
- Uses `ON DUPLICATE KEY UPDATE` for upsert behavior
- Saved cart is deleted after successful restore (one-time use)
- Guest cart items are merged (not replaced) with saved cart
- Custom variant data (selectedColor, variantPrice, etc.) is preserved in payload
