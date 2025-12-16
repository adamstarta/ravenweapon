<?php
/**
 * Fix SEO URLs for Snigel products in "Taschen & Rucksäcke" category
 * Updates canonical SEO URLs to use correct category path
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

// Find the "Taschen & Rucksäcke" category ID
$stmt = $pdo->query("
    SELECT HEX(c.id) as id, ct.name
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE ct.name IN ('Taschen & Rucksäcke', 'Taschen-Rucksaecke', 'Bags & Backpacks')
    LIMIT 1
");
$taschenCategory = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$taschenCategory) {
    die("Could not find 'Taschen & Rucksäcke' category\n");
}

echo "Found 'Taschen & Rucksäcke' category: {$taschenCategory['id']}\n\n";

// Find all Snigel products that are in 'Taschen & Rucksäcke' but have wrong SEO URLs
$stmt = $pdo->query("
    SELECT
        HEX(p.id) as product_id,
        p.product_number,
        HEX(su.id) as seo_url_id,
        su.seo_path_info,
        HEX(su.language_id) as language_id,
        HEX(su.sales_channel_id) as sales_channel_id
    FROM product p
    JOIN seo_url su ON su.foreign_key = p.id AND su.is_canonical = 1 AND su.route_name = 'frontend.detail.page'
    WHERE p.product_number LIKE 'SN-%'
    AND su.seo_path_info NOT LIKE '%Taschen-Rucksaecke%'
    AND EXISTS (
        SELECT 1 FROM product_category pc
        JOIN category c ON pc.category_id = c.id
        JOIN category_translation ct ON c.id = ct.category_id
        WHERE pc.product_id = p.id
        AND ct.name IN ('Taschen & Rucksäcke', 'Bags & Backpacks')
    )
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($products) . " products with incorrect SEO URLs\n\n";

$updated = 0;
$errors = 0;

foreach ($products as $product) {
    $productNumber = $product['product_number'];
    $currentPath = $product['seo_path_info'];

    // Extract the product slug from the current path
    // Format: Ausruestung/wrong-category/product-slug
    $parts = explode('/', $currentPath);
    $productSlug = end($parts);

    // Build new path: Ausruestung/Taschen-Rucksaecke/product-slug
    $newPath = "Ausruestung/Taschen-Rucksaecke/" . $productSlug;

    echo "Updating: $productNumber\n";
    echo "  Old: $currentPath\n";
    echo "  New: $newPath\n";

    try {
        // Update the SEO URL
        $updateStmt = $pdo->prepare("
            UPDATE seo_url
            SET seo_path_info = ?, updated_at = NOW()
            WHERE id = UNHEX(?)
        ");
        $updateStmt->execute([$newPath, $product['seo_url_id']]);

        echo "  -> Updated!\n\n";
        $updated++;
    } catch (PDOException $e) {
        echo "  -> ERROR: " . $e->getMessage() . "\n\n";
        $errors++;
    }
}

echo "=== Complete ===\n";
echo "Updated: $updated\n";
echo "Errors: $errors\n";
