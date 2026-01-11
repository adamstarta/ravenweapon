<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

/**
 * Sends mobile-friendly order confirmation email to customers.
 *
 * IMPORTANT: Disable the default Shopware "Order confirmation" email template
 * in admin (Settings → Email Templates) to avoid duplicate emails.
 */
class CustomerOrderConfirmationSubscriber implements EventSubscriberInterface
{
    private const FROM_EMAIL = 'info@ravenweapon.ch';
    private const FROM_NAME = 'Raven Weapon AG';

    // Bank details for prepayment orders
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
            CheckoutOrderPlacedEvent::class => ['onOrderPlaced', -200], // Run after other subscribers
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $order = $event->getOrder();

        if (!$order) {
            return;
        }

        $customer = $order->getOrderCustomer();
        if (!$customer || !$customer->getEmail()) {
            return;
        }

        try {
            $this->sendOrderConfirmation($order, $customer->getEmail(), $customer->getFirstName());
            $this->logger->info('Customer order confirmation sent', [
                'orderNumber' => $order->getOrderNumber(),
                'customerEmail' => $customer->getEmail(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send customer order confirmation', [
                'orderNumber' => $order->getOrderNumber(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendOrderConfirmation(OrderEntity $order, string $customerEmail, ?string $firstName): void
    {
        $orderNumber = $order->getOrderNumber();
        $orderDate = $order->getOrderDateTime()->format('d.m.Y');
        $greeting = $firstName ? "Guten Tag {$firstName}" : 'Guten Tag';

        // Check if this is a bank transfer order
        $isBankTransfer = $this->isBankTransferPayment($order);

        // Get billing address
        $billingAddress = $order->getBillingAddress();
        $billingInfo = '';
        if ($billingAddress) {
            $billingInfo = sprintf(
                "%s %s\n%s\n%s %s\n%s",
                $billingAddress->getFirstName(),
                $billingAddress->getLastName(),
                $billingAddress->getStreet(),
                $billingAddress->getZipcode(),
                $billingAddress->getCity(),
                $billingAddress->getCountry()?->getName() ?? 'Schweiz'
            );
        }

        // Get shipping info
        $shippingMethodName = 'Standard';
        $shippingCost = 0.0;
        $deliveries = $order->getDeliveries();
        if ($deliveries && $deliveries->count() > 0) {
            $firstDelivery = $deliveries->first();
            $shippingMethodName = $firstDelivery?->getShippingMethod()?->getName() ?? 'Standard';
            $shippingCost = $firstDelivery?->getShippingCosts()?->getTotalPrice() ?? 0.0;
        }

        // Build order items
        $items = $this->buildItemsList($order);

        // Calculate subtotal
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += $item['totalPriceRaw'];
        }

        // Format amounts
        $subtotalFormatted = 'CHF ' . number_format($subtotal, 2, '.', "'");
        $shippingCostFormatted = 'CHF ' . number_format($shippingCost, 2, '.', "'");
        $totalAmount = 'CHF ' . number_format($order->getAmountTotal(), 2, '.', "'");

        $subject = sprintf('Bestellbestätigung #%s', $orderNumber);

        $htmlContent = $this->buildHtmlEmail(
            $orderNumber,
            $orderDate,
            $greeting,
            $billingInfo,
            $items,
            $subtotalFormatted,
            $shippingMethodName,
            $shippingCostFormatted,
            $totalAmount,
            $isBankTransfer
        );

        $textContent = $this->buildTextEmail(
            $orderNumber,
            $orderDate,
            $greeting,
            $billingInfo,
            $items,
            $subtotalFormatted,
            $shippingMethodName,
            $shippingCostFormatted,
            $totalAmount,
            $isBankTransfer
        );

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

        $bankTransferKeywords = ['vorkasse', 'bank', 'prepayment', 'paid_in_advance', 'überweisung'];

        foreach ($bankTransferKeywords as $keyword) {
            if (str_contains($shortName, $keyword) || str_contains($translatedName, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function buildItemsList(OrderEntity $order): array
    {
        $items = [];
        $lineItems = $order->getLineItems();

        if (!$lineItems) {
            return $items;
        }

        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $payload = $lineItem->getPayload();
            $variantInfo = '';

            if (!empty($payload['variantenDisplay'])) {
                $variantInfo = ' (' . $payload['variantenDisplay'] . ')';
            } elseif (!empty($payload['selectedColor']) || !empty($payload['selectedSize'])) {
                $parts = [];
                if (!empty($payload['selectedColor'])) {
                    $parts[] = $payload['selectedColor'];
                }
                if (!empty($payload['selectedSize'])) {
                    $parts[] = $payload['selectedSize'];
                }
                $variantInfo = ' (' . implode(' / ', $parts) . ')';
            }

            $imageUrl = '';
            if (!empty($payload['variantImageUrl'])) {
                $imageUrl = $payload['variantImageUrl'];
            } elseif ($cover = $lineItem->getCover()) {
                $imageUrl = $cover->getUrl();
            }

            $items[] = [
                'name' => $lineItem->getLabel() . $variantInfo,
                'quantity' => $lineItem->getQuantity(),
                'unitPrice' => 'CHF ' . number_format($lineItem->getUnitPrice(), 2, '.', "'"),
                'totalPrice' => 'CHF ' . number_format($lineItem->getTotalPrice(), 2, '.', "'"),
                'totalPriceRaw' => $lineItem->getTotalPrice(),
                'productNumber' => $payload['productNumber'] ?? '',
                'imageUrl' => $imageUrl,
            ];
        }

        return $items;
    }

    private function buildHtmlEmail(
        string $orderNumber,
        string $orderDate,
        string $greeting,
        string $billingInfo,
        array $items,
        string $subtotal,
        string $shippingMethodName,
        string $shippingCost,
        string $totalAmount,
        bool $isBankTransfer
    ): string {
        // Build product rows - mobile-friendly with fixed table layout
        $itemsHtml = '';
        foreach ($items as $item) {
            $imageHtml = '';
            if (!empty($item['imageUrl'])) {
                $imageHtml = sprintf(
                    '<img src="%s" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; display: block;">',
                    htmlspecialchars($item['imageUrl'])
                );
            }

            $productNumberHtml = '';
            if (!empty($item['productNumber'])) {
                $productNumberHtml = sprintf(
                    '<div style="font-size: 11px; color: #6b7280; margin-top: 2px;">Art.Nr: %s</div>',
                    htmlspecialchars($item['productNumber'])
                );
            }

            $itemsHtml .= sprintf(
                '<tr>
                    <td style="padding: 12px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top;">
                        <table style="border-collapse: collapse;"><tr>
                            <td style="padding: 0; vertical-align: top; width: 58px;">%s</td>
                            <td style="padding: 0 0 0 8px; vertical-align: top;">
                                <div style="font-size: 13px; color: #111827; font-weight: 500; line-height: 1.3;">%s</div>
                                %s
                            </td>
                        </tr></table>
                    </td>
                    <td style="padding: 12px 4px; border-bottom: 1px solid #e5e7eb; text-align: center; vertical-align: top; font-size: 13px; color: #374151;">%d</td>
                    <td style="padding: 12px 8px; border-bottom: 1px solid #e5e7eb; text-align: right; vertical-align: top; font-size: 13px; color: #111827; font-weight: 600; white-space: nowrap;">%s</td>
                </tr>',
                $imageHtml,
                htmlspecialchars($item['name']),
                $productNumberHtml,
                $item['quantity'],
                $item['unitPrice']
            );
        }

        // Bank details section (only for prepayment)
        $bankDetailsHtml = '';
        if ($isBankTransfer) {
            $bankDetailsHtml = $this->buildBankDetailsHtml($orderNumber, $totalAmount);
        }

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
                Bestellbestätigung
            </h1>
            <p style="margin: 8px 0 0 0; color: #9ca3af; font-size: 14px;">
                Bestellung #{$orderNumber}
            </p>
        </div>

        <!-- Content -->
        <div style="background: #ffffff; padding: 24px 16px; border-radius: 0 0 8px 8px;">
            <!-- Greeting -->
            <p style="font-size: 14px; color: #374151; margin: 0 0 20px 0; line-height: 1.5;">
                {$greeting},<br><br>
                vielen Dank für Ihre Bestellung bei Raven Weapon. Wir haben Ihre Bestellung erhalten und werden diese schnellstmöglich bearbeiten.
            </p>

            <!-- Billing Address -->
            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                <h3 style="margin: 0 0 10px 0; color: #D97706; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    Rechnungsadresse
                </h3>
                <p style="margin: 0; font-size: 14px; color: #374151; white-space: pre-line; line-height: 1.5;">{$billingInfo}</p>
            </div>

            {$bankDetailsHtml}

            <!-- Product Table -->
            <div style="margin-bottom: 20px;">
                <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                    <colgroup>
                        <col style="width: 55%;">
                        <col style="width: 15%;">
                        <col style="width: 30%;">
                    </colgroup>
                    <thead>
                        <tr style="background: linear-gradient(135deg, #FDE047 0%, #F59E0B 100%);">
                            <th style="padding: 10px 8px; text-align: left; color: #111827; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Produkt</th>
                            <th style="padding: 10px 4px; text-align: center; color: #111827; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Anz.</th>
                            <th style="padding: 10px 8px; text-align: right; color: #111827; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Preis</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsHtml}
                    </tbody>
                </table>
            </div>

            <!-- Summary -->
            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                <h3 style="margin: 0 0 12px 0; color: #D97706; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    Zusammenfassung
                </h3>
                <table style="width: 100%; font-size: 14px; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 6px 0; color: #6b7280;">Versand ({$shippingMethodName})</td>
                        <td style="padding: 6px 0; text-align: right; color: #374151; white-space: nowrap;">{$shippingCost}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0 0 0; color: #111827; font-weight: 700; font-size: 15px;">Gesamtsumme</td>
                        <td style="padding: 10px 0 0 0; text-align: right; color: #111827; font-weight: 700; font-size: 18px; white-space: nowrap;">{$totalAmount}</td>
                    </tr>
                </table>
            </div>

            <!-- CTA Button -->
            <div style="text-align: center; margin-bottom: 20px;">
                <a href="https://ravenweapon.ch/account/order" style="display: inline-block; background: linear-gradient(135deg, #FDE047 0%, #F59E0B 100%); color: #111827; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">
                    Bestellungen ansehen
                </a>
            </div>

            <!-- Contact Info -->
            <p style="font-size: 13px; color: #6b7280; margin: 0; line-height: 1.5; text-align: center;">
                Bei Fragen stehen wir Ihnen gerne zur Verfügung unter<br>
                <a href="mailto:info@ravenweapon.ch" style="color: #D97706; text-decoration: none;">info@ravenweapon.ch</a>
            </p>
        </div>

        <!-- Footer -->
        <div style="text-align: center; padding: 16px; color: #6b7280; font-size: 12px;">
            <p style="margin: 0;">Raven Weapon AG | Schweiz</p>
            <p style="margin: 8px 0 0 0;">
                <a href="https://ravenweapon.ch" style="color: #D97706; text-decoration: none;">ravenweapon.ch</a>
            </p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function buildBankDetailsHtml(string $orderNumber, string $totalAmount): string
    {
        $accountHolder = self::BANK_ACCOUNT_HOLDER;
        $bankName = self::BANK_NAME;
        $iban = self::BANK_IBAN;
        $bic = self::BANK_BIC;

        return <<<HTML
            <!-- Amount to Pay -->
            <div style="background: linear-gradient(135deg, #fef3c7 0%, #fffbeb 100%); border: 2px solid #f59e0b; border-radius: 8px; padding: 16px; margin-bottom: 20px; text-align: center;">
                <p style="margin: 0 0 4px 0; font-size: 13px; color: #92400e; font-weight: 500;">Zu zahlender Betrag</p>
                <p style="margin: 0; font-size: 26px; color: #111827; font-weight: 700;">{$totalAmount}</p>
            </div>

            <!-- Bank Details - Mobile-optimized with stacked layout -->
            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; margin-bottom: 20px;">
                <div style="background: #111827; padding: 10px 12px;">
                    <h2 style="margin: 0; color: #fde047; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                        Bankverbindung
                    </h2>
                </div>
                <table style="width: 100%; font-size: 13px; border-collapse: collapse; table-layout: fixed;">
                    <colgroup>
                        <col style="width: 35%;">
                        <col style="width: 65%;">
                    </colgroup>
                    <tr>
                        <td style="padding: 10px 12px; border-bottom: 1px solid #e5e7eb; color: #6b7280;">Konto</td>
                        <td style="padding: 10px 12px; border-bottom: 1px solid #e5e7eb; color: #111827; font-weight: 500; word-break: break-word;">{$accountHolder}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 12px; border-bottom: 1px solid #e5e7eb; color: #6b7280;">Bank</td>
                        <td style="padding: 10px 12px; border-bottom: 1px solid #e5e7eb; color: #111827; font-weight: 500;">{$bankName}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 12px; border-bottom: 1px solid #e5e7eb; color: #6b7280;">IBAN</td>
                        <td style="padding: 10px 12px; border-bottom: 1px solid #e5e7eb; color: #111827; font-weight: 700; font-family: monospace; font-size: 12px; word-break: break-all;">{$iban}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 12px; border-bottom: 1px solid #e5e7eb; color: #6b7280;">BIC</td>
                        <td style="padding: 10px 12px; border-bottom: 1px solid #e5e7eb; color: #111827; font-weight: 500; font-family: monospace; font-size: 12px;">{$bic}</td>
                    </tr>
                    <tr style="background: #fef3c7;">
                        <td style="padding: 10px 12px; color: #92400e; font-weight: 600;">Zweck</td>
                        <td style="padding: 10px 12px; color: #111827; font-weight: 700; font-size: 14px;">{$orderNumber}</td>
                    </tr>
                </table>
            </div>

            <!-- Important Note -->
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px; margin-bottom: 20px;">
                <p style="margin: 0; font-size: 12px; color: #991b1b; line-height: 1.4;">
                    <strong>Wichtig:</strong> Bitte geben Sie die Bestellnummer <strong>{$orderNumber}</strong> als Verwendungszweck an, damit wir Ihre Zahlung zuordnen können.
                </p>
            </div>
HTML;
    }

    private function buildTextEmail(
        string $orderNumber,
        string $orderDate,
        string $greeting,
        string $billingInfo,
        array $items,
        string $subtotal,
        string $shippingMethodName,
        string $shippingCost,
        string $totalAmount,
        bool $isBankTransfer
    ): string {
        $itemsText = '';
        foreach ($items as $item) {
            $itemsText .= sprintf(
                "- %s\n  Menge: %d | Preis: %s\n",
                $item['name'],
                $item['quantity'],
                $item['unitPrice']
            );
            if (!empty($item['productNumber'])) {
                $itemsText .= sprintf("  Art.Nr: %s\n", $item['productNumber']);
            }
            $itemsText .= "\n";
        }

        $bankDetailsText = '';
        if ($isBankTransfer) {
            $bankDetailsText = <<<TEXT

-------------------------------------
Zu zahlender Betrag: {$totalAmount}
-------------------------------------

BANKVERBINDUNG
-------------------------------------
Konto:    Raven Weapon AG
Bank:     PostFinance
IBAN:     CH6009000000165059892
BIC:      POFICHBEXXX
Zweck:    {$orderNumber}

Wichtig: Bitte geben Sie die Bestellnummer {$orderNumber} als Verwendungszweck an.

TEXT;
        }

        return <<<TEXT
Bestellbestätigung - #{$orderNumber}

{$greeting},

vielen Dank für Ihre Bestellung bei Raven Weapon. Wir haben Ihre Bestellung erhalten und werden diese schnellstmöglich bearbeiten.

-------------------------------------
RECHNUNGSADRESSE
-------------------------------------
{$billingInfo}
{$bankDetailsText}
-------------------------------------
BESTELLTE PRODUKTE
-------------------------------------
{$itemsText}
-------------------------------------
Versand ({$shippingMethodName}): {$shippingCost}
Gesamtsumme: {$totalAmount}
-------------------------------------

Bestellungen ansehen: https://ravenweapon.ch/account/order

Bei Fragen stehen wir Ihnen gerne zur Verfügung unter info@ravenweapon.ch

---
Raven Weapon AG | Schweiz
https://ravenweapon.ch

TEXT;
    }
}
