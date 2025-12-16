<?php
/**
 * Check Event Dispatcher Configuration
 */

require_once '/var/www/html/vendor/autoload.php';

use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;

try {
    $kernel = KernelFactory::create('dev', true);
    $kernel->boot();
    $container = $kernel->getContainer();

    $dispatcher = $container->get('event_dispatcher');
    echo "Event Dispatcher Class: " . get_class($dispatcher) . "\n\n";

    // Check if FlowDispatcher is registered
    if ($container->has('Shopware\Core\Content\Flow\Dispatching\FlowDispatcher')) {
        $flowDispatcher = $container->get('Shopware\Core\Content\Flow\Dispatching\FlowDispatcher');
        echo "FlowDispatcher Class: " . get_class($flowDispatcher) . "\n";
    } else {
        echo "FlowDispatcher not found in container\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
