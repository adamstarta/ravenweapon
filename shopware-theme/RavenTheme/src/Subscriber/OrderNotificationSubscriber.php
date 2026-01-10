<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

/**
 * Sends email notification to admin when a new order is placed
 */
class OrderNotificationSubscriber implements EventSubscriberInterface
{
    private const ADMIN_EMAILS = [
        'mirco@ravenweapon.ch',
        'business.mitrovic@gmail.com',
        'alamajacintg04@gmail.com',
    ];
    private const FROM_EMAIL = 'info@ravenweapon.ch';
    private const FROM_NAME = 'Raven Weapon AG';

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
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $order = $event->getOrder();

        if (!$order) {
            return;
        }

        try {
            $this->sendAdminNotification($order);
            $this->logger->info('Order notification sent', [
                'orderNumber' => $order->getOrderNumber(),
                'recipients' => self::ADMIN_EMAILS,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send order notification', [
                'orderNumber' => $order->getOrderNumber(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendAdminNotification(OrderEntity $order): void
    {
        $orderNumber = $order->getOrderNumber();
        $orderDate = $order->getOrderDateTime()->format('d.m.Y H:i');

        // Get customer info
        $customer = $order->getOrderCustomer();
        $customerName = $customer ? ($customer->getFirstName() . ' ' . $customer->getLastName()) : 'Unbekannt';
        $customerEmail = $customer ? $customer->getEmail() : 'N/A';

        // Get shipping address and delivery info
        $shippingAddress = null;
        $shippingMethodName = 'N/A';
        $shippingCost = 0.0;
        $deliveries = $order->getDeliveries();
        if ($deliveries && $deliveries->count() > 0) {
            $firstDelivery = $deliveries->first();
            $shippingAddress = $firstDelivery?->getShippingOrderAddress();
            $shippingMethodName = $firstDelivery?->getShippingMethod()?->getName() ?? 'Standard';
            $shippingCost = $firstDelivery?->getShippingCosts()?->getTotalPrice() ?? 0.0;
        }

        $shippingInfo = 'N/A';
        if ($shippingAddress) {
            $shippingInfo = sprintf(
                "%s %s\n%s\n%s %s\n%s",
                $shippingAddress->getFirstName(),
                $shippingAddress->getLastName(),
                $shippingAddress->getStreet(),
                $shippingAddress->getZipcode(),
                $shippingAddress->getCity(),
                $shippingAddress->getCountry()?->getName() ?? 'Schweiz'
            );
        }

        // Build order items list and calculate subtotal
        $itemsList = $this->buildItemsList($order);
        $subtotal = $order->getAmountNet() + ($order->getAmountTotal() - $order->getAmountNet()) - $shippingCost;
        // Alternative: calculate subtotal from line items
        $subtotal = 0.0;
        foreach ($itemsList as $item) {
            $subtotal += $item['totalPriceRaw'];
        }

        // Format amounts with CHF first
        $subtotalFormatted = 'CHF ' . number_format($subtotal, 2, '.', "'");
        $shippingCostFormatted = 'CHF ' . number_format($shippingCost, 2, '.', "'");
        $totalAmount = 'CHF ' . number_format($order->getAmountTotal(), 2, '.', "'");

        // Build email content
        $subject = sprintf('Neue Bestellung #%s - CHF %s', $orderNumber, number_format($order->getAmountTotal(), 2));

        $htmlContent = $this->buildHtmlEmail($orderNumber, $orderDate, $customerName, $customerEmail, $shippingInfo, $shippingMethodName, $itemsList, $subtotalFormatted, $shippingCostFormatted, $totalAmount);
        $textContent = $this->buildTextEmail($orderNumber, $orderDate, $customerName, $customerEmail, $shippingInfo, $shippingMethodName, $itemsList, $subtotalFormatted, $shippingCostFormatted, $totalAmount);

        $email = (new Email())
            ->from(self::FROM_NAME . ' <' . self::FROM_EMAIL . '>')
            ->replyTo(self::FROM_EMAIL)
            ->to(...self::ADMIN_EMAILS)
            ->subject($subject)
            ->html($htmlContent)
            ->text($textContent);

        // Add headers to improve email deliverability
        $headers = $email->getHeaders();
        $headers->addTextHeader('X-Mailer', 'Raven Weapon Shop');

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

            // Check for variant display info
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

            // Get product image URL - prefer variant image from payload, fallback to cover
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
                'productNumber' => $payload['productNumber'] ?? 'N/A',
                'imageUrl' => $imageUrl,
            ];
        }

        return $items;
    }

    private function buildHtmlEmail(
        string $orderNumber,
        string $orderDate,
        string $customerName,
        string $customerEmail,
        string $shippingInfo,
        string $shippingMethodName,
        array $items,
        string $subtotal,
        string $shippingCost,
        string $totalAmount
    ): string {
        // Build table rows for each item (original table design, mobile-safe)
        $itemsHtml = '';
        foreach ($items as $item) {
            $imageHtml = '';
            if (!empty($item['imageUrl'])) {
                $imageHtml = sprintf(
                    '<img src="%s" alt="%s" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; vertical-align: middle;">',
                    htmlspecialchars($item['imageUrl']),
                    htmlspecialchars($item['name'])
                );
            } else {
                $imageHtml = '<div style="width: 40px; height: 40px; background: #f3f4f6; border-radius: 4px; display: inline-block; vertical-align: middle;"></div>';
            }

            $itemsHtml .= sprintf(
                '<tr>
                    <td style="padding: 10px 5px; border-bottom: 1px solid #e5e7eb; vertical-align: top;">
                        <div style="display: inline-block; vertical-align: top; margin-right: 8px;">%s</div>
                        <span style="font-size: 12px; color: #374151; word-wrap: break-word;">%s</span>
                    </td>
                    <td style="padding: 10px 5px; border-bottom: 1px solid #e5e7eb; text-align: center; vertical-align: top; font-size: 13px; color: #374151;">%d</td>
                    <td style="padding: 10px 5px; border-bottom: 1px solid #e5e7eb; text-align: right; vertical-align: top; font-size: 13px; color: #374151;">%s</td>
                    <td style="padding: 10px 5px; border-bottom: 1px solid #e5e7eb; text-align: right; vertical-align: top; font-size: 13px; font-weight: 600; color: #374151;">%s</td>
                </tr>',
                $imageHtml,
                htmlspecialchars($item['name']),
                $item['quantity'],
                $item['unitPrice'],
                $item['totalPrice']
            );
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <div style="max-width: 650px; margin: 0 auto; padding: 15px;">
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #111827 0%, #1f2937 100%); padding: 25px 20px; text-align: center; border-radius: 8px 8px 0 0;">
            <h1 style="margin: 0; color: #fde047; font-size: 22px; font-weight: 700;">
                Neue Bestellung
            </h1>
            <p style="margin: 8px 0 0 0; color: #9ca3af; font-size: 14px;">
                #{$orderNumber}
            </p>
        </div>

        <!-- Content -->
        <div style="background: #ffffff; padding: 25px 20px; border-radius: 0 0 8px 8px;">
            <!-- Order Info -->
            <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e5e7eb;">
                <table style="width: 100%; font-size: 14px; color: #374151;">
                    <tr>
                        <td style="padding: 4px 0; color: #6b7280;">Datum:</td>
                        <td style="padding: 4px 0;">{$orderDate}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; color: #6b7280;">Gesamtbetrag:</td>
                        <td style="padding: 4px 0; font-weight: 700; color: #e53935; font-size: 16px;">{$totalAmount}</td>
                    </tr>
                </table>
            </div>

            <!-- Customer Info -->
            <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e5e7eb;">
                <h2 style="margin: 0 0 10px 0; color: #111827; font-size: 14px; font-weight: 600;">
                    Kundeninformationen
                </h2>
                <table style="width: 100%; font-size: 14px; color: #374151;">
                    <tr>
                        <td style="padding: 4px 0; color: #6b7280;">Name:</td>
                        <td style="padding: 4px 0;">{$customerName}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; color: #6b7280;">E-Mail:</td>
                        <td style="padding: 4px 0;"><a href="mailto:{$customerEmail}" style="color: #f59e0b; text-decoration: none;">{$customerEmail}</a></td>
                    </tr>
                </table>
            </div>

            <!-- Shipping Address -->
            <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e5e7eb;">
                <h2 style="margin: 0 0 10px 0; color: #111827; font-size: 14px; font-weight: 600;">
                    Lieferadresse
                </h2>
                <div style="font-size: 14px; color: #374151; white-space: pre-line; line-height: 1.5;">{$shippingInfo}</div>
                <div style="margin-top: 10px; font-size: 14px;">
                    <span style="color: #6b7280;">Versandart:</span> <span style="color: #374151;">{$shippingMethodName}</span>
                </div>
            </div>

            <!-- Order Items - Original Table Design -->
            <div style="margin-bottom: 20px;">
                <h2 style="margin: 0 0 15px 0; color: #111827; font-size: 14px; font-weight: 600;">
                    Bestellte Artikel
                </h2>
                <table style="width: 100%; font-size: 13px; border-collapse: collapse; table-layout: fixed;">
                    <colgroup>
                        <col style="width: 40%;">
                        <col style="width: 15%;">
                        <col style="width: 22%;">
                        <col style="width: 23%;">
                    </colgroup>
                    <thead>
                        <tr style="background: #f9fafb;">
                            <th style="padding: 8px 5px; text-align: left; color: #6b7280; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Artikel</th>
                            <th style="padding: 8px 5px; text-align: center; color: #6b7280; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Anz.</th>
                            <th style="padding: 8px 5px; text-align: right; color: #6b7280; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Preis</th>
                            <th style="padding: 8px 5px; text-align: right; color: #6b7280; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Gesamt</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsHtml}
                    </tbody>
                </table>
            </div>

            <!-- Summary -->
            <table style="width: 100%; font-size: 14px; border-collapse: collapse; margin-bottom: 20px;">
                <tr>
                    <td style="padding: 8px 0; color: #6b7280; text-align: right;">Zwischensumme:</td>
                    <td style="padding: 8px 0; text-align: right; color: #374151; font-weight: 500; width: 100px;">{$subtotal}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #6b7280; text-align: right;">Versandkosten:</td>
                    <td style="padding: 8px 0; text-align: right; color: #374151; font-weight: 500;">{$shippingCost}</td>
                </tr>
                <tr style="background: #f3f4f6;">
                    <td style="padding: 12px 8px; color: #111827; font-weight: 600; text-align: right;">Gesamtbetrag:</td>
                    <td style="padding: 12px 8px; text-align: right; color: #e53935; font-weight: 700; font-size: 16px;">{$totalAmount}</td>
                </tr>
            </table>

            <!-- CTA Button -->
            <div style="text-align: center;">
                <a href="https://shop.ravenweapon.ch/admin" style="display: inline-block; background: linear-gradient(135deg, #fde047 0%, #f59e0b 50%, #d97706 100%); color: #111827; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">
                    Im Admin-Panel anzeigen
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align: center; padding: 15px; color: #6b7280; font-size: 12px;">
            <p style="margin: 0;">
                Diese E-Mail wurde automatisch von Raven Weapon Shop gesendet.
            </p>
            <p style="margin: 8px 0 0 0;">
                <a href="https://ravenweapon.ch" style="color: #f59e0b; text-decoration: none;">ravenweapon.ch</a>
            </p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function buildTextEmail(
        string $orderNumber,
        string $orderDate,
        string $customerName,
        string $customerEmail,
        string $shippingInfo,
        string $shippingMethodName,
        array $items,
        string $subtotal,
        string $shippingCost,
        string $totalAmount
    ): string {
        $itemsText = '';
        foreach ($items as $item) {
            $itemsText .= sprintf(
                "- %s\n  Menge: %d | Stückpreis: %s | Gesamt: %s\n\n",
                $item['name'],
                $item['quantity'],
                $item['unitPrice'],
                $item['totalPrice']
            );
        }

        return <<<TEXT
Neue Bestellung - Raven Weapon AG

-------------------------------------
Bestellinformationen
-------------------------------------
Bestellnummer: #{$orderNumber}
Datum: {$orderDate}
Gesamtbetrag: {$totalAmount}

-------------------------------------
Kundeninformationen
-------------------------------------
Name: {$customerName}
E-Mail: {$customerEmail}

-------------------------------------
Lieferadresse
-------------------------------------
{$shippingInfo}

Versandart: {$shippingMethodName}

-------------------------------------
Bestellte Artikel
-------------------------------------
{$itemsText}

Zwischensumme: {$subtotal}
Versandkosten: {$shippingCost}
-------------------------------------
Gesamtbetrag: {$totalAmount}

-------------------------------------

Diese E-Mail wurde automatisch von Raven Weapon AG gesendet.
Admin-Panel: https://shop.ravenweapon.ch/admin

TEXT;
    }
}
