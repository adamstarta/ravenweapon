<?php
/**
 * Sync Snigel Weight & Dimensions to Shopware
 *
 * Reads scraped data from snigel-article-data/snigel-articles.json
 * Updates Shopware products with weight, width, height, length
 */

require_once __DIR__ . '/shopware-api-client.php';

// Database connection
$pdo = new PDO(
    'mysql:host=localhost;dbname=shopware;charset=utf8mb4',
    'root',
    'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Load scraped data
$jsonFile = __DIR__ . '/snigel-article-data/snigel-articles.json';
if (!file_exists($jsonFile)) {
    die("ERROR: snigel-articles.json not found!\n");
}

$articles = json_decode(file_get_contents($jsonFile), true);
echo "Loaded " . count($articles) . " articles from JSON\n\n";

// Stats
$updated = 0;
$notFound = 0;
$skipped = 0;
$errors = 0;

// Prepare update statement
$updateStmt = $pdo->prepare("
    UPDATE product
    SET weight = :weight,
        width = :width,
        height = :height,
        length = :length,
        updated_at = NOW()
    WHERE product_number = :product_number
");

// Find product statement
$findStmt = $pdo->prepare("
    SELECT LOWER(HEX(id)) as id, product_number, weight, width, height, length
    FROM product
    WHERE product_number = :product_number
");

echo "=== SYNCING WEIGHT & DIMENSIONS ===\n\n";

foreach ($articles as $article) {
    $articleNo = trim($article['article_no'] ?? '');

    if (empty($articleNo)) {
        $skipped++;
        continue;
    }

    // Check if product exists
    $findStmt->execute(['product_number' => $articleNo]);
    $product = $findStmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo "NOT FOUND: $articleNo\n";
        $notFound++;
        continue;
    }

    // Get weight (already in grams)
    $weight = $article['weight_g'] ?? null;

    // Get dimensions (already in mm)
    $dimensions = $article['dimensions_mm'] ?? null;
    $width = $dimensions['width'] ?? null;
    $height = $dimensions['height'] ?? null;
    $length = $dimensions['length'] ?? null;

    // Skip if no data to update
    if (!$weight && !$width && !$height && !$length) {
        $skipped++;
        continue;
    }

    // Check if update needed
    $needsUpdate = false;
    if ($weight && $product['weight'] != $weight) $needsUpdate = true;
    if ($width && $product['width'] != $width) $needsUpdate = true;
    if ($height && $product['height'] != $height) $needsUpdate = true;
    if ($length && $product['length'] != $length) $needsUpdate = true;

    if (!$needsUpdate) {
        $skipped++;
        continue;
    }

    try {
        $updateStmt->execute([
            'weight' => $weight,
            'width' => $width,
            'height' => $height,
            'length' => $length,
            'product_number' => $articleNo
        ]);

        echo "UPDATED: $articleNo - Weight: {$weight}g, Dimensions: {$length}x{$width}x{$height}mm\n";
        $updated++;
    } catch (Exception $e) {
        echo "ERROR: $articleNo - " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== SYNC COMPLETE ===\n";
echo "Updated: $updated\n";
echo "Not found: $notFound\n";
echo "Skipped (no changes): $skipped\n";
echo "Errors: $errors\n";

// Show summary of updated products
echo "\n=== VERIFICATION ===\n";
$verifyStmt = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN weight IS NOT NULL THEN 1 ELSE 0 END) as has_weight,
        SUM(CASE WHEN width IS NOT NULL THEN 1 ELSE 0 END) as has_width,
        SUM(CASE WHEN height IS NOT NULL THEN 1 ELSE 0 END) as has_height,
        SUM(CASE WHEN length IS NOT NULL THEN 1 ELSE 0 END) as has_length
    FROM product
    WHERE parent_id IS NULL
    AND product_number LIKE '__-_____-__-___'
");
$stats = $verifyStmt->fetch(PDO::FETCH_ASSOC);
echo "Snigel products with weight: {$stats['has_weight']} / {$stats['total']}\n";
echo "Snigel products with dimensions: {$stats['has_width']} / {$stats['total']}\n";
