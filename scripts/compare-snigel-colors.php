<?php
/**
 * Compare Snigel product color options between scraped data and Shopware
 * Check if all color variants are properly configured
 */

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

echo "=== Snigel Color Options Comparison ===\n\n";
echo "Loaded " . count($scrapedData) . " products from scraped data\n\n";

// Find products with color variants in scraped data
$scrapedWithColors = [];
foreach ($scrapedData as $product) {
    if (isset($product['hasColorVariants']) && $product['hasColorVariants'] === true && !empty($product['colorOptions'])) {
        $scrapedWithColors[$product['name']] = [
            'name' => $product['name'] ?? 'Unknown',
            'slug' => $product['slug'] ?? '',
            'colorOptions' => $product['colorOptions'] ?? [],
        ];
    }
}

echo "Found " . count($scrapedWithColors) . " products with color variants in scraped data\n\n";

// Get Shopware products with custom fields
$stmt = $pdo->query("
    SELECT
        p.product_number,
        pt.name,
        pt.custom_fields
    FROM product p
    JOIN product_translation pt ON p.id = pt.product_id
    WHERE p.product_number LIKE 'SN-%'
    ORDER BY pt.name
");

$shopwareProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($shopwareProducts) . " Snigel products in Shopware\n\n";

// Build shopware product lookup
$shopwareLookup = [];
foreach ($shopwareProducts as $p) {
    $cf = json_decode($p['custom_fields'] ?? '{}', true) ?: [];
    $hasColors = $cf['snigel_has_colors'] ?? false;

    // Color options might be a JSON string or already an array
    $colorOptionsRaw = $cf['snigel_color_options'] ?? '[]';
    if (is_string($colorOptionsRaw)) {
        $colorOptions = json_decode($colorOptionsRaw, true) ?: [];
    } else {
        $colorOptions = is_array($colorOptionsRaw) ? $colorOptionsRaw : [];
    }

    $shopwareLookup[$p['name']] = [
        'product_number' => $p['product_number'],
        'name' => $p['name'],
        'hasColors' => $hasColors,
        'colorOptions' => $colorOptions,
    ];
}

// Compare
$matched = [];
$missingColors = [];
$colorMismatch = [];
$notInShopware = [];

foreach ($scrapedWithColors as $scrapedName => $scraped) {
    $scrapedColors = array_map(function($c) { return $c['name']; }, $scraped['colorOptions']);
    sort($scrapedColors);

    // Try to find matching product in Shopware
    $found = false;
    foreach ($shopwareLookup as $swName => $sw) {
        // Fuzzy match names
        $swNameLower = strtolower($swName);
        $scrapedNameLower = strtolower($scrapedName);

        // Remove version numbers like -17, -14, 1.0, 2.0
        $swNameClean = preg_replace('/\s*[-]?\d+(\.\d+)?$/', '', $swNameLower);
        $scrapedNameClean = preg_replace('/\s*[-]?\d+(\.\d+)?$/', '', $scrapedNameLower);

        $match = (
            $swNameClean === $scrapedNameClean ||
            strpos($swNameLower, $scrapedNameClean) !== false ||
            strpos($scrapedNameLower, $swNameClean) !== false ||
            similar_text($swNameClean, $scrapedNameClean, $percent) && $percent > 70
        );

        if ($match) {
            $found = true;
            $swColors = array_map(function($c) { return $c['name'] ?? ''; }, $sw['colorOptions']);
            sort($swColors);

            if (!$sw['hasColors'] || empty($sw['colorOptions'])) {
                $missingColors[] = [
                    'scraped' => $scraped,
                    'shopware' => $sw,
                    'scrapedColors' => $scrapedColors,
                ];
            } elseif ($scrapedColors != $swColors) {
                $colorMismatch[] = [
                    'scraped' => $scraped,
                    'shopware' => $sw,
                    'scrapedColors' => $scrapedColors,
                    'swColors' => $swColors,
                ];
            } else {
                $matched[] = [
                    'name' => $scrapedName,
                    'colors' => $scrapedColors,
                ];
            }
            break;
        }
    }

    if (!$found) {
        $notInShopware[] = [
            'name' => $scrapedName,
            'colors' => $scrapedColors,
        ];
    }
}

// Print results
echo "=== RESULTS ===\n\n";

echo "--- CORRECTLY CONFIGURED (" . count($matched) . " products) ---\n";
foreach ($matched as $item) {
    echo "  [OK] {$item['name']}: " . implode(', ', $item['colors']) . "\n";
}

echo "\n--- MISSING COLOR CONFIG (" . count($missingColors) . " products) ---\n";
foreach ($missingColors as $item) {
    echo "  [MISSING] {$item['scraped']['name']}\n";
    echo "    Should have: " . implode(', ', $item['scrapedColors']) . "\n";
    echo "    Shopware name: {$item['shopware']['name']}\n";
    echo "    Product #: {$item['shopware']['product_number']}\n\n";
}

echo "\n--- COLOR MISMATCH (" . count($colorMismatch) . " products) ---\n";
foreach ($colorMismatch as $item) {
    echo "  [MISMATCH] {$item['scraped']['name']}\n";
    echo "    Scraped colors: " . implode(', ', $item['scrapedColors']) . "\n";
    echo "    Shopware colors: " . implode(', ', $item['swColors']) . "\n";
    echo "    Product #: {$item['shopware']['product_number']}\n\n";
}

echo "\n--- NOT FOUND IN SHOPWARE (" . count($notInShopware) . " products) ---\n";
foreach ($notInShopware as $item) {
    echo "  [NOT IN SW] {$item['name']}: " . implode(', ', $item['colors']) . "\n";
}

echo "\n=== SUMMARY ===\n";
echo "Scraped products with colors: " . count($scrapedWithColors) . "\n";
echo "Correctly configured: " . count($matched) . "\n";
echo "Missing color config: " . count($missingColors) . "\n";
echo "Color mismatch: " . count($colorMismatch) . "\n";
echo "Not in Shopware: " . count($notInShopware) . "\n";
