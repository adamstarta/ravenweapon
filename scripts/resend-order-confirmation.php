<?php
/**
 * Resend Order Confirmation Email for a specific order
 */

require_once '/var/www/html/vendor/autoload.php';

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Dotenv\Dotenv;

// Bootstrap Shopware
$dotenv = new Dotenv();
$dotenv->loadEnv('/var/www/html/.env');

$kernel = new \Shopware\Core\Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
$context = Context::createDefaultContext();

// Get order ID from command line or use the latest failed one
$orderNumber = $argv[1] ?? '10060';

echo "Resending order confirmation for order: $orderNumber\n";

// Get the order
$orderRepository = $container->get('order.repository');
$criteria = new Criteria();
$criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
$criteria->addAssociation('orderCustomer');
$criteria->addAssociation('currency');
$criteria->addAssociation('lineItems');
$criteria->addAssociation('deliveries.shippingOrderAddress.country');
$criteria->addAssociation('transactions.paymentMethod');
$criteria->addAssociation('addresses.country');

$order = $orderRepository->search($criteria, $context)->first();

if (!$order) {
    echo "Order not found!\n";
    exit(1);
}

echo "Found order: {$order->getOrderNumber()} for {$order->getOrderCustomer()->getEmail()}\n";

// Get mail service
$mailService = $container->get('Shopware\Core\Content\Mail\Service\MailService');
$mailTemplateRepository = $container->get('mail_template.repository');

// Get order confirmation template
$templateCriteria = new Criteria();
$templateCriteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', 'order_confirmation_mail'));
$templateCriteria->addAssociation('mailTemplateType');

$mailTemplate = $mailTemplateRepository->search($templateCriteria, $context)->first();

if (!$mailTemplate) {
    echo "Mail template not found!\n";
    exit(1);
}

echo "Using template: {$mailTemplate->getSubject()}\n";

// Send the email using document generation approach
try {
    $salesChannelRepository = $container->get('sales_channel.repository');
    $salesChannel = $salesChannelRepository->search(new Criteria([$order->getSalesChannelId()]), $context)->first();

    // Get mail factory
    $mailFactory = $container->get('Shopware\Core\Content\Mail\Service\MailFactory');
    $templateRenderer = $container->get('Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer');

    $data = [
        'order' => $order,
        'salesChannel' => $salesChannel,
    ];

    $recipients = [
        $order->getOrderCustomer()->getEmail() => $order->getOrderCustomer()->getFirstName() . ' ' . $order->getOrderCustomer()->getLastName()
    ];

    // Render subject
    $subject = $templateRenderer->render($mailTemplate->getSubject(), $data, $context);

    // Render content
    $contentHtml = $templateRenderer->render($mailTemplate->getContentHtml(), $data, $context);
    $contentPlain = $templateRenderer->render($mailTemplate->getContentPlain(), $data, $context);

    $mailData = [
        'recipients' => $recipients,
        'senderName' => 'Raven Weapon AG',
        'salesChannelId' => $order->getSalesChannelId(),
        'subject' => $subject,
        'contentHtml' => $contentHtml,
        'contentPlain' => $contentPlain,
    ];

    $mailService->send($mailData, $context, $data);

    echo "\n✅ Order confirmation email sent successfully to: {$order->getOrderCustomer()->getEmail()}\n";

} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
