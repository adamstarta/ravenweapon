<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Loads delivery time association for shipping methods on checkout confirm page
 */
class ShippingDeliveryTimeSubscriber implements EventSubscriberInterface
{
    private EntityRepository $shippingMethodRepository;

    public function __construct(EntityRepository $shippingMethodRepository)
    {
        $this->shippingMethodRepository = $shippingMethodRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onCheckoutConfirmPageLoaded',
        ];
    }

    public function onCheckoutConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $context = $event->getSalesChannelContext();
        $shippingMethods = $page->getShippingMethods();

        if ($shippingMethods->count() === 0) {
            return;
        }

        // Get all shipping method IDs
        $shippingMethodIds = $shippingMethods->getIds();

        // Reload shipping methods with deliveryTime association
        $criteria = new Criteria($shippingMethodIds);
        $criteria->addAssociation('deliveryTime');

        $enrichedShippingMethods = $this->shippingMethodRepository->search(
            $criteria,
            $context->getContext()
        );

        // Update each shipping method with the delivery time data
        foreach ($shippingMethods as $shippingMethod) {
            $enrichedMethod = $enrichedShippingMethods->get($shippingMethod->getId());
            if ($enrichedMethod && $enrichedMethod->getDeliveryTime()) {
                $shippingMethod->setDeliveryTime($enrichedMethod->getDeliveryTime());
            }
        }
    }
}
