<?php
/**
 * Analyze the mismatched products to identify:
 * 1. Wrong product matches (fuzzy matching errors)
 * 2. Size variants that need size selector implementation
 */

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=shopware', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Load scraped data
$jsonPath = __DIR__ . '/snigel-data/products-with-variants.json';
$scrapedData = json_decode(file_get_contents($jsonPath), true);

echo "=== MISMATCH ANALYSIS ===\n\n";

// Get all Shopware Snigel products
$stmt = $pdo->query("
    SELECT p.product_number, pt.name, pt.custom_fields
    FROM product p
    JOIN product_translation pt ON p.id = pt.product_id
    WHERE p.product_number LIKE 'SN-%'
    ORDER BY pt.name
");
$shopwareProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Size patterns
$sizePatterns = [
    '/^size\s*\d/i',
    '/^(xs|s|m|l|xl|xxl|xxxl)$/i',
    '/^(xsmall|small|medium|large|xlarge|xxl)$/i',
    '/^\d+l$/i',
    '/^\d+\.\d+$/i',
    '/^[a-z]{2}\d+=/',
    '/^(briefs|complete|coverall|vest)$/i',
    '/^\d{3}$/',
    '/^m-xxl$/i',
    '/^xs-m$/i',
];

function isSizeVariant($options) {
    global $sizePatterns;
    foreach ($options as $opt) {
        $name = $opt['name'] ?? '';
        foreach ($sizePatterns as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }
    }
    return false;
}

// Products with SIZE variants (need size selector)
echo "=== PRODUCTS WITH SIZE VARIANTS ===\n";
echo "(These need SIZE selector implementation, not color)\n\n";

$sizeProducts = [];
foreach ($scrapedData as $product) {
    if (!empty($product['colorOptions']) && isSizeVariant($product['colorOptions'])) {
        $sizes = array_map(function($c) { return $c['name']; }, $product['colorOptions']);
        $sizeProducts[] = [
            'name' => $product['name'],
            'sizes' => $sizes,
        ];
        echo "  {$product['name']}\n";
        echo "    Sizes: " . implode(', ', $sizes) . "\n\n";
    }
}
echo "Total: " . count($sizeProducts) . " products with size variants\n\n";

// Find wrong product matches
echo "\n=== WRONG PRODUCT MATCHES ===\n";
echo "(Fuzzy matching hit wrong products)\n\n";

$wrongMatches = [
    // Scraped name => should match to (or "NOT IN SHOPWARE")
    '30L Specialist backpack' => 'SN-25-30l-specialist-backpack-14',
    'Equipment vest -16' => 'SN-tactical-vest-16',
    'Small Notebook cover' => 'SN-small-notebook-cover-07',
    'Radio pouch -15' => 'SN-radio-pouch-06-15',
    'Oyster pouch 1.0' => 'NOT FOUND - different product',
    '3L Multipurpose bag -15' => 'SN-3l-multipurpose-bag-15',
    'Rigid trouser belt -05' => 'SN-rigid-trouser-belt-05',
    '50L Mission backpack 2.0' => 'SN-50l-mission-backpack-2-0',
    'Herma 25 mm repair buckle' => 'SN-herma-25-mm-buckle-pair or SN-malefemale-rep-buckle-stealth',
    'T-bar set -12' => 'SN-t-bar-set-12',
    'Dual Magazine pouch -18' => 'SN-dual-magazine-pouch-15',
    'Speed magazine pouch 2.0' => 'SN-speed-magazine-pouch-2-0',
];

// List all Snigel products in Shopware for reference
echo "--- All Shopware Snigel Products ---\n";
foreach ($shopwareProducts as $p) {
    echo "  [{$p['product_number']}] {$p['name']}\n";
}

echo "\n\n--- Scraped Products with Variants (not size) ---\n";
foreach ($scrapedData as $product) {
    if (!empty($product['colorOptions']) && !isSizeVariant($product['colorOptions'])) {
        $colors = array_map(function($c) { return $c['name']; }, $product['colorOptions']);
        echo "  {$product['name']}: " . implode(', ', $colors) . "\n";
    }
}
