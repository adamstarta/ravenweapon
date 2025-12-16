<?php
/**
 * Create ALL missing SEO URLs for Snigel products
 * Creates one URL per category path
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

// Get config
$stmt = $pdo->query("
    SELECT DISTINCT
        HEX(sales_channel_id) as sales_channel_id,
        HEX(language_id) as language_id
    FROM seo_url
    WHERE route_name = 'frontend.detail.page'
    AND is_canonical = 1
    LIMIT 1
");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Sales Channel: {$config['sales_channel_id']}\n";
echo "Language: {$config['language_id']}\n\n";

// Get all Snigel products
$stmt = $pdo->query("
    SELECT
        HEX(p.id) as product_id,
        p.product_number,
        su.seo_path_info as canonical_url
    FROM product p
    LEFT JOIN seo_url su ON su.foreign_key = p.id
        AND su.route_name = 'frontend.detail.page'
        AND su.is_canonical = 1
    WHERE p.product_number LIKE 'SN-%'
    AND p.parent_id IS NULL
    ORDER BY p.product_number
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($products) . " Snigel products\n\n";

$totalCreated = 0;
$totalSkipped = 0;
$totalErrors = 0;

foreach ($products as $product) {
    $productId = $product['product_id'];
    $productNumber = $product['product_number'];
    $canonicalUrl = $product['canonical_url'];

    if (empty($canonicalUrl)) {
        continue;
    }

    // Extract product slug
    $urlParts = explode('/', $canonicalUrl);
    $productSlug = end($urlParts);

    // Get ALL distinct category paths for this product
    $stmt = $pdo->prepare("
        SELECT DISTINCT su.seo_path_info as category_path
        FROM product_category pc
        JOIN seo_url su ON su.foreign_key = pc.category_id
            AND su.route_name = 'frontend.navigation.page'
            AND su.is_canonical = 1
        WHERE pc.product_id = UNHEX(?)
        AND su.seo_path_info IS NOT NULL
    ");
    $stmt->execute([$productId]);
    $categoryPaths = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get existing SEO URLs for this product
    $stmt = $pdo->prepare("
        SELECT seo_path_info
        FROM seo_url
        WHERE foreign_key = UNHEX(?)
        AND route_name = 'frontend.detail.page'
    ");
    $stmt->execute([$productId]);
    $existingUrls = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $productCreated = 0;

    foreach ($categoryPaths as $catPath) {
        $catPath = rtrim($catPath, '/');

        // Skip parent categories
        if ($catPath === 'Ausruestung' || $catPath === 'Alle-Produkte') {
            continue;
        }

        // Build new SEO URL path
        $newSeoPath = $catPath . '/' . $productSlug;

        // Check if this URL already exists (case-insensitive comparison)
        $exists = false;
        foreach ($existingUrls as $existing) {
            if (strtolower($existing) === strtolower($newSeoPath)) {
                $exists = true;
                break;
            }
        }

        if ($exists) {
            $totalSkipped++;
            continue;
        }

        // Create new SEO URL
        try {
            $newId = bin2hex(random_bytes(16));
            $pathInfo = '/detail/' . strtolower($productId);

            $stmt = $pdo->prepare("
                INSERT INTO seo_url (
                    id, language_id, sales_channel_id, foreign_key,
                    route_name, path_info, seo_path_info,
                    is_canonical, is_modified, is_deleted,
                    created_at
                ) VALUES (
                    UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?),
                    'frontend.detail.page', ?, ?,
                    0, 0, 0,
                    NOW()
                )
            ");

            $stmt->execute([
                $newId,
                $config['language_id'],
                $config['sales_channel_id'],
                $productId,
                $pathInfo,
                $newSeoPath
            ]);

            $productCreated++;
            $totalCreated++;

        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                $totalSkipped++;
            } else {
                echo "ERROR for $productNumber: " . $e->getMessage() . "\n";
                $totalErrors++;
            }
        }
    }

    if ($productCreated > 0) {
        echo "$productNumber: +$productCreated URLs created\n";
    }
}

echo "\n=== Complete ===\n";
echo "Created: $totalCreated new URLs\n";
echo "Skipped: $totalSkipped (already exist)\n";
echo "Errors: $totalErrors\n";
