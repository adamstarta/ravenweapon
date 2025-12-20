<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Redirects users to homepage after logout instead of login page.
 */
class LogoutRedirectSubscriber implements EventSubscriberInterface
{
    private RouterInterface $router;
    private bool $isLogout = false;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLogoutEvent::class => 'onCustomerLogout',
            KernelEvents::RESPONSE => ['onResponse', -100],
        ];
    }

    public function onCustomerLogout(CustomerLogoutEvent $event): void
    {
        $this->isLogout = true;
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$this->isLogout) {
            return;
        }

        $response = $event->getResponse();

        // Only modify redirect responses
        if (!$response instanceof RedirectResponse) {
            return;
        }

        // Redirect to homepage
        $homepageUrl = $this->router->generate('frontend.home.page');
        $event->setResponse(new RedirectResponse($homepageUrl));

        $this->isLogout = false;
    }
}
