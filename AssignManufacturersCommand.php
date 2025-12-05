<?php declare(strict_types=1);

namespace RavenTheme\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'raven:assign-manufacturers',
    description: 'Assign manufacturers to products based on SKU prefix'
)]
class AssignManufacturersCommand extends Command
{
    // SKU prefix to manufacturer name mapping
    private const PREFIX_MAPPING = [
        'ACH' => 'Acheron',
        'AIM' => 'Aimpact',
        'MGP' => 'Magpul',
        'ZRT' => 'Zero Tolerance',
        'LHT' => 'Lockhart Tactical',
        'DEX' => 'Lockhart Tactical',
        'RAVEN' => 'Lockhart Tactical',
        'KIT' => 'Lockhart Tactical',
        'FCH' => 'FCH',
    ];

    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $manufacturerRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $io->title('Raven Weapon - Assign Manufacturers by SKU Prefix');

        // Step 1: Load all manufacturers
        $io->section('Loading manufacturers...');
        $manufacturerCriteria = new Criteria();
        $manufacturers = $this->manufacturerRepository->search($manufacturerCriteria, $context);

        $manufacturerMap = [];
        foreach ($manufacturers as $manufacturer) {
            $manufacturerMap[$manufacturer->getTranslation('name')] = $manufacturer->getId();
            $io->writeln(sprintf('  Found: %s (%s)', $manufacturer->getTranslation('name'), $manufacturer->getId()));
        }

        if (empty($manufacturerMap)) {
            $io->error('No manufacturers found! Please add manufacturers first.');
            return Command::FAILURE;
        }

        // Step 2: Load all products
        $io->section('Loading products...');
        $productCriteria = new Criteria();
        $productCriteria->addAssociation('manufacturer');
        $products = $this->productRepository->search($productCriteria, $context);

        $io->writeln(sprintf('Found %d products', $products->getTotal()));

        // Step 3: Process each product
        $io->section('Processing products...');
        $updates = [];
        $updated = 0;
        $skipped = 0;
        $noMatch = 0;

        foreach ($products as $product) {
            $productNumber = $product->getProductNumber();
            $currentManufacturer = $product->getManufacturer()?->getTranslation('name');

            // Find matching prefix
            $matchedManufacturer = null;
            foreach (self::PREFIX_MAPPING as $prefix => $manufacturerName) {
                if (str_starts_with($productNumber, $prefix . '-') || str_starts_with($productNumber, $prefix . '_')) {
                    $matchedManufacturer = $manufacturerName;
                    break;
                }
            }

            // Special case for RAVEN and KIT without dash
            if (!$matchedManufacturer) {
                if (str_starts_with($productNumber, 'RAVEN-') || str_starts_with($productNumber, 'KIT-')) {
                    $matchedManufacturer = 'Lockhart Tactical';
                }
            }

            if (!$matchedManufacturer) {
                $io->writeln(sprintf('  [NO MATCH] %s - No prefix match', $productNumber));
                $noMatch++;
                continue;
            }

            if (!isset($manufacturerMap[$matchedManufacturer])) {
                $io->writeln(sprintf('  [MISSING] %s - Manufacturer "%s" not found in system', $productNumber, $matchedManufacturer));
                $noMatch++;
                continue;
            }

            $targetManufacturerId = $manufacturerMap[$matchedManufacturer];

            // Check if already assigned correctly
            if ($product->getManufacturerId() === $targetManufacturerId) {
                $io->writeln(sprintf('  [SKIP] %s - Already assigned to %s', $productNumber, $matchedManufacturer));
                $skipped++;
                continue;
            }

            // Queue update
            $updates[] = [
                'id' => $product->getId(),
                'manufacturerId' => $targetManufacturerId,
            ];

            $io->writeln(sprintf('  [UPDATE] %s: %s -> %s',
                $productNumber,
                $currentManufacturer ?? 'None',
                $matchedManufacturer
            ));
            $updated++;
        }

        // Step 4: Apply updates
        if (!empty($updates)) {
            $io->section('Applying updates...');
            $this->productRepository->update($updates, $context);
            $io->success(sprintf('Updated %d products!', count($updates)));
        }

        // Summary
        $io->section('Summary');
        $io->table(
            ['Status', 'Count'],
            [
                ['Updated', $updated],
                ['Skipped (already correct)', $skipped],
                ['No match / Missing', $noMatch],
                ['Total', $products->getTotal()],
            ]
        );

        $io->success('Manufacturer assignment complete!');

        return Command::SUCCESS;
    }
}
