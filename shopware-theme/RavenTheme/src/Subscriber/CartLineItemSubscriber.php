<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CartLineItemSubscriber implements EventSubscriberInterface
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeLineItemAddedEvent::class => 'onLineItemAdded',
        ];
    }

    public function onLineItemAdded(BeforeLineItemAddedEvent $event): void
    {
        $lineItem = $event->getLineItem();

        if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        // Get selected color directly from request (simple field name)
        $selectedColor = $request->request->get('selectedColor');

        if ($selectedColor && !empty($selectedColor) && is_string($selectedColor)) {
            // Get existing payload or create new one
            $payload = $lineItem->getPayload();

            // Add selected color to payload
            $payload['selectedColor'] = $selectedColor;

            $lineItem->setPayload($payload);
        }
    }
}
