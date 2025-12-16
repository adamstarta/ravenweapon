<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductDetailSubscriber implements EventSubscriberInterface
{
    private EntityRepository $mainCategoryRepository;
    private EntityRepository $categoryRepository;

    public function __construct(
        EntityRepository $mainCategoryRepository,
        EntityRepository $categoryRepository
    ) {
        $this->mainCategoryRepository = $mainCategoryRepository;
        $this->categoryRepository = $categoryRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $product = $page->getProduct();
        $context = $event->getSalesChannelContext();

        // Check if seoCategory is already set
        if ($product->getSeoCategory() !== null) {
            return;
        }

        // Try to get the main category for this product in the current sales channel
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $product->getId()));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()));
        $criteria->addAssociation('category');

        $mainCategories = $this->mainCategoryRepository->search($criteria, $context->getContext());

        if ($mainCategories->count() > 0) {
            $mainCategory = $mainCategories->first();
            $category = $mainCategory->get('category');

            if ($category instanceof CategoryEntity) {
                // Set the seoCategory on the product
                $product->setSeoCategory($category);
            }
        }
    }
}
