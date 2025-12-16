<?php
/**
 * Assign SEO Categories to Products
 *
 * This script assigns each product's first category as its SEO category
 * so the SEO URL template can use it to create nice URLs like:
 * /snigel/tactical-gear/product-name
 */

$host = '127.0.0.1';
$dbname = 'shopware';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "=== Assigning SEO Categories to Products ===\n\n";

    // Get all products first
    $productStmt = $pdo->query("
        SELECT HEX(p.id) as product_id, pt.name as product_name
        FROM product p
        LEFT JOIN product_translation pt ON p.id = pt.product_id
        WHERE p.parent_id IS NULL
        ORDER BY pt.name
    ");
    $allProducts = $productStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($allProducts) . " products\n\n";

    // Prepare query to get best category for each product
    $catStmt = $pdo->prepare("
        SELECT HEX(pc.category_id) as category_id, ct.name as category_name, c.level
        FROM product_category pc
        JOIN category c ON pc.category_id = c.id
        JOIN category_translation ct ON c.id = ct.category_id
        WHERE HEX(pc.product_id) = ?
        AND ct.name NOT IN ('Alle Produkte', 'Home', 'Catalogue #1')
        ORDER BY c.level DESC
        LIMIT 1
    ");

    $products = [];
    foreach ($allProducts as $product) {
        $catStmt->execute([$product['product_id']]);
        $cat = $catStmt->fetch(PDO::FETCH_ASSOC);
        if ($cat) {
            $product['category_id'] = $cat['category_id'];
            $product['category_name'] = $cat['category_name'];
            $products[] = $product;
        }
    }

    echo "Found " . count($products) . " products with categories\n\n";

    // Prepare update statement
    $updateStmt = $pdo->prepare("
        UPDATE product
        SET seo_category_id = UNHEX(?),
            updated_at = NOW()
        WHERE HEX(id) = ?
    ");

    $updated = 0;
    foreach ($products as $product) {
        if ($product['category_id']) {
            $updateStmt->execute([$product['category_id'], $product['product_id']]);
            if ($updateStmt->rowCount() > 0) {
                $updated++;
                echo "  ✓ {$product['product_name']} -> {$product['category_name']}\n";
            }
        }
    }

    echo "\n✅ Updated $updated products with SEO categories\n";

    // Now check products without categories
    $checkStmt = $pdo->query("
        SELECT COUNT(*) as cnt FROM product WHERE parent_id IS NULL AND seo_category_id IS NULL
    ");
    $noCategory = $checkStmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    if ($noCategory > 0) {
        echo "\n⚠️ $noCategory products still have no SEO category (they have no category assigned)\n";
    }

    echo "\n=== Next Step ===\n";
    echo "Run: docker exec shopware-chf bash -c 'cd /var/www/html && bin/console dal:refresh:index --only=product.indexer && bin/console cache:clear'\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
