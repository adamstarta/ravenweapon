<?php
/**
 * Analyze Snigel product images in Shopware
 *
 * Run: docker exec shopware-chf php /tmp/analyze-snigel-images.php
 */

$host = '127.0.0.1';
$port = 3306;
$dbname = 'shopware';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to database.\n\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

// Get Snigel manufacturer ID
$stmt = $pdo->query("
    SELECT HEX(pmt.product_manufacturer_id) as id, pmt.name
    FROM product_manufacturer_translation pmt
    WHERE pmt.name = 'Snigel'
    LIMIT 1
");
$snigel = $stmt->fetch(PDO::FETCH_ASSOC);

if ($snigel) {
    echo "Snigel Manufacturer ID: {$snigel['id']}\n\n";
} else {
    echo "Snigel manufacturer not found (will search by product number)\n\n";
}

// Get all Snigel products with their images
$stmt = $pdo->prepare("
    SELECT
        p.product_number,
        pt.name as product_name,
        GROUP_CONCAT(DISTINCT m.file_name ORDER BY pm.position SEPARATOR '|||') as image_filenames,
        COUNT(DISTINCT m.id) as image_count
    FROM product p
    JOIN product_translation pt ON pt.product_id = p.id
    LEFT JOIN product_media pm ON pm.product_id = p.id
    LEFT JOIN media m ON m.id = pm.media_id
    WHERE p.product_number LIKE 'SN-%'
    AND p.parent_id IS NULL
    GROUP BY p.id, p.product_number, pt.name
    ORDER BY p.product_number
    LIMIT 20
");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Sample Snigel Products with Images ===\n\n";

// Color code mapping
$colorCodes = [
    '01' => 'Black',
    '09' => 'Grey',
    '14' => 'Coyote',
    '15' => 'Tan',
    '17' => 'Olive',
    '27' => 'Ranger Green',
    '28' => 'HighVis',
    '56' => 'Multicam',
];

foreach ($products as $product) {
    echo "Product: {$product['product_name']}\n";
    echo "Number: {$product['product_number']}\n";
    echo "Images: {$product['image_count']}\n";

    if ($product['image_filenames']) {
        $filenames = explode('|||', $product['image_filenames']);
        echo "Filenames:\n";

        $detectedColors = [];
        foreach ($filenames as $filename) {
            echo "  - $filename";

            // Try to extract color code from filename
            // Pattern: XX-XXXXX-CC-XXX or similar
            if (preg_match('/-(\d{2})-/', $filename, $matches)) {
                $code = $matches[1];
                if (isset($colorCodes[$code])) {
                    echo " → {$colorCodes[$code]} (code: $code)";
                    $detectedColors[$code] = $colorCodes[$code];
                }
            }
            // Also check for patterns like A01, B09
            if (preg_match('/[AB](\d{2})/', $filename, $matches)) {
                $code = $matches[1];
                if (isset($colorCodes[$code])) {
                    echo " → {$colorCodes[$code]} (code: $code)";
                    $detectedColors[$code] = $colorCodes[$code];
                }
            }
            echo "\n";
        }

        if (!empty($detectedColors)) {
            echo "Detected colors: " . implode(', ', $detectedColors) . "\n";
        }
    }
    echo "\n---\n\n";
}

// Count total Snigel products
$stmt = $pdo->query("
    SELECT COUNT(*) as total
    FROM product
    WHERE product_number LIKE 'SN-%'
    AND parent_id IS NULL
");
$total = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total Snigel products: {$total['total']}\n";
