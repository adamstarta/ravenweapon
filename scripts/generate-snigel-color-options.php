<?php
/**
 * Generate Snigel Color Options from Image Filenames
 *
 * This script:
 * 1. Gets all Snigel products from Shopware
 * 2. Analyzes image filenames to detect color codes
 * 3. Builds colorOptions array with imageUrl for each color
 * 4. Outputs JSON that can be used to update products
 *
 * Run: docker exec shopware-chf php /tmp/generate-snigel-color-options.php
 */

$host = '127.0.0.1';
$port = 3306;
$dbname = 'shopware';
$username = 'root';
$password = 'root';

// Color code mapping (from Snigel naming convention)
$colorCodes = [
    '01' => 'Black',
    '05' => 'Clear',
    '09' => 'Grey',
    '14' => 'Coyote',
    '15' => 'Tan',
    '17' => 'Olive',
    '20' => 'Khaki',
    '27' => 'Ranger Green',
    '28' => 'HighVis',
    '56' => 'Multicam',
];

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to database.\n\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

// Get all Snigel products with their images and URLs
$stmt = $pdo->query("
    SELECT
        HEX(p.id) as product_id,
        p.product_number,
        pt.name as product_name,
        m.file_name,
        CONCAT('/media/', SUBSTR(HEX(m.id), 1, 2), '/', SUBSTR(HEX(m.id), 3, 2), '/', HEX(m.id), '/', m.file_name, '.', m.file_extension) as media_path,
        HEX(m.id) as media_id
    FROM product p
    JOIN product_translation pt ON pt.product_id = p.id
    LEFT JOIN product_media pm ON pm.product_id = p.id
    LEFT JOIN media m ON m.id = pm.media_id
    WHERE p.product_number LIKE 'SN-%'
    AND p.parent_id IS NULL
    ORDER BY p.product_number, pm.position
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by product
$products = [];
foreach ($rows as $row) {
    $productId = $row['product_id'];
    if (!isset($products[$productId])) {
        $products[$productId] = [
            'product_id' => $productId,
            'product_number' => $row['product_number'],
            'product_name' => $row['product_name'],
            'images' => []
        ];
    }
    if ($row['file_name']) {
        $products[$productId]['images'][] = [
            'filename' => $row['file_name'],
            'media_path' => $row['media_path'],
            'media_id' => $row['media_id']
        ];
    }
}

echo "Found " . count($products) . " Snigel products\n\n";

// Process each product to detect colors
$results = [];
$stats = [
    'total' => count($products),
    'with_colors' => 0,
    'single_color' => 0,
    'multi_color' => 0,
    'no_color_detected' => 0,
];

foreach ($products as $product) {
    $detectedColors = [];

    foreach ($product['images'] as $image) {
        $filename = $image['filename'];

        // Try to extract color code from filename
        // Pattern 1: -XX- (e.g., -09-, -01-)
        if (preg_match('/-(\d{2})-/', $filename, $matches)) {
            $code = $matches[1];
            if (isset($colorCodes[$code])) {
                $colorName = $colorCodes[$code];
                if (!isset($detectedColors[$colorName])) {
                    $detectedColors[$colorName] = [
                        'name' => $colorName,
                        'value' => strtolower($colorName),
                        'code' => $code,
                        'imageUrl' => $image['media_path'],
                        'mediaId' => $image['media_id'],
                        'filename' => $filename
                    ];
                }
            }
        }

        // Pattern 2: AXX or BXX (e.g., A09, B56)
        if (preg_match('/[AB](\d{2})/', $filename, $matches)) {
            $code = $matches[1];
            if (isset($colorCodes[$code])) {
                $colorName = $colorCodes[$code];
                if (!isset($detectedColors[$colorName])) {
                    $detectedColors[$colorName] = [
                        'name' => $colorName,
                        'value' => strtolower($colorName),
                        'code' => $code,
                        'imageUrl' => $image['media_path'],
                        'mediaId' => $image['media_id'],
                        'filename' => $filename
                    ];
                }
            }
        }

        // Pattern 3: CXX (e.g., C09)
        if (preg_match('/C(\d{2})/', $filename, $matches)) {
            $code = $matches[1];
            if (isset($colorCodes[$code])) {
                $colorName = $colorCodes[$code];
                if (!isset($detectedColors[$colorName])) {
                    $detectedColors[$colorName] = [
                        'name' => $colorName,
                        'value' => strtolower($colorName),
                        'code' => $code,
                        'imageUrl' => $image['media_path'],
                        'mediaId' => $image['media_id'],
                        'filename' => $filename
                    ];
                }
            }
        }
    }

    // Convert to array and sort by color name
    $colorOptions = array_values($detectedColors);
    usort($colorOptions, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    $colorCount = count($colorOptions);

    if ($colorCount > 0) {
        $stats['with_colors']++;
        if ($colorCount === 1) {
            $stats['single_color']++;
        } else {
            $stats['multi_color']++;
        }
    } else {
        $stats['no_color_detected']++;
    }

    $results[] = [
        'product_id' => $product['product_id'],
        'product_number' => $product['product_number'],
        'product_name' => $product['product_name'],
        'image_count' => count($product['images']),
        'color_count' => $colorCount,
        'colorOptions' => $colorOptions,
        'hasColorVariants' => $colorCount > 1
    ];

    // Print summary
    $colorNames = array_column($colorOptions, 'name');
    $colorStr = $colorCount > 0 ? implode(', ', $colorNames) : 'NONE';
    echo "{$product['product_number']}: {$colorStr}\n";
}

// Save results to JSON
$outputPath = '/tmp/snigel-color-options.json';
file_put_contents($outputPath, json_encode($results, JSON_PRETTY_PRINT));

echo "\n=== STATISTICS ===\n";
echo "Total products: {$stats['total']}\n";
echo "With colors detected: {$stats['with_colors']}\n";
echo "  - Single color: {$stats['single_color']}\n";
echo "  - Multiple colors: {$stats['multi_color']}\n";
echo "No color detected: {$stats['no_color_detected']}\n";
echo "\nOutput saved to: $outputPath\n";

// Show sample of multi-color products
echo "\n=== SAMPLE MULTI-COLOR PRODUCTS ===\n";
$multiColor = array_filter($results, function($r) { return $r['color_count'] > 1; });
$sample = array_slice($multiColor, 0, 5);
foreach ($sample as $p) {
    echo "\n{$p['product_name']} ({$p['product_number']}):\n";
    foreach ($p['colorOptions'] as $color) {
        echo "  - {$color['name']} (code: {$color['code']})\n";
        echo "    Image: {$color['filename']}\n";
    }
}
