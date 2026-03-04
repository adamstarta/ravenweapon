<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles TWINT payment cancellation:
 * - Auto-cancels the ORDER when a TWINT payment is cancelled
 * - The Zahlungsstatus email is handled by Flow Builder ("Payment enters status cancelled")
 */
class TwintCancellationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $orderTransactionRepository,
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'state_enter.order_transaction.state.cancelled' => 'onTransactionCancelled',
        ];
    }

    public function onTransactionCancelled(OrderStateMachineStateChangeEvent $event): void
    {
        $order = $event->getOrder();
        $context = $event->getContext();

        // Load the order's transactions with payment method
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $order->getId()));
        $criteria->addAssociation('paymentMethod');

        $transactions = $this->orderTransactionRepository->search($criteria, $context);

        $isTwint = false;
        foreach ($transactions as $transaction) {
            $paymentMethod = $transaction->getPaymentMethod();
            if (!$paymentMethod) {
                continue;
            }

            $name = strtolower($paymentMethod->getName() ?? '');
            $handlerId = strtolower($paymentMethod->getHandlerIdentifier() ?? '');
            if (str_contains($name, 'twint') || str_contains($handlerId, 'twint')) {
                $isTwint = true;
                break;
            }
        }

        if (!$isTwint) {
            return;
        }

        $this->logger->info('TWINT payment cancelled, auto-cancelling order', [
            'orderNumber' => $order->getOrderNumber(),
        ]);

        // Auto-cancel the order status (payment is already cancelled by TWINT plugin)
        $this->cancelOrder($order, $context);
    }

    private function cancelOrder(OrderEntity $order, Context $context): void
    {
        try {
            $this->stateMachineRegistry->transition(
                new Transition(
                    'order',
                    $order->getId(),
                    StateMachineTransitionActions::ACTION_CANCEL,
                    'stateId'
                ),
                $context
            );

            $this->logger->info('Order cancelled due to TWINT payment cancellation', [
                'orderNumber' => $order->getOrderNumber(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel order after TWINT payment cancellation', [
                'orderNumber' => $order->getOrderNumber(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
