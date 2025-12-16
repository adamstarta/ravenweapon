<?php
/**
 * Import Snigel color variant data from JSON to Shopware custom fields
 *
 * Reads products-with-variants.json and updates Shopware products with:
 * - snigel_color_options: JSON array of color options
 * - snigel_has_colors: Boolean flag
 */

// Database connection
$host = '127.0.0.1';
$dbname = 'shopware';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to database.\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

// Read JSON file
$jsonPath = '/tmp/products-with-variants.json';
if (!file_exists($jsonPath)) {
    die("JSON file not found: $jsonPath\n");
}

$jsonData = json_decode(file_get_contents($jsonPath), true);
if (!$jsonData) {
    die("Failed to parse JSON file.\n");
}

echo "Loaded " . count($jsonData) . " products from JSON.\n\n";

// Stats
$updated = 0;
$notFound = 0;
$skipped = 0;
$errors = 0;

// Process each product
foreach ($jsonData as $product) {
    $articleNo = $product['article_no'] ?? '';
    $productName = $product['name'] ?? 'Unknown';
    $hasColorVariants = $product['hasColorVariants'] ?? false;
    $colorOptions = $product['colorOptions'] ?? [];
    $colours = $product['colours'] ?? [];
    $slug = $product['slug'] ?? '';

    if (empty($slug)) {
        echo "SKIP: No slug for '$productName'\n";
        $skipped++;
        continue;
    }

    // Build Snigel product number (SN-slug format)
    $productNumber = 'SN-' . $slug;

    // Find product in Shopware by product_number
    $stmt = $pdo->prepare("
        SELECT HEX(p.id) as id, p.product_number, pt.custom_fields
        FROM product p
        LEFT JOIN product_translation pt ON p.id = pt.product_id
        WHERE p.product_number = ?
        LIMIT 1
    ");
    $stmt->execute([$productNumber]);
    $shopwareProduct = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shopwareProduct) {
        echo "NOT FOUND: $productNumber ($productName)\n";
        $notFound++;
        continue;
    }

    // Prepare color options for storage
    $colorOptionsForStorage = [];

    if ($hasColorVariants && !empty($colorOptions)) {
        // Use scraped color options
        foreach ($colorOptions as $option) {
            $colorOptionsForStorage[] = [
                'name' => $option['name'],
                'imageFilename' => basename($option['imageUrl'] ?? '')
            ];
        }
    } elseif (!empty($colours)) {
        // Single color - create one option
        $firstColor = is_array($colours) ? $colours[0] : $colours;
        if ($firstColor && $firstColor !== 'Various') {
            $colorOptionsForStorage[] = [
                'name' => $firstColor,
                'imageFilename' => '' // Will use main product image
            ];
        }
    }

    // Parse existing custom fields
    $existingCustomFields = [];
    if ($shopwareProduct['custom_fields']) {
        $existingCustomFields = json_decode($shopwareProduct['custom_fields'], true) ?? [];
    }

    // Merge new fields
    $existingCustomFields['snigel_color_options'] = $colorOptionsForStorage;
    $existingCustomFields['snigel_has_colors'] = !empty($colorOptionsForStorage);

    // Update product_translation
    try {
        $stmt = $pdo->prepare("
            UPDATE product_translation
            SET custom_fields = ?, updated_at = NOW()
            WHERE product_id = UNHEX(?)
        ");
        $stmt->execute([
            json_encode($existingCustomFields),
            $shopwareProduct['id']
        ]);

        $colorCount = count($colorOptionsForStorage);
        $colorNames = array_map(fn($c) => $c['name'], $colorOptionsForStorage);
        echo "UPDATED: {$shopwareProduct['product_number']} - $colorCount colors: " . implode(', ', $colorNames) . "\n";
        $updated++;
    } catch (PDOException $e) {
        echo "ERROR updating {$shopwareProduct['product_number']}: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== Import Complete ===\n";
echo "Updated: $updated\n";
echo "Not Found: $notFound\n";
echo "Skipped: $skipped\n";
echo "Errors: $errors\n";
