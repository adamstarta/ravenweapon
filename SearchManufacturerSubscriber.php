<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SearchManufacturerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $manufacturerRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductSearchCriteriaEvent::class => 'onProductSearchCriteria',
            ProductListingCriteriaEvent::class => 'onProductListingCriteria',
            ProductSearchResultEvent::class => ['onProductSearchResult', -100],
            ProductListingResultEvent::class => ['onProductListingResult', -100],
        ];
    }

    public function onProductSearchCriteria(ProductSearchCriteriaEvent $event): void
    {
        $this->addManufacturerAggregation($event->getCriteria());
    }

    public function onProductListingCriteria(ProductListingCriteriaEvent $event): void
    {
        $this->addManufacturerAggregation($event->getCriteria());
    }

    public function onProductSearchResult(ProductSearchResultEvent $event): void
    {
        $this->enrichManufacturerAggregation($event->getResult(), $event->getContext());
    }

    public function onProductListingResult(ProductListingResultEvent $event): void
    {
        $this->enrichManufacturerAggregation($event->getResult(), $event->getContext());
    }

    private function addManufacturerAggregation(Criteria $criteria): void
    {
        // Add manufacturer association
        $criteria->addAssociation('manufacturer');

        // Add manufacturer aggregation for filter
        if ($criteria->getAggregation('manufacturer') === null) {
            $criteria->addAggregation(
                new TermsAggregation(
                    'manufacturer',
                    'manufacturerId'
                )
            );
        }
    }

    private function enrichManufacturerAggregation(ProductListingResult $result, $context): void
    {
        $aggregations = $result->getAggregations();

        /** @var TermsResult|null $manufacturerAggregation */
        $manufacturerAggregation = $aggregations->get('manufacturer');

        if (!$manufacturerAggregation instanceof TermsResult) {
            return;
        }

        // Get manufacturer IDs from aggregation buckets
        $manufacturerIds = [];
        foreach ($manufacturerAggregation->getBuckets() as $bucket) {
            if ($bucket->getKey()) {
                $manufacturerIds[] = $bucket->getKey();
            }
        }

        if (empty($manufacturerIds)) {
            return;
        }

        // Load manufacturer entities
        $criteria = new Criteria($manufacturerIds);
        $manufacturers = $this->manufacturerRepository->search($criteria, $context);

        // Create an entity that mimics what the template expects
        $manufacturerEntity = new ArrayEntity([
            'entities' => $manufacturers->getEntities(),
        ]);

        // Replace the aggregation with enriched data
        $aggregations->add($manufacturerEntity);
        $aggregations->set('manufacturer', $manufacturerEntity);
    }
}
