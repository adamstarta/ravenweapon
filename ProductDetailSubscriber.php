<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Storefront\Page\Product\ProductPageCriteriaEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductDetailSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageCriteriaEvent::class => 'onProductPageCriteria',
        ];
    }

    /**
     * Add productReviews association to product detail page
     */
    public function onProductPageCriteria(ProductPageCriteriaEvent $event): void
    {
        $criteria = $event->getCriteria();

        // Add productReviews association so we can display reviews on the product page
        $criteria->addAssociation('productReviews');
    }
}
