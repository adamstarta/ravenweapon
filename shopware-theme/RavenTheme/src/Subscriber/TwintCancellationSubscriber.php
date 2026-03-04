<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
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
    private EntityRepository $orderTransactionRepository;
    private StateMachineRegistry $stateMachineRegistry;
    private LoggerInterface $logger;

    public function __construct(
        EntityRepository $orderTransactionRepository,
        StateMachineRegistry $stateMachineRegistry,
        LoggerInterface $logger
    ) {
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'state_enter.order_transaction.state.cancelled' => 'onTransactionCancelled',
        ];
    }

    public function onTransactionCancelled(StateMachineStateChangeEvent $event): void
    {
        $transactionId = $event->getTransition()->getEntityId();
        $context = $event->getContext();

        // Load the transaction with order and payment method
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('paymentMethod');
        $criteria->addAssociation('order');
        $criteria->addAssociation('order.stateMachineState');

        $transaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if (!$transaction instanceof OrderTransactionEntity) {
            return;
        }

        // Check if payment method is TWINT
        $paymentMethod = $transaction->getPaymentMethod();
        if (!$paymentMethod) {
            return;
        }

        $paymentName = strtolower($paymentMethod->getName() ?? '');
        $paymentShortName = strtolower($paymentMethod->getShortName() ?? '');
        $isTwint = str_contains($paymentName, 'twint') || str_contains($paymentShortName, 'twint');

        if (!$isTwint) {
            return;
        }

        $order = $transaction->getOrder();
        if (!$order) {
            return;
        }

        $this->logger->info('TWINT payment cancelled, auto-cancelling order', [
            'orderNumber' => $order->getOrderNumber(),
            'transactionId' => $transactionId,
        ]);

        // Auto-cancel the order status (payment is already cancelled by TWINT plugin)
        $this->cancelOrder($order, $context);
    }

    private function cancelOrder(OrderEntity $order, Context $context): void
    {
        try {
            $currentState = $order->getStateMachineState()?->getTechnicalName();
            if ($currentState === OrderStates::STATE_CANCELLED) {
                return;
            }

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
