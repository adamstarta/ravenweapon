<?php
/**
 * Update Snigel Products with Color Options
 *
 * Saves colorOptions to product_translation.custom_fields JSON
 *
 * Run: docker exec shopware-chf php /tmp/update-snigel-color-options.php
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

// Load color options JSON
$jsonPath = '/tmp/snigel-color-options.json';
if (!file_exists($jsonPath)) {
    die("Error: $jsonPath not found. Run generate-snigel-color-options.php first.\n");
}

$colorData = json_decode(file_get_contents($jsonPath), true);
echo "Loaded " . count($colorData) . " products from JSON\n\n";

echo "=== Updating Products ===\n\n";

$updated = 0;
$skipped = 0;
$errors = 0;

foreach ($colorData as $product) {
    $productId = $product['product_id'];
    $productNumber = $product['product_number'];
    $colorOptions = $product['colorOptions'];
    $hasVariants = $product['hasColorVariants'];

    if (empty($colorOptions)) {
        $skipped++;
        continue;
    }

    try {
        // Get current custom fields from product_translation
        $stmt = $pdo->prepare("
            SELECT custom_fields
            FROM product_translation
            WHERE product_id = UNHEX(?)
            LIMIT 1
        ");
        $stmt->execute([$productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo "SKIP $productNumber: No translation found\n";
            $skipped++;
            continue;
        }

        $currentFields = $row['custom_fields'] ? json_decode($row['custom_fields'], true) : [];

        // Prepare clean colorOptions (only name, value, imageUrl)
        $cleanOptions = [];
        foreach ($colorOptions as $opt) {
            $cleanOptions[] = [
                'name' => $opt['name'],
                'value' => $opt['value'],
                'imageUrl' => $opt['imageUrl']
            ];
        }

        // Update custom fields
        $currentFields['snigel_color_options'] = $cleanOptions;
        $currentFields['snigel_has_color_variants'] = $hasVariants;

        // Update product_translation
        $stmt = $pdo->prepare("
            UPDATE product_translation
            SET custom_fields = ?, updated_at = NOW()
            WHERE product_id = UNHEX(?)
        ");
        $stmt->execute([json_encode($currentFields, JSON_UNESCAPED_SLASHES), $productId]);

        $colorNames = array_column($colorOptions, 'name');
        echo "$productNumber: " . implode(', ', $colorNames) . "\n";
        $updated++;

    } catch (PDOException $e) {
        echo "ERROR $productNumber: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== COMPLETE ===\n";
echo "Updated: $updated products\n";
echo "Skipped (no colors): $skipped products\n";
echo "Errors: $errors\n";
echo "\nRun: bin/console cache:clear\n";
