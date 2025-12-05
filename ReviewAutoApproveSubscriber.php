<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReviewAutoApproveSubscriber implements EventSubscriberInterface
{
    private EntityRepository $reviewRepository;
    private ProductIndexer $productIndexer;

    public function __construct(
        EntityRepository $reviewRepository,
        ProductIndexer $productIndexer
    ) {
        $this->reviewRepository = $reviewRepository;
        $this->productIndexer = $productIndexer;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'product_review.written' => 'onReviewWritten',
        ];
    }

    /**
     * Auto-approve reviews and trigger product indexer to update ratingAverage
     */
    public function onReviewWritten(EntityWrittenEvent $event): void
    {
        $context = $event->getContext();
        $productIds = [];
        $reviewIds = [];

        foreach ($event->getWriteResults() as $result) {
            $payload = $result->getPayload();

            // Only process if this is a new review (has id)
            if (!isset($payload['id'])) {
                continue;
            }

            $reviewIds[] = $payload['id'];

            // Update the review to be approved (status = true)
            $this->reviewRepository->update([
                [
                    'id' => $payload['id'],
                    'status' => true,
                ]
            ], $context);
        }

        // Fetch reviews to get their product IDs
        if (!empty($reviewIds)) {
            $criteria = new Criteria($reviewIds);
            $reviews = $this->reviewRepository->search($criteria, $context);

            foreach ($reviews as $review) {
                $productIds[] = $review->getProductId();
            }
        }

        // Trigger product indexer synchronously to update ratingAverage immediately
        if (!empty($productIds)) {
            $message = new ProductIndexingMessage(array_unique($productIds));
            $this->productIndexer->handle($message);
        }
    }
}
