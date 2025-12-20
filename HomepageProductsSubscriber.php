<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopware\Storefront\Page\GenericPageLoadedEvent;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Page;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class HomepageProductsSubscriber implements EventSubscriberInterface
{
    private SalesChannelRepository $productRepository;
    private EntityRepository $manufacturerRepository;
    private EntityRepository $categoryRepository;

    public function __construct(
        SalesChannelRepository $productRepository,
        EntityRepository $manufacturerRepository,
        EntityRepository $categoryRepository
    ) {
        $this->productRepository = $productRepository;
        $this->manufacturerRepository = $manufacturerRepository;
        $this->categoryRepository = $categoryRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NavigationPageLoadedEvent::class => 'onNavigationPageLoaded',
            GenericPageLoadedEvent::class => 'onGenericPageLoaded',
            HeaderPageletLoadedEvent::class => 'onHeaderPageletLoaded',
        ];
    }

    /**
     * Add navigation categories to the header pagelet
     * This makes categories available in the header template
     */
    public function onHeaderPageletLoaded(HeaderPageletLoadedEvent $event): void
    {
        $pagelet = $event->getPagelet();
        $context = $event->getSalesChannelContext();

        // Skip if already loaded
        if ($pagelet->hasExtension('navCategories')) {
            return;
        }

        $rootCategoryId = $context->getSalesChannel()->getNavigationCategoryId();

        // Get main categories with 3 levels deep + seoUrls for dynamic navigation
        $categoryCriteria = new Criteria();
        $categoryCriteria->addFilter(new EqualsFilter('parentId', $rootCategoryId));
        $categoryCriteria->addFilter(new EqualsFilter('active', true));
        $categoryCriteria->addFilter(new EqualsFilter('visible', true));
        $categoryCriteria->addAssociation('media');
        $categoryCriteria->addAssociation('seoUrls');
        $categoryCriteria->addAssociation('children');
        $categoryCriteria->addAssociation('children.seoUrls');
        $categoryCriteria->addAssociation('children.children');
        $categoryCriteria->addAssociation('children.children.seoUrls');
        $categoryCriteria->addAssociation('children.products');                 // Level 2 products (for categories like Raven Weapons)
        $categoryCriteria->addAssociation('children.products.seoUrls');         // Level 2 product SEO URLs
        $categoryCriteria->addSorting(new FieldSorting('autoIncrement', FieldSorting::ASCENDING));
        $categoryCriteria->setLimit(20);

        $categories = $this->categoryRepository->search($categoryCriteria, $context->getContext());

        // Add categories to header pagelet as extension
        $pagelet->addExtension('navCategories', $categories->getEntities());

        // Load products for categories without subcategories (like Raven Weapons)
        $categoryProducts = $this->loadProductsForLeafCategories($categories->getEntities(), $context);
        if (!empty($categoryProducts)) {
            $pagelet->addExtension('categoryProducts', new ArrayStruct($categoryProducts));
        }
    }

    /**
     * Handle generic page loads (including CMS/landing pages like homepage)
     * Also load navigation categories for ALL pages
     */
    public function onGenericPageLoaded(GenericPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $context = $event->getSalesChannelContext();

        // ALWAYS load navigation categories for header dropdown menus
        if (!$page->hasExtension('navigationCategories')) {
            $this->loadNavigationCategories($page, $context);
        }

        // Check if this is the homepage (CMS landing page at root)
        $request = $event->getRequest();
        $routeName = $request->attributes->get('_route');

        // Only load homepage-specific data for homepage/landing pages
        if ($routeName !== 'frontend.home.page' && $routeName !== 'frontend.landing.page' && $routeName !== 'frontend.cms.page') {
            return;
        }

        $this->loadHomepageData($page, $context);
    }

    public function onNavigationPageLoaded(NavigationPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $context = $event->getSalesChannelContext();

        // ALWAYS load navigation categories for header dropdown menus
        if (!$page->hasExtension('navigationCategories')) {
            $this->loadNavigationCategories($page, $context);
        }

        // Check if this is the homepage by looking at the navigation ID
        $navigationId = $context->getSalesChannel()->getNavigationCategoryId();

        // Get current category from the page
        if (method_exists($page, 'getNavigationId')) {
            $currentCategoryId = $page->getNavigationId();
            // Only load homepage products if we're on the root navigation (homepage)
            if ($currentCategoryId !== $navigationId) {
                return;
            }
        }

        $this->loadHomepageData($page, $context);
    }

    /**
     * Load all homepage data: products, manufacturers, and categories
     */
    private function loadHomepageData(Page $page, SalesChannelContext $context): void
    {
        // Skip if already loaded
        if ($page->hasExtension('homepageProducts')) {
            return;
        }

        // Find category IDs by name for Raven Weapons and Raven Caliber Kit
        $ravenWeaponsCategoryId = $this->findCategoryIdByName('Raven Weapons', $context);
        $ravenCaliberKitCategoryId = $this->findCategoryIdByName('Raven Caliber Kit', $context);

        $allProducts = [];

        // Load 3 products from Raven Weapons category (rifles)
        if ($ravenWeaponsCategoryId) {
            $riflesCriteria = new Criteria();
            $riflesCriteria->setLimit(3);
            $riflesCriteria->addFilter(new ProductAvailableFilter($context->getSalesChannel()->getId()));
            $riflesCriteria->addFilter(new EqualsFilter('categories.id', $ravenWeaponsCategoryId));
            $riflesCriteria->addAssociation('productReviews');
            $riflesCriteria->addAssociation('cover');
            $riflesCriteria->addAssociation('manufacturer');
            $riflesCriteria->addAssociation('categories');
            $riflesCriteria->addSorting(new FieldSorting('productNumber', FieldSorting::ASCENDING));

            $rifles = $this->productRepository->search($riflesCriteria, $context);
            foreach ($rifles->getEntities() as $product) {
                $allProducts[$product->getId()] = $product;
            }
        }

        // Load 3 products from Raven Caliber Kit category
        if ($ravenCaliberKitCategoryId) {
            $caliberCriteria = new Criteria();
            $caliberCriteria->setLimit(3);
            $caliberCriteria->addFilter(new ProductAvailableFilter($context->getSalesChannel()->getId()));
            $caliberCriteria->addFilter(new EqualsFilter('categories.id', $ravenCaliberKitCategoryId));
            $caliberCriteria->addAssociation('productReviews');
            $caliberCriteria->addAssociation('cover');
            $caliberCriteria->addAssociation('manufacturer');
            $caliberCriteria->addAssociation('categories');
            $caliberCriteria->addSorting(new FieldSorting('productNumber', FieldSorting::ASCENDING));

            $caliberKits = $this->productRepository->search($caliberCriteria, $context);
            foreach ($caliberKits->getEntities() as $product) {
                $allProducts[$product->getId()] = $product;
            }
        }

        // Fallback: if no products found from specific categories, load 6 best sellers
        if (empty($allProducts)) {
            $fallbackCriteria = new Criteria();
            $fallbackCriteria->setLimit(6);
            $fallbackCriteria->addFilter(new ProductAvailableFilter($context->getSalesChannel()->getId()));
            $fallbackCriteria->addAssociation('productReviews');
            $fallbackCriteria->addAssociation('cover');
            $fallbackCriteria->addAssociation('manufacturer');
            $fallbackCriteria->addAssociation('categories');
            $fallbackCriteria->addSorting(new FieldSorting('productNumber', FieldSorting::ASCENDING));

            $products = $this->productRepository->search($fallbackCriteria, $context);
            $page->addExtension('homepageProducts', $products->getEntities());
        } else {
            // Create a ProductCollection from the merged products
            $productCollection = new \Shopware\Core\Content\Product\ProductCollection($allProducts);
            $page->addExtension('homepageProducts', $productCollection);
        }

        // Load manufacturers (brands) for homepage - get ALL manufacturers, not just those with logos
        $manufacturerCriteria = new Criteria();
        $manufacturerCriteria->setLimit(8);
        $manufacturerCriteria->addAssociation('media');

        $manufacturers = $this->manufacturerRepository->search($manufacturerCriteria, $context->getContext());

        // Add to page extensions
        $page->addExtension('homepageManufacturers', $manufacturers->getEntities());

        // Load main navigation categories for homepage
        $rootCategoryId = $context->getSalesChannel()->getNavigationCategoryId();

        $categoryCriteria = new Criteria();
        $categoryCriteria->addFilter(new EqualsFilter('parentId', $rootCategoryId));
        $categoryCriteria->addFilter(new EqualsFilter('active', true));
        $categoryCriteria->addFilter(new EqualsFilter('visible', true));
        $categoryCriteria->addAssociation('media');
        $categoryCriteria->addSorting(new FieldSorting('autoIncrement', FieldSorting::ASCENDING));
        $categoryCriteria->setLimit(6);

        $categories = $this->categoryRepository->search($categoryCriteria, $context->getContext());

        // Add to page extensions
        $page->addExtension('homepageCategories', $categories->getEntities());
    }

    /**
     * Load navigation categories for header menus on ALL pages
     * Loads 3-level deep category tree with seoUrls for dynamic navigation
     */
    private function loadNavigationCategories(Page $page, SalesChannelContext $context): void
    {
        $rootCategoryId = $context->getSalesChannel()->getNavigationCategoryId();

        // Get main categories with 3 levels deep + seoUrls for dynamic links
        $categoryCriteria = new Criteria();
        $categoryCriteria->addFilter(new EqualsFilter('parentId', $rootCategoryId));
        $categoryCriteria->addFilter(new EqualsFilter('active', true));
        $categoryCriteria->addFilter(new EqualsFilter('visible', true));
        $categoryCriteria->addAssociation('media');
        $categoryCriteria->addAssociation('seoUrls');                           // Level 1 SEO URLs
        $categoryCriteria->addAssociation('children');                          // Level 2
        $categoryCriteria->addAssociation('children.seoUrls');                  // Level 2 SEO URLs
        $categoryCriteria->addAssociation('children.children');                 // Level 3
        $categoryCriteria->addAssociation('children.children.seoUrls');         // Level 3 SEO URLs
        $categoryCriteria->addAssociation('children.products');                 // Level 2 products (for categories like Raven Weapons)
        $categoryCriteria->addAssociation('children.products.seoUrls');         // Level 2 product SEO URLs
        $categoryCriteria->addSorting(new FieldSorting('autoIncrement', FieldSorting::ASCENDING));
        $categoryCriteria->setLimit(20);

        $categories = $this->categoryRepository->search($categoryCriteria, $context->getContext());

        // Add categories to page extensions for header navigation
        $page->addExtension('navCategories', $categories->getEntities());

        // Load products for categories without subcategories (like Raven Weapons)
        $categoryProducts = $this->loadProductsForLeafCategories($categories->getEntities(), $context);
        if (!empty($categoryProducts)) {
            $page->addExtension('categoryProducts', new ArrayStruct($categoryProducts));
        }

        // Also set homepageCategories for backward compatibility
        if (!$page->hasExtension('homepageCategories')) {
            $page->addExtension('homepageCategories', $categories->getEntities());
        }
    }

    /**
     * Find category ID by name
     */
    private function findCategoryIdByName(string $categoryName, SalesChannelContext $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $categoryName));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->setLimit(1);

        $result = $this->categoryRepository->search($criteria, $context->getContext());

        if ($result->count() > 0) {
            return $result->first()->getId();
        }

        return null;
    }

    /**
     * Load products for leaf categories (categories without subcategories)
     * This is used to show products in navigation dropdowns for categories like "Raven Weapons"
     */
    private function loadProductsForLeafCategories($categories, SalesChannelContext $context): array
    {
        $categoryProducts = [];

        foreach ($categories as $mainCategory) {
            // Check children (level 2 categories like "Waffen")
            if (!$mainCategory->getChildren()) {
                continue;
            }

            foreach ($mainCategory->getChildren() as $childCategory) {
                // Skip if this child has its own children (subcategories)
                if ($childCategory->getChildren() && $childCategory->getChildren()->count() > 0) {
                    continue;
                }

                // This is a leaf category - load its products
                $categoryId = $childCategory->getId();

                $productCriteria = new Criteria();
                $productCriteria->addFilter(new EqualsFilter('categories.id', $categoryId));
                $productCriteria->addFilter(new ProductAvailableFilter($context->getSalesChannel()->getId()));
                $productCriteria->addAssociation('seoUrls');
                $productCriteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
                $productCriteria->setLimit(10);

                $products = $this->productRepository->search($productCriteria, $context);

                if ($products->count() > 0) {
                    // Category ID is already hex string, just lowercase it
                    $categoryProducts[strtolower($categoryId)] = $products->getEntities();
                }
            }
        }

        return $categoryProducts;
    }
}
