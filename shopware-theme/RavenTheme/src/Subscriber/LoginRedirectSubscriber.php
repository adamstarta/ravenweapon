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
