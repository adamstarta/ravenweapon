<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Loads products for navigation categories that have no grandchildren (level 4 categories).
 * These products are then displayed in the desktop dropdown menu instead of "Entdecken Sie...".
 */
class NavigationProductsSubscriber implements EventSubscriberInterface
{
    private SalesChannelRepository $productRepository;

    public function __construct(SalesChannelRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            HeaderPageletLoadedEvent::class => 'onHeaderLoaded',
        ];
    }

    public function onHeaderLoaded(HeaderPageletLoadedEvent $event): void
    {
        $header = $event->getPagelet();
        $context = $event->getSalesChannelContext();
        $navigation = $header->getNavigation();

        if ($navigation === null) {
            return;
        }

        $tree = $navigation->getTree();
        if ($tree === null || empty($tree)) {
            return;
        }

        // Collect all category IDs that need products
        // These are level 3 categories (subcategories) that have NO level 4 children
        $categoryProductsMap = [];

        foreach ($tree as $treeItem) {
            // treeItem is a main category (level 2)
            $children = $treeItem->getChildren();
            if (empty($children)) {
                continue;
            }

            foreach ($children as $childItem) {
                // childItem is a subcategory (level 3)
                $grandChildren = $childItem->getChildren();

                // Only load products if this category has NO grandchildren
                if (empty($grandChildren)) {
                    $category = $childItem->getCategory();
                    if ($category !== null) {
                        $categoryId = $category->getId();

                        // Load products for this category
                        $products = $this->loadCategoryProducts($categoryId, $context);

                        if ($products->count() > 0) {
                            $categoryProductsMap[$categoryId] = $products;
                        }
                    }
                }
            }
        }

        // Store the products map as an extension on the header
        if (!empty($categoryProductsMap)) {
            $header->addExtension('navigationProducts', new ArrayStruct($categoryProductsMap));
        }
    }

    /**
     * Load products for a specific category (max 8 products, sorted by name)
     */
    private function loadCategoryProducts(string $categoryId, $context): ProductCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('categories.id', $categoryId));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $criteria->setLimit(8); // Max 8 products in dropdown

        // Only load necessary fields for dropdown display
        $criteria->addAssociation('cover');

        $result = $this->productRepository->search($criteria, $context);

        return $result->getEntities();
    }
}
