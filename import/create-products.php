<?php
/**
 * Raven Products Import Script for Shopware 6
 * Run this script inside the Shopware 6 container:
 * php bin/console app:create-raven-products
 */

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// Products to create
$products = [
    [
        'number' => 'RAVEN-300AAC',
        'name' => 'Lockhart Tactical 300 AAC RAVEN',
        'description' => 'Die 300 AAC RAVEN kombiniert Präzision mit Vielseitigkeit. Ideal für verschiedene Einsatzbereiche und optimiert für maximale Zuverlässigkeit.',
        'price' => 2985,
        'stock' => 100,
        'category' => 'Waffen',
    ],
    [
        'number' => 'RAVEN-762X39',
        'name' => 'Lockhart Tactical 7.62×39 RAVEN',
        'description' => 'Die 7.62×39 RAVEN bietet robuste Leistung und bewährte Zuverlässigkeit. Perfekt für anspruchsvolle Einsätze.',
        'price' => 2985,
        'stock' => 100,
        'category' => 'Waffen',
    ],
    [
        'number' => 'RAVEN-9MM',
        'name' => 'Lockhart Tactical 9mm RAVEN',
        'description' => 'Die 9mm RAVEN ist die perfekte Wahl für präzise Schüsse auf kurze bis mittlere Distanzen. Entwickelt für höchste Zuverlässigkeit.',
        'price' => 2985,
        'stock' => 100,
        'category' => 'Waffen',
    ],
    [
        'number' => 'RAVEN-22LR',
        'name' => 'Lockhart Tactical .22 RAVEN',
        'description' => 'Die .22 RAVEN ist ideal für Präzisionsschießen und Training. Zuverlässige Leistung mit geringem Rückstoß für höchste Genauigkeit.',
        'price' => 2985,
        'stock' => 100,
        'category' => 'Waffen',
    ],
    [
        'number' => 'KIT-9MM',
        'name' => 'Lockhart Tactical 9mm CALIBER KIT',
        'description' => 'Das 9mm Kaliber Kit ermöglicht die einfache Umrüstung Ihrer RAVEN Waffe. Enthält alle notwendigen Komponenten für einen schnellen Kaliberwechsel.',
        'price' => 1685,
        'stock' => 50,
        'category' => 'Zubehör',
    ],
    [
        'number' => 'KIT-300AAC',
        'name' => 'Lockhart Tactical 300 AAC CALIBER KIT',
        'description' => 'Das 300 AAC Kaliber Kit für die flexible Nutzung verschiedener Kaliber. Hochwertige Verarbeitung und einfache Installation.',
        'price' => 1685,
        'stock' => 50,
        'category' => 'Zubehör',
    ],
    [
        'number' => 'KIT-762X39',
        'name' => 'Lockhart Tactical 7.62×39 CALIBER KIT',
        'description' => 'Das 7.62×39 Kaliber Kit bietet maximale Flexibilität für Ihre RAVEN Plattform. Professionelle Qualität für zuverlässige Leistung.',
        'price' => 1685,
        'stock' => 50,
        'category' => 'Zubehör',
    ],
];

echo "Products to import:\n";
foreach ($products as $p) {
    echo "- {$p['name']} ({$p['number']}) - CHF {$p['price']}\n";
}
echo "\nTotal: " . count($products) . " products\n";
