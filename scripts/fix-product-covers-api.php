<?php
/**
 * Fix product covers using Shopware's internal API
 * This properly hydrates the associations unlike direct SQL
 */

require_once '/var/www/html/vendor/autoload.php';

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Dotenv\Dotenv;

// Bootstrap Shopware kernel
$dotenv = new Dotenv();
$dotenv->load('/var/www/html/.env');

$kernel = new \Shopware\Core\Kernel('prod', false);
$kernel->boot();

$container = $kernel->getContainer();
$context = Context::createDefaultContext();

$productRepository = $container->get('product.repository');
$productMediaRepository = $container->get('product_media.repository');

echo "=== FIXING PRODUCT COVERS VIA API ===\n\n";

// Products to fix
$productNumbers = ['Basic-Kurs', 'Basic-Kurs-II', 'Basic-Kurs-III', 'Basic-Kurs-IV'];

foreach ($productNumbers as $productNumber) {
    echo "Processing: $productNumber\n";

    // Find product
    $criteria = new Criteria();
    $criteria->addFilter(new EqualsFilter('productNumber', $productNumber));
    $criteria->addAssociation('media');
    $criteria->addAssociation('cover');

    $products = $productRepository->search($criteria, $context);

    if ($products->count() === 0) {
        echo "  Product not found\n\n";
        continue;
    }

    $product = $products->first();
    $productId = $product->getId();
    echo "  Product ID: $productId\n";

    // Get product media
    $mediaCriteria = new Criteria();
    $mediaCriteria->addFilter(new EqualsFilter('productId', $productId));
    $mediaCriteria->addAssociation('media');

    $productMediaCollection = $productMediaRepository->search($mediaCriteria, $context);

    if ($productMediaCollection->count() === 0) {
        echo "  No media found\n\n";
        continue;
    }

    $productMedia = $productMediaCollection->first();
    $productMediaId = $productMedia->getId();
    echo "  Product Media ID: $productMediaId\n";

    // Update product with cover
    $productRepository->update([
        [
            'id' => $productId,
            'coverId' => $productMediaId
        ]
    ], $context);

    echo "  Cover set successfully\n\n";
}

echo "=== DONE ===\n";
echo "\nRun: bin/console cache:clear\n";
