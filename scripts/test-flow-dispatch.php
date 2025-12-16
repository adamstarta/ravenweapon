<?php
/**
 * Test Flow Dispatch Manually
 */

require_once '/var/www/html/vendor/autoload.php';

use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Defaults;

try {
    $kernel = KernelFactory::create('dev', true);
    $kernel->boot();
    $container = $kernel->getContainer();

    // Get the event_dispatcher - this should be the FlowDispatcher
    $dispatcher = $container->get('event_dispatcher');
    echo "Event Dispatcher Class: " . get_class($dispatcher) . "\n\n";

    // Try to get FlowLoader to see if flows are loaded
    $flowLoader = $container->get('Shopware\Core\Content\Flow\Dispatching\FlowLoader');
    echo "FlowLoader Class: " . get_class($flowLoader) . "\n\n";

    // Get database connection
    $connection = $container->get('Doctrine\DBAL\Connection');

    // Load flows for the checkout.order.placed event
    $sql = "SELECT f.name, f.active, f.event_name
            FROM flow f
            WHERE f.event_name = 'checkout.order.placed' AND f.active = 1";
    $flows = $connection->fetchAllAssociative($sql);

    echo "Active flows for checkout.order.placed:\n";
    foreach ($flows as $flow) {
        echo " - {$flow['name']} (active: {$flow['active']})\n";
    }

    // Check if BufferedFlowExecutionTriggersListener is registered
    echo "\n\nChecking if flow listeners are registered...\n";

    // Get all listeners for Response event (kernel.response)
    // This is where BufferedFlowExecutionTriggersListener should trigger

    echo "Checking BufferedFlowExecutionTriggersListener...\n";
    $listener = $container->get('Shopware\Core\Content\Flow\Dispatching\BufferedFlowExecutionTriggersListener');
    echo "BufferedFlowExecutionTriggersListener: " . get_class($listener) . "\n";

    echo "\n✅ Flow system appears to be configured correctly!\n";
    echo "The issue may be that flows are buffered and executed at the end of the request.\n";
    echo "Check if BufferedFlowQueue has pending flows after order placement.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
