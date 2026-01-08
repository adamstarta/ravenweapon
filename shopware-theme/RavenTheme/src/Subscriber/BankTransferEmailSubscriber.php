<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

/**
 * Sends bank transfer payment information email to customers
 * who select prepayment/bank transfer as their payment method
 */
class BankTransferEmailSubscriber implements EventSubscriberInterface
{
    private const FROM_EMAIL = 'info@ravenweapon.ch';
    private const FROM_NAME = 'Raven Weapon AG';

    // Bank details
    private const BANK_ACCOUNT_HOLDER = 'Raven Weapon AG';
    private const BANK_NAME = 'PostFinance';
    private const BANK_IBAN = 'CH6009000000165059892';
    private const BANK_BIC = 'POFICHBEXXX';

    private MailerInterface $mailer;
    private LoggerInterface $logger;

    public function __construct(
        MailerInterface $mailer,
        LoggerInterface $logger
    ) {
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => ['onOrderPlaced', -100], // Lower priority to run after other subscribers
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $order = $event->getOrder();

        if (!$order) {
            return;
        }

        // Check if payment method is bank transfer
        if (!$this->isBankTransferPayment($order)) {
            return;
        }

        // Get customer email
        $customer = $order->getOrderCustomer();
        if (!$customer || !$customer->getEmail()) {
            return;
        }

        try {
            $this->sendBankTransferEmail($order, $customer->getEmail(), $customer->getFirstName());
            $this->logger->info('Bank transfer payment info email sent', [
                'orderNumber' => $order->getOrderNumber(),
                'customerEmail' => $customer->getEmail(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send bank transfer payment info email', [
                'orderNumber' => $order->getOrderNumber(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isBankTransferPayment(OrderEntity $order): bool
    {
        $transactions = $order->getTransactions();
        if (!$transactions || $transactions->count() === 0) {
            return false;
        }

        $paymentMethod = $transactions->first()?->getPaymentMethod();
        if (!$paymentMethod) {
            return false;
        }

        $shortName = strtolower($paymentMethod->getShortName() ?? '');
        $translatedName = strtolower($paymentMethod->getTranslation('name') ?? '');

        // Check for various bank transfer payment method names
        $bankTransferKeywords = ['vorkasse', 'bank', 'prepayment', 'paid_in_advance', 'überweisung'];

        foreach ($bankTransferKeywords as $keyword) {
            if (str_contains($shortName, $keyword) || str_contains($translatedName, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function sendBankTransferEmail(OrderEntity $order, string $customerEmail, ?string $firstName): void
    {
        $orderNumber = $order->getOrderNumber();
        $totalAmount = number_format($order->getAmountTotal(), 2, '.', "'") . ' CHF';
        $greeting = $firstName ? "Guten Tag {$firstName}" : 'Guten Tag';

        $subject = sprintf('Zahlungsinformationen für Ihre Bestellung #%s', $orderNumber);

        $htmlContent = $this->buildHtmlEmail($orderNumber, $totalAmount, $greeting);
        $textContent = $this->buildTextEmail($orderNumber, $totalAmount, $greeting);

        $email = (new Email())
            ->from(self::FROM_NAME . ' <' . self::FROM_EMAIL . '>')
            ->to($customerEmail)
            ->subject($subject)
            ->html($htmlContent)
            ->text($textContent);

        // Use DSN directly from environment to ensure correct SMTP config
        $dsn = $_ENV['MAILER_DSN'] ?? $_SERVER['MAILER_DSN'] ?? null;

        if ($dsn && $dsn !== 'null://null') {
            $transport = Transport::fromDsn($dsn);
            $directMailer = new Mailer($transport);
            $directMailer->send($email);
        } else {
            $this->mailer->send($email);
        }
    }

    private function buildHtmlEmail(string $orderNumber, string $totalAmount, string $greeting): string
    {
        $accountHolder = self::BANK_ACCOUNT_HOLDER;
        $bankName = self::BANK_NAME;
        $iban = self::BANK_IBAN;
        $bic = self::BANK_BIC;

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f3f4f6;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #111827 0%, #1f2937 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
            <h1 style="margin: 0; color: #fde047; font-family: 'Chakra Petch', sans-serif; font-size: 22px; font-weight: 700;">
                ZAHLUNGSINFORMATIONEN
            </h1>
            <p style="margin: 10px 0 0 0; color: #9ca3af; font-size: 14px;">
                Bestellung #{$orderNumber}
            </p>
        </div>

        <!-- Content -->
        <div style="background: #ffffff; padding: 30px; border-radius: 0 0 8px 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
            <!-- Greeting -->
            <p style="font-size: 15px; color: #374151; margin: 0 0 20px 0; line-height: 1.6;">
                {$greeting},<br><br>
                vielen Dank für Ihre Bestellung bei Raven Weapon. Um Ihre Bestellung abzuschliessen, überweisen Sie bitte den folgenden Betrag auf unser Bankkonto:
            </p>

            <!-- Amount -->
            <div style="background: linear-gradient(135deg, #fef3c7 0%, #fffbeb 100%); border: 2px solid #f59e0b; border-radius: 8px; padding: 20px; margin-bottom: 25px; text-align: center;">
                <p style="margin: 0 0 5px 0; font-size: 14px; color: #92400e; font-weight: 500;">Zu zahlender Betrag</p>
                <p style="margin: 0; font-size: 28px; color: #111827; font-weight: 700;">{$totalAmount}</p>
            </div>

            <!-- Bank Details -->
            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; margin-bottom: 25px;">
                <div style="background: #111827; padding: 12px 16px;">
                    <h2 style="margin: 0; color: #fde047; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                        Bankverbindung
                    </h2>
                </div>
                <table style="width: 100%; font-size: 14px; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; color: #6b7280; width: 40%;">Kontoinhaber</td>
                        <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; color: #111827; font-weight: 500;">{$accountHolder}</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; color: #6b7280;">Bank</td>
                        <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; color: #111827; font-weight: 500;">{$bankName}</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; color: #6b7280;">IBAN</td>
                        <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; color: #111827; font-weight: 700; font-family: monospace; font-size: 15px;">{$iban}</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; color: #6b7280;">BIC/SWIFT</td>
                        <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; color: #111827; font-weight: 500; font-family: monospace;">{$bic}</td>
                    </tr>
                    <tr style="background: #fef3c7;">
                        <td style="padding: 12px 16px; color: #92400e; font-weight: 600;">Verwendungszweck</td>
                        <td style="padding: 12px 16px; color: #111827; font-weight: 700; font-size: 16px;">{$orderNumber}</td>
                    </tr>
                </table>
            </div>

            <!-- Important Note -->
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; margin-bottom: 25px;">
                <p style="margin: 0; font-size: 13px; color: #991b1b; line-height: 1.5;">
                    <strong>Wichtig:</strong> Bitte geben Sie unbedingt die Bestellnummer <strong>{$orderNumber}</strong> als Verwendungszweck an, damit wir Ihre Zahlung zuordnen können.
                </p>
            </div>

            <!-- Processing Info -->
            <p style="font-size: 14px; color: #6b7280; margin: 0; line-height: 1.6;">
                Nach Zahlungseingang wird Ihre Bestellung umgehend bearbeitet und versendet. Bei Fragen stehen wir Ihnen gerne zur Verfügung.
            </p>

            <!-- Contact -->
            <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                <p style="font-size: 14px; color: #374151; margin: 0;">
                    Mit freundlichen Grüssen<br>
                    <strong style="color: #111827;">Ihr Raven Weapon Team</strong>
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align: center; padding: 20px; color: #6b7280; font-size: 12px;">
            <p style="margin: 0;">
                Raven Weapon AG | Schweiz
            </p>
            <p style="margin: 10px 0 0 0;">
                <a href="https://ravenweapon.ch" style="color: #f59e0b; text-decoration: none;">ravenweapon.ch</a>
            </p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function buildTextEmail(string $orderNumber, string $totalAmount, string $greeting): string
    {
        $accountHolder = self::BANK_ACCOUNT_HOLDER;
        $bankName = self::BANK_NAME;
        $iban = self::BANK_IBAN;
        $bic = self::BANK_BIC;

        return <<<TEXT
ZAHLUNGSINFORMATIONEN - BESTELLUNG #{$orderNumber}

=====================================

{$greeting},

vielen Dank für Ihre Bestellung bei Raven Weapon. Um Ihre Bestellung abzuschliessen, überweisen Sie bitte den folgenden Betrag auf unser Bankkonto:

ZU ZAHLENDER BETRAG: {$totalAmount}

=====================================
BANKVERBINDUNG
=====================================

Kontoinhaber:    {$accountHolder}
Bank:            {$bankName}
IBAN:            {$iban}
BIC/SWIFT:       {$bic}
Verwendungszweck: {$orderNumber}

=====================================

WICHTIG: Bitte geben Sie unbedingt die Bestellnummer {$orderNumber} als Verwendungszweck an, damit wir Ihre Zahlung zuordnen können.

Nach Zahlungseingang wird Ihre Bestellung umgehend bearbeitet und versendet. Bei Fragen stehen wir Ihnen gerne zur Verfügung.

Mit freundlichen Grüssen
Ihr Raven Weapon Team

---
Raven Weapon AG | Schweiz
https://ravenweapon.ch

TEXT;
    }
}
