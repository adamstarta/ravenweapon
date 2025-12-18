<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
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

        // Check if seoCategory is already set with full breadcrumb data
        $existingCategory = $product->getSeoCategory();
        if ($existingCategory !== null && $existingCategory->getBreadcrumb() !== null) {
            // Already have full breadcrumb data, load parent categories for URLs
            $this->loadBreadcrumbCategories($existingCategory, $page, $context->getContext());
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
                // Load the category with seoUrls for proper URL generation
                $categoryCriteria = new Criteria([$category->getId()]);
                $categoryCriteria->addAssociation('seoUrls');

                $fullCategory = $this->categoryRepository->search($categoryCriteria, $context->getContext())->first();

                if ($fullCategory instanceof CategoryEntity) {
                    // Set the seoCategory on the product
                    $product->setSeoCategory($fullCategory);

                    // Load all parent categories for breadcrumb URLs
                    $this->loadBreadcrumbCategories($fullCategory, $page, $context->getContext());
                }
            }
        }
    }

    /**
     * Load all parent categories in the breadcrumb chain with their SEO URLs
     * and store them as a page extension for the template to use
     */
    private function loadBreadcrumbCategories(CategoryEntity $category, $page, $context): void
    {
        // Use the category's path property to get parent category IDs
        // Path format is: |uuid1|uuid2|uuid3| (pipe-separated)
        $path = $category->getPath();

        $categoryIds = [];

        if (!empty($path)) {
            // Parse path string to extract category IDs
            $pathIds = array_filter(explode('|', $path), function($id) {
                // Only keep valid UUID-like strings (32 hex chars with dashes = 36 chars)
                return !empty($id) && strlen($id) >= 32;
            });
            $categoryIds = array_values($pathIds);
        }

        // Add the current category itself
        $categoryIds[] = $category->getId();

        if (empty($categoryIds)) {
            return;
        }

        // Load all breadcrumb categories with their SEO URLs
        $criteria = new Criteria($categoryIds);
        $criteria->addAssociation('seoUrls');

        $categories = $this->categoryRepository->search($criteria, $context);

        // Build ordered breadcrumb array with full category data (following path order)
        $breadcrumbCategories = [];
        foreach ($categoryIds as $categoryId) {
            $cat = $categories->get($categoryId);
            if ($cat instanceof CategoryEntity) {
                $breadcrumbCategories[] = $cat;
            }
        }

        // Store as page extension for template access
        $page->addExtension('breadcrumbCategories', new CategoryCollection($breadcrumbCategories));
    }
}
