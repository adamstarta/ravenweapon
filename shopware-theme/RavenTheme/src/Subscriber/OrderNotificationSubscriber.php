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
    ];
    private const FROM_EMAIL = 'info@ravenweapon.ch';
    private const FROM_NAME = 'Raven Weapon Shop';

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
        $totalAmount = number_format($order->getAmountTotal(), 2, '.', "'") . ' CHF';

        // Get customer info
        $customer = $order->getOrderCustomer();
        $customerName = $customer ? ($customer->getFirstName() . ' ' . $customer->getLastName()) : 'Unbekannt';
        $customerEmail = $customer ? $customer->getEmail() : 'N/A';

        // Get shipping address
        $shippingAddress = null;
        $deliveries = $order->getDeliveries();
        if ($deliveries && $deliveries->count() > 0) {
            $shippingAddress = $deliveries->first()?->getShippingOrderAddress();
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

        // Build order items list
        $itemsList = $this->buildItemsList($order);

        // Build email content
        $subject = sprintf('Neue Bestellung #%s - CHF %s', $orderNumber, number_format($order->getAmountTotal(), 2));

        $htmlContent = $this->buildHtmlEmail($orderNumber, $orderDate, $customerName, $customerEmail, $shippingInfo, $itemsList, $totalAmount);
        $textContent = $this->buildTextEmail($orderNumber, $orderDate, $customerName, $customerEmail, $shippingInfo, $itemsList, $totalAmount);

        $email = (new Email())
            ->from(self::FROM_NAME . ' <' . self::FROM_EMAIL . '>')
            ->to(...self::ADMIN_EMAILS)
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

            // Get product image URL from cover
            $imageUrl = '';
            $cover = $lineItem->getCover();
            if ($cover) {
                $imageUrl = $cover->getUrl();
            }

            $items[] = [
                'name' => $lineItem->getLabel() . $variantInfo,
                'quantity' => $lineItem->getQuantity(),
                'unitPrice' => number_format($lineItem->getUnitPrice(), 2, '.', "'") . ' CHF',
                'totalPrice' => number_format($lineItem->getTotalPrice(), 2, '.', "'") . ' CHF',
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
        array $items,
        string $totalAmount
    ): string {
        $itemsHtml = '';
        foreach ($items as $item) {
            $imageHtml = '';
            if (!empty($item['imageUrl'])) {
                $imageHtml = sprintf(
                    '<img src="%s" alt="%s" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; margin-right: 10px; vertical-align: middle;">',
                    htmlspecialchars($item['imageUrl']),
                    htmlspecialchars($item['name'])
                );
            } else {
                // Placeholder if no image
                $imageHtml = '<div style="width: 50px; height: 50px; background: #f3f4f6; border-radius: 4px; display: inline-block; vertical-align: middle; margin-right: 10px;"></div>';
            }

            $itemsHtml .= sprintf(
                '<tr>
                    <td style="padding: 10px; border-bottom: 1px solid #e5e7eb;">%s<span style="vertical-align: middle;">%s</span></td>
                    <td style="padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: center;">%d</td>
                    <td style="padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: right;">%s</td>
                    <td style="padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: right;">%s</td>
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
<body style="margin: 0; padding: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f3f4f6;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #111827 0%, #1f2937 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
            <h1 style="margin: 0; color: #fde047; font-family: 'Chakra Petch', sans-serif; font-size: 24px; font-weight: 700;">
                NEUE BESTELLUNG
            </h1>
            <p style="margin: 10px 0 0 0; color: #9ca3af; font-size: 14px;">
                Bestellung #{$orderNumber}
            </p>
        </div>

        <!-- Content -->
        <div style="background: #ffffff; padding: 30px; border-radius: 0 0 8px 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
            <!-- Order Info -->
            <div style="margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid #f3f4f6;">
                <h2 style="margin: 0 0 15px 0; color: #111827; font-size: 16px; font-weight: 600;">
                    Bestellinformationen
                </h2>
                <table style="width: 100%; font-size: 14px; color: #374151;">
                    <tr>
                        <td style="padding: 5px 0; color: #6b7280;">Bestellnummer:</td>
                        <td style="padding: 5px 0; font-weight: 600;">#{$orderNumber}</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0; color: #6b7280;">Datum:</td>
                        <td style="padding: 5px 0;">{$orderDate}</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0; color: #6b7280;">Gesamtbetrag:</td>
                        <td style="padding: 5px 0; font-weight: 700; color: #e53935; font-size: 16px;">{$totalAmount}</td>
                    </tr>
                </table>
            </div>

            <!-- Customer Info -->
            <div style="margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid #f3f4f6;">
                <h2 style="margin: 0 0 15px 0; color: #111827; font-size: 16px; font-weight: 600;">
                    Kundeninformationen
                </h2>
                <table style="width: 100%; font-size: 14px; color: #374151;">
                    <tr>
                        <td style="padding: 5px 0; color: #6b7280;">Name:</td>
                        <td style="padding: 5px 0;">{$customerName}</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0; color: #6b7280;">E-Mail:</td>
                        <td style="padding: 5px 0;"><a href="mailto:{$customerEmail}" style="color: #f59e0b; text-decoration: none;">{$customerEmail}</a></td>
                    </tr>
                </table>
            </div>

            <!-- Shipping Address -->
            <div style="margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid #f3f4f6;">
                <h2 style="margin: 0 0 15px 0; color: #111827; font-size: 16px; font-weight: 600;">
                    Lieferadresse
                </h2>
                <p style="margin: 0; font-size: 14px; color: #374151; white-space: pre-line; line-height: 1.6;">{$shippingInfo}</p>
            </div>

            <!-- Order Items -->
            <div style="margin-bottom: 25px;">
                <h2 style="margin: 0 0 15px 0; color: #111827; font-size: 16px; font-weight: 600;">
                    Bestellte Artikel
                </h2>
                <table style="width: 100%; font-size: 14px; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f9fafb;">
                            <th style="padding: 12px 10px; text-align: left; color: #6b7280; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Artikel</th>
                            <th style="padding: 12px 10px; text-align: center; color: #6b7280; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Menge</th>
                            <th style="padding: 12px 10px; text-align: right; color: #6b7280; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Stückpreis</th>
                            <th style="padding: 12px 10px; text-align: right; color: #6b7280; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Gesamt</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsHtml}
                    </tbody>
                    <tfoot>
                        <tr style="background: #111827;">
                            <td colspan="3" style="padding: 15px 10px; color: #ffffff; font-weight: 600; text-align: right;">Gesamtbetrag:</td>
                            <td style="padding: 15px 10px; color: #fde047; font-weight: 700; text-align: right; font-size: 16px;">{$totalAmount}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- CTA Button -->
            <div style="text-align: center; margin-top: 30px;">
                <a href="https://shop.ravenweapon.ch/admin" style="display: inline-block; background: linear-gradient(135deg, #fde047 0%, #f59e0b 50%, #d97706 100%); color: #111827; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">
                    Im Admin-Panel anzeigen
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align: center; padding: 20px; color: #6b7280; font-size: 12px;">
            <p style="margin: 0;">
                Diese E-Mail wurde automatisch von Raven Weapon Shop gesendet.
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

    private function buildTextEmail(
        string $orderNumber,
        string $orderDate,
        string $customerName,
        string $customerEmail,
        string $shippingInfo,
        array $items,
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
NEUE BESTELLUNG - RAVEN WEAPON

=====================================
BESTELLINFORMATIONEN
=====================================
Bestellnummer: #{$orderNumber}
Datum: {$orderDate}
Gesamtbetrag: {$totalAmount}

=====================================
KUNDENINFORMATIONEN
=====================================
Name: {$customerName}
E-Mail: {$customerEmail}

=====================================
LIEFERADRESSE
=====================================
{$shippingInfo}

=====================================
BESTELLTE ARTIKEL
=====================================
{$itemsText}

GESAMTBETRAG: {$totalAmount}

=====================================

Diese E-Mail wurde automatisch von Raven Weapon Shop gesendet.
Admin-Panel: https://shop.ravenweapon.ch/admin

TEXT;
    }
}
