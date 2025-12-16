<?php
/**
 * Complete SEO URL fix:
 * 1. Undelete canonical URLs
 * 2. Create SEO URLs for ALL category paths (German and English)
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

// Step 1: Undelete all deleted canonical URLs for Snigel products
echo "=== Step 1: Undelete canonical URLs ===\n";
$stmt = $pdo->query("
    UPDATE seo_url su
    JOIN product p ON su.foreign_key = p.id
    SET su.is_deleted = 0
    WHERE p.product_number LIKE 'SN-%'
    AND su.route_name = 'frontend.detail.page'
    AND su.is_canonical = 1
    AND su.is_deleted = 1
");
$undeleted = $stmt->rowCount();
echo "Undeleted $undeleted canonical URLs\n\n";

// Get config from existing URLs
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

// Step 2: Get ALL category paths (not deduplicated)
echo "=== Step 2: Create URLs for all category paths ===\n";

$stmt = $pdo->query("
    SELECT
        HEX(c.id) as category_id,
        ct.name as category_name,
        su.seo_path_info as category_path
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    LEFT JOIN seo_url su ON su.foreign_key = c.id
        AND su.route_name = 'frontend.navigation.page'
        AND su.is_canonical = 1
    WHERE c.id IN (
        SELECT DISTINCT pc.category_id
        FROM product_category pc
        JOIN product p ON pc.product_id = p.id
        WHERE p.product_number LIKE 'SN-%'
    )
    AND su.seo_path_info IS NOT NULL
    AND su.seo_path_info != ''
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build path -> category_id mapping
$pathToCategoryId = [];
foreach ($categories as $cat) {
    $path = rtrim($cat['category_path'], '/');
    // Skip parent categories
    if ($path === 'Ausruestung' || $path === 'Alle-Produkte') {
        continue;
    }
    $pathToCategoryId[$path] = $cat['category_id'];
}

echo "Found " . count($pathToCategoryId) . " unique category paths\n\n";

// Get all Snigel products
$stmt = $pdo->query("
    SELECT
        HEX(p.id) as product_id,
        p.product_number,
        pt.name as product_name,
        su.seo_path_info as canonical_url
    FROM product p
    JOIN product_translation pt ON p.id = pt.product_id
    LEFT JOIN seo_url su ON su.foreign_key = p.id
        AND su.route_name = 'frontend.detail.page'
        AND su.is_canonical = 1
    WHERE p.product_number LIKE 'SN-%'
    AND p.parent_id IS NULL
    ORDER BY p.product_number
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($products) . " Snigel products\n\n";

$created = 0;
$skipped = 0;
$errors = 0;

foreach ($products as $product) {
    $productId = $product['product_id'];
    $productNumber = $product['product_number'];
    $productName = $product['product_name'];
    $canonicalUrl = $product['canonical_url'];

    if (empty($canonicalUrl)) {
        continue;
    }

    // Extract product slug from canonical URL
    $urlParts = explode('/', $canonicalUrl);
    $productSlug = end($urlParts);

    // Get all categories this product belongs to
    $stmt = $pdo->prepare("
        SELECT DISTINCT su.seo_path_info as category_path
        FROM product_category pc
        JOIN category c ON pc.category_id = c.id
        JOIN seo_url su ON su.foreign_key = c.id
            AND su.route_name = 'frontend.navigation.page'
            AND su.is_canonical = 1
        WHERE pc.product_id = UNHEX(?)
        AND su.seo_path_info IS NOT NULL
    ");
    $stmt->execute([$productId]);
    $productCategoryPaths = $stmt->fetchAll(PDO::FETCH_COLUMN);

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

    foreach ($productCategoryPaths as $catPath) {
        $catPath = rtrim($catPath, '/');

        // Skip parent categories
        if ($catPath === 'Ausruestung' || $catPath === 'Alle-Produkte') {
            continue;
        }

        // Build new SEO URL path
        $newSeoPath = $catPath . '/' . $productSlug;

        // Check if this URL already exists
        if (in_array($newSeoPath, $existingUrls)) {
            $skipped++;
            continue;
        }

        // Create new SEO URL (non-canonical)
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
            $created++;

        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                $skipped++;
            } else {
                $errors++;
            }
        }
    }

    if ($productCreated > 0) {
        echo "$productNumber: +$productCreated URLs\n";
    }
}

echo "\n=== Complete ===\n";
echo "Undeleted: $undeleted canonical URLs\n";
echo "Created: $created new URLs\n";
echo "Skipped: $skipped (already exist)\n";
echo "Errors: $errors\n";
