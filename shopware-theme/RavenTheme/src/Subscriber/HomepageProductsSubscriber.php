<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Storefront\Page\LandingPage\LandingPageLoadedEvent;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Loads featured products for the homepage.
 * Displays exactly: 3 Caliber Kits + 3 Raven Rifles
 */
class HomepageProductsSubscriber implements EventSubscriberInterface
{
    private SalesChannelRepository $productRepository;

    public function __construct(SalesChannelRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NavigationPageLoadedEvent::class => 'onPageLoaded',
            LandingPageLoadedEvent::class => 'onPageLoaded',
        ];
    }

    public function onPageLoaded($event): void
    {
        $page = $event->getPage();
        $context = $event->getSalesChannelContext();

        // Combined products for homepage
        $allProducts = new ProductCollection();

        // 1. Load 3 Caliber Kits (products with "CALIBER KIT" in name)
        $caliberCriteria = new Criteria();
        $caliberCriteria->addFilter(new EqualsFilter('active', true));
        $caliberCriteria->addFilter(new ContainsFilter('name', 'CALIBER KIT'));
        $caliberCriteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $caliberCriteria->setLimit(3);
        $caliberCriteria->addAssociation('cover');
        $caliberCriteria->addAssociation('manufacturer');

        $caliberKits = $this->productRepository->search($caliberCriteria, $context);
        foreach ($caliberKits->getEntities() as $product) {
            $allProducts->add($product);
        }

        // 2. Load 3 Raven Rifles (products with "RAVEN" in name, excluding Caliber Kits)
        $rifleCriteria = new Criteria();
        $rifleCriteria->addFilter(new EqualsFilter('active', true));
        $rifleCriteria->addFilter(new ContainsFilter('name', 'RAVEN'));
        $rifleCriteria->addFilter(new NotFilter(
            MultiFilter::CONNECTION_AND,
            [new ContainsFilter('name', 'CALIBER')]
        ));
        $rifleCriteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $rifleCriteria->setLimit(3);
        $rifleCriteria->addAssociation('cover');
        $rifleCriteria->addAssociation('manufacturer');

        $rifles = $this->productRepository->search($rifleCriteria, $context);
        foreach ($rifles->getEntities() as $product) {
            $allProducts->add($product);
        }

        $page->addExtension('homepageProducts', $allProducts);
    }
}
