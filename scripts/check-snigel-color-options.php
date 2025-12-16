<?php
/**
 * Check if all Snigel products with color variants have color options configured in Shopware
 * Compares scraped data against actual Shopware product custom fields
 */

// Database connection - same as other scripts
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=shopware', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Load scraped data
$jsonPath = __DIR__ . '/snigel-data/products-with-variants.json';
if (!file_exists($jsonPath)) {
    echo "ERROR: Cannot find scraped data file at $jsonPath\n";
    exit(1);
}

$scrapedData = json_decode(file_get_contents($jsonPath), true);
if (!$scrapedData) {
    echo "ERROR: Failed to parse JSON data\n";
    exit(1);
}

echo "=== Snigel Color Options Verification ===\n\n";
echo "Loaded " . count($scrapedData) . " products from scraped data\n\n";

// Find products with color variants
$productsWithColors = [];
foreach ($scrapedData as $product) {
    if (isset($product['hasColorVariants']) && $product['hasColorVariants'] === true) {
        $productsWithColors[] = [
            'name' => $product['name'] ?? 'Unknown',
            'slug' => $product['slug'] ?? '',
            'article_no' => $product['article_no'] ?? '',
            'colorOptions' => $product['colorOptions'] ?? [],
            'colours' => $product['colours'] ?? [],
        ];
    }
}

echo "Found " . count($productsWithColors) . " products with color variants in scraped data\n\n";

// Get all Snigel products from Shopware with their custom fields
echo "Checking Shopware database...\n\n";

$stmt = $pdo->query("
    SELECT
        p.id,
        LOWER(HEX(p.id)) as product_id_hex,
        pt.name,
        p.product_number,
        pt.custom_fields
    FROM product p
    JOIN product_translation pt ON p.id = pt.product_id
    WHERE pt.language_id = (SELECT id FROM language WHERE locale_id = (SELECT id FROM locale WHERE code = 'de-DE'))
    AND p.parent_id IS NULL
    AND (pt.name LIKE '%snigel%' OR pt.name LIKE '%Snigel%' OR p.product_number LIKE 'SN-%')
    ORDER BY pt.name
");

$shopwareProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($shopwareProducts) . " Snigel products in Shopware\n\n";

// Analyze each scraped product with color variants
echo "=== Products WITH color variants (from scraped data) ===\n\n";

$missingColorOptions = [];
$correctlyConfigured = [];
$notFoundInShopware = [];

foreach ($productsWithColors as $scrapedProduct) {
    $name = $scrapedProduct['name'];
    $articleNo = $scrapedProduct['article_no'];
    $colorCount = count($scrapedProduct['colorOptions']);
    $colors = array_map(function($c) { return $c['name']; }, $scrapedProduct['colorOptions']);

    // Try to find this product in Shopware
    $found = false;
    $hasColorConfig = false;
    $shopwareColorOptions = [];

    foreach ($shopwareProducts as $swProduct) {
        // Match by name (fuzzy) or product number
        $swName = strtolower($swProduct['name']);
        $scrapedName = strtolower($name);

        // Check if names are similar or product numbers match
        $nameMatch = (
            strpos($swName, substr($scrapedName, 0, 20)) !== false ||
            strpos($scrapedName, substr($swName, 0, 20)) !== false ||
            similar_text($swName, $scrapedName) > strlen($scrapedName) * 0.6
        );

        if ($nameMatch) {
            $found = true;

            // Check custom fields for color options
            if ($swProduct['custom_fields']) {
                $customFields = json_decode($swProduct['custom_fields'], true);
                if ($customFields) {
                    // Look for color-related custom fields
                    foreach ($customFields as $key => $value) {
                        if (stripos($key, 'color') !== false || stripos($key, 'colour') !== false) {
                            $hasColorConfig = true;
                            $shopwareColorOptions[$key] = $value;
                        }
                    }
                }
            }

            if ($hasColorConfig) {
                $correctlyConfigured[] = [
                    'scraped' => $scrapedProduct,
                    'shopware' => $swProduct,
                    'shopwareColors' => $shopwareColorOptions
                ];
            } else {
                $missingColorOptions[] = [
                    'scraped' => $scrapedProduct,
                    'shopware' => $swProduct
                ];
            }
            break;
        }
    }

    if (!$found) {
        $notFoundInShopware[] = $scrapedProduct;
    }
}

// Print results
echo "--- CORRECTLY CONFIGURED (" . count($correctlyConfigured) . " products) ---\n";
foreach ($correctlyConfigured as $item) {
    $name = $item['scraped']['name'];
    $colors = array_map(function($c) { return $c['name']; }, $item['scraped']['colorOptions']);
    echo "  [OK] $name\n";
    echo "       Scraped colors: " . implode(', ', $colors) . "\n";
    echo "       Shopware config: " . json_encode($item['shopwareColors']) . "\n\n";
}

echo "\n--- MISSING COLOR OPTIONS (" . count($missingColorOptions) . " products) ---\n";
foreach ($missingColorOptions as $item) {
    $name = $item['scraped']['name'];
    $colors = array_map(function($c) { return $c['name']; }, $item['scraped']['colorOptions']);
    echo "  [MISSING] $name\n";
    echo "       Should have colors: " . implode(', ', $colors) . "\n";
    echo "       Shopware product: " . $item['shopware']['name'] . "\n";
    echo "       Product number: " . $item['shopware']['product_number'] . "\n\n";
}

echo "\n--- NOT FOUND IN SHOPWARE (" . count($notFoundInShopware) . " products) ---\n";
foreach ($notFoundInShopware as $item) {
    $name = $item['name'];
    $colors = array_map(function($c) { return $c['name']; }, $item['colorOptions']);
    echo "  [NOT FOUND] $name\n";
    echo "       Should have colors: " . implode(', ', $colors) . "\n";
    echo "       Article no: " . $item['article_no'] . "\n\n";
}

echo "\n=== SUMMARY ===\n";
echo "Total products with color variants (scraped): " . count($productsWithColors) . "\n";
echo "Correctly configured in Shopware: " . count($correctlyConfigured) . "\n";
echo "Missing color options in Shopware: " . count($missingColorOptions) . "\n";
echo "Not found in Shopware at all: " . count($notFoundInShopware) . "\n";
