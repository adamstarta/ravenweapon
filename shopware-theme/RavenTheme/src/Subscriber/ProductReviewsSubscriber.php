<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Loads product reviews on the product detail page.
 *
 * Shopware doesn't load reviews by default, so we need to fetch them
 * and add them to the product for display in the template.
 */
class ProductReviewsSubscriber implements EventSubscriberInterface
{
    private EntityRepository $productReviewRepository;

    public function __construct(EntityRepository $productReviewRepository)
    {
        $this->productReviewRepository = $productReviewRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $product = $event->getPage()->getProduct();
        $context = $event->getContext();

        // Build criteria to fetch approved reviews for this product
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $product->getId()));
        $criteria->addFilter(new EqualsFilter('status', true)); // Only approved reviews
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(50); // Limit to 50 reviews

        // Fetch reviews
        $reviews = $this->productReviewRepository->search($criteria, $context);

        // Set reviews on the product (this makes them available in the template as page.product.productReviews)
        $product->setProductReviews($reviews->getEntities());
    }
}
