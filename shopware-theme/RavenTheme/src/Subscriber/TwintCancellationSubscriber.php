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
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

/**
 * Handles TWINT payment cancellation:
 * - Auto-cancels the ORDER when a TWINT payment is cancelled
 * - Sends a professional German cancellation email to the customer
 */
class TwintCancellationSubscriber implements EventSubscriberInterface
{
    private const FROM_EMAIL = 'info@ravenweapon.ch';
    private const FROM_NAME = 'Raven Weapon AG';

    private EntityRepository $orderTransactionRepository;
    private StateMachineRegistry $stateMachineRegistry;
    private MailerInterface $mailer;
    private LoggerInterface $logger;

    public function __construct(
        EntityRepository $orderTransactionRepository,
        StateMachineRegistry $stateMachineRegistry,
        MailerInterface $mailer,
        LoggerInterface $logger
    ) {
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->mailer = $mailer;
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

        // Load the transaction with full order associations
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('paymentMethod');
        $criteria->addAssociation('order');
        $criteria->addAssociation('order.orderCustomer');
        $criteria->addAssociation('order.addresses');
        $criteria->addAssociation('order.addresses.country');
        $criteria->addAssociation('order.lineItems');
        $criteria->addAssociation('order.deliveries.shippingMethod');
        $criteria->addAssociation('order.transactions.paymentMethod');

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

        $this->logger->info('TWINT payment cancelled, processing order cancellation', [
            'orderNumber' => $order->getOrderNumber(),
            'transactionId' => $transactionId,
        ]);

        // Cancel the order status
        $this->cancelOrder($order, $context);

        // Send cancellation email to customer
        $this->sendCancellationEmail($order);
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

    private function sendCancellationEmail(OrderEntity $order): void
    {
        $customer = $order->getOrderCustomer();
        if (!$customer || !$customer->getEmail()) {
            return;
        }

        try {
            $customerEmail = $customer->getEmail();
            $firstName = $customer->getFirstName();
            $orderNumber = $order->getOrderNumber();
            $totalAmount = 'CHF ' . number_format($order->getAmountTotal(), 2, '.', "'");
            $greeting = $firstName ? "Guten Tag {$firstName}" : 'Guten Tag';

            $subject = sprintf('Bestellung #%s — Zahlung abgebrochen', $orderNumber);

            $htmlContent = $this->buildHtmlEmail($orderNumber, $greeting, $totalAmount);
            $textContent = $this->buildTextEmail($orderNumber, $greeting, $totalAmount);

            $email = (new Email())
                ->from(self::FROM_NAME . ' <' . self::FROM_EMAIL . '>')
                ->replyTo(self::FROM_EMAIL)
                ->to($customerEmail)
                ->subject($subject)
                ->html($htmlContent)
                ->text($textContent);

            $headers = $email->getHeaders();
            $headers->addTextHeader('X-Mailer', 'Raven Weapon Shop');

            $dsn = $_ENV['MAILER_DSN'] ?? $_SERVER['MAILER_DSN'] ?? null;

            if ($dsn && $dsn !== 'null://null') {
                $transport = Transport::fromDsn($dsn);
                $directMailer = new Mailer($transport);
                $directMailer->send($email);
            } else {
                $this->mailer->send($email);
            }

            $this->logger->info('TWINT cancellation email sent', [
                'orderNumber' => $orderNumber,
                'customerEmail' => $customerEmail,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send TWINT cancellation email', [
                'orderNumber' => $order->getOrderNumber(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildHtmlEmail(string $orderNumber, string $greeting, string $totalAmount): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <div style="max-width: 600px; margin: 0 auto; padding: 16px;">
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #111827 0%, #1f2937 100%); padding: 24px 16px; text-align: center; border-radius: 8px 8px 0 0;">
            <h1 style="margin: 0; color: #fde047; font-size: 20px; font-weight: 700;">
                Zahlung abgebrochen
            </h1>
            <p style="margin: 8px 0 0 0; color: #9ca3af; font-size: 14px;">
                Bestellung #{$orderNumber}
            </p>
        </div>

        <!-- Content -->
        <div style="background: #ffffff; padding: 24px 16px; border-radius: 0 0 8px 8px;">
            <!-- Greeting -->
            <p style="font-size: 14px; color: #374151; margin: 0 0 20px 0; line-height: 1.6;">
                {$greeting},<br><br>
                Die Zahlung mit TWINT für Ihre Bestellung <strong>#{$orderNumber}</strong> über <strong>{$totalAmount}</strong> wurde nicht abgeschlossen. Die Bestellung wurde daher storniert.
            </p>

            <!-- Info Box -->
            <div style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                <p style="margin: 0; font-size: 13px; color: #92400e; line-height: 1.5;">
                    Falls Sie den Kauf dennoch abschliessen möchten, besuchen Sie gerne unseren Shop erneut und legen Sie die gewünschten Artikel in den Warenkorb.
                </p>
            </div>

            <!-- CTA Button -->
            <div style="text-align: center; margin-bottom: 20px;">
                <a href="https://ravenweapon.ch" style="display: inline-block; background: linear-gradient(135deg, #FDE047 0%, #F59E0B 100%); color: #111827; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">
                    Zum Shop
                </a>
            </div>

            <!-- Contact Info -->
            <p style="font-size: 13px; color: #6b7280; margin: 0; line-height: 1.5; text-align: center;">
                Bei Fragen stehen wir Ihnen gerne zur Verfügung unter<br>
                <a href="mailto:info@ravenweapon.ch" style="color: #D97706; text-decoration: none;">info@ravenweapon.ch</a>
            </p>
        </div>

        <!-- Footer -->
        <div style="text-align: center; padding: 16px;">
            <!-- Gold line separator -->
            <div style="width: 60px; height: 2px; background: linear-gradient(90deg, #FDE047, #F59E0B); margin: 0 auto 12px auto;"></div>
            <p style="margin: 0; color: #6b7280; font-size: 12px;">Raven Weapon AG | Schweiz</p>
            <p style="margin: 8px 0 0 0;">
                <a href="https://ravenweapon.ch" style="color: #D97706; text-decoration: none; font-size: 12px;">ravenweapon.ch</a>
            </p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function buildTextEmail(string $orderNumber, string $greeting, string $totalAmount): string
    {
        return <<<TEXT
Zahlung abgebrochen — Bestellung #{$orderNumber}

{$greeting},

Die Zahlung mit TWINT für Ihre Bestellung #{$orderNumber} über {$totalAmount} wurde nicht abgeschlossen. Die Bestellung wurde daher storniert.

Falls Sie den Kauf dennoch abschliessen möchten, besuchen Sie gerne unseren Shop erneut unter https://ravenweapon.ch

Bei Fragen stehen wir Ihnen gerne zur Verfügung unter info@ravenweapon.ch

---
Raven Weapon AG | Schweiz
https://ravenweapon.ch
TEXT;
    }
}
