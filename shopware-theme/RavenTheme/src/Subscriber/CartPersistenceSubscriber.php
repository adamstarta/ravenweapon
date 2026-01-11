<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Persists customer cart across logout/login sessions.
 *
 * On logout: Saves cart to raven_customer_cart table (via kernel.request BEFORE logout processing)
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
            // Use kernel.request with high priority to save cart BEFORE logout processing
            KernelEvents::REQUEST => ['onKernelRequest', 100],
            CustomerLoginEvent::class => ['onCustomerLogin', 200],
        ];
    }

    /**
     * Intercept the logout request BEFORE it's processed to save the cart
     * while it still exists (session not yet invalidated).
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only handle main requests (not sub-requests)
        if (!$event->isMainRequest()) {
            return;
        }

        // Check if this is the logout route
        $pathInfo = $request->getPathInfo();
        if (!str_ends_with($pathInfo, '/account/logout')) {
            return;
        }

        file_put_contents('/tmp/cart_debug.log', date('Y-m-d H:i:s') . " - onKernelRequest: logout route detected\n", FILE_APPEND);

        // Get the sales channel context from the request
        $context = $request->attributes->get('sw-sales-channel-context');
        if (!$context instanceof SalesChannelContext) {
            file_put_contents('/tmp/cart_debug.log', date('Y-m-d H:i:s') . " - No SalesChannelContext found\n", FILE_APPEND);
            return;
        }

        $customer = $context->getCustomer();
        if (!$customer) {
            file_put_contents('/tmp/cart_debug.log', date('Y-m-d H:i:s') . " - No customer in context\n", FILE_APPEND);
            return;
        }

        file_put_contents('/tmp/cart_debug.log', date('Y-m-d H:i:s') . " - Customer ID: " . $customer->getId() . "\n", FILE_APPEND);

        try {
            $cart = $this->cartService->getCart($context->getToken(), $context);
            $itemCount = $cart->getLineItems()->count();

            file_put_contents('/tmp/cart_debug.log', date('Y-m-d H:i:s') . " - Cart items: " . $itemCount . "\n", FILE_APPEND);

            if ($itemCount === 0) {
                file_put_contents('/tmp/cart_debug.log', date('Y-m-d H:i:s') . " - Cart empty, nothing to save\n", FILE_APPEND);
                return;
            }

            $this->saveCustomerCart($customer->getId(), $cart);

            file_put_contents('/tmp/cart_debug.log', date('Y-m-d H:i:s') . " - Cart saved successfully! Items: " . $itemCount . "\n", FILE_APPEND);

            $this->logger->info('Cart saved for customer before logout', [
                'customerId' => $customer->getId(),
                'itemCount' => $itemCount,
            ]);
        } catch (\Exception $e) {
            file_put_contents('/tmp/cart_debug.log', date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
            $this->logger->error('Failed to save cart before logout', [
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
            $guestCart = $this->cartService->getCart($context->getToken(), $context);
            $guestItemCount = $guestCart->getLineItems()->count();

            $savedItems = $this->loadCustomerCart($customer->getId());

            if ($savedItems === null) {
                return;
            }

            $this->mergeCartItems($savedItems, $guestCart, $context);
            $this->deleteCustomerCart($customer->getId());

            $this->logger->info('Cart restored for customer on login', [
                'customerId' => $customer->getId(),
                'restoredItems' => count($savedItems),
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
            if ($currentCart->getLineItems()->has($itemData['id'])) {
                continue;
            }

            if ($itemData['type'] === 'product' && !empty($itemData['referencedId'])) {
                $lineItem = new \Shopware\Core\Checkout\Cart\LineItem\LineItem(
                    $itemData['id'],
                    $itemData['type'],
                    $itemData['referencedId'],
                    $itemData['quantity']
                );

                // Restore payload data (variant info, custom fields)
                if (!empty($itemData['payload'])) {
                    foreach ($itemData['payload'] as $key => $value) {
                        $lineItem->setPayloadValue($key, $value);
                    }
                }

                $this->cartService->add($currentCart, [$lineItem], $context);
            }
        }
    }
}
