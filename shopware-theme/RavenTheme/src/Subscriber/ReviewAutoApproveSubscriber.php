<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Auto-approves product reviews based on star rating.
 *
 * - 4-5 stars: Auto-approved immediately
 * - 1-3 stars: Requires manual approval (so you can respond to complaints)
 */
class ReviewAutoApproveSubscriber implements EventSubscriberInterface
{
    private EntityRepository $productReviewRepository;

    public function __construct(EntityRepository $productReviewRepository)
    {
        $this->productReviewRepository = $productReviewRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'product_review.written' => 'onReviewWritten',
        ];
    }

    public function onReviewWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $result) {
            $payload = $result->getPayload();

            // Skip if no payload or no ID
            if (empty($payload) || empty($payload['id'])) {
                continue;
            }

            // Get the star rating (points)
            $points = $payload['points'] ?? 0;

            // Auto-approve if rating is 4 or 5 stars
            if ($points >= 4) {
                $this->approveReview($payload['id'], $event->getContext());
            }
            // 1-3 stars: Leave as pending for manual review
        }
    }

    private function approveReview(string $reviewId, Context $context): void
    {
        try {
            $this->productReviewRepository->update([
                [
                    'id' => $reviewId,
                    'status' => true, // true = approved/active
                ]
            ], $context);
        } catch (\Exception $e) {
            // Silently fail - don't break the review submission
            // The review will just stay in pending status
        }
    }
}
