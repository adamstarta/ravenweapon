<?php
/**
 * Create multiple SEO URLs for Snigel products - one per assigned category
 * This enables dynamic URL/breadcrumb based on navigation path
 *
 * When user clicks product from K9 category -> URL shows K9 path
 * When user clicks product from Taschen category -> URL shows Taschen path
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

// Get the sales channel ID and language ID from existing SEO URLs
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

if (!$config) {
    die("Could not find sales channel/language config from existing SEO URLs\n");
}

echo "Sales Channel: {$config['sales_channel_id']}\n";
echo "Language: {$config['language_id']}\n\n";

// Get category path mappings (category_id -> SEO path slug)
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

echo "Found " . count($categories) . " categories\n";
$categoryPaths = [];
$seenPaths = []; // Track seen paths to dedupe
foreach ($categories as $cat) {
    // Skip parent categories like 'Ausruestung/' and 'Alle-Produkte/'
    if ($cat['category_path'] === 'Ausruestung/' || $cat['category_path'] === 'Alle-Produkte/') {
        continue;
    }
    // Skip duplicate paths (keep first one only)
    if (isset($seenPaths[$cat['category_path']])) {
        continue;
    }
    $seenPaths[$cat['category_path']] = true;

    echo "  - {$cat['category_name']}: {$cat['category_path']}\n";
    $categoryPaths[$cat['category_id']] = [
        'name' => $cat['category_name'],
        'path' => $cat['category_path']
    ];
}
echo "\n";

// Get all Snigel products with their categories and current canonical SEO URL
$stmt = $pdo->query("
    SELECT
        HEX(p.id) as product_id,
        p.product_number,
        pt.name as product_name,
        su.seo_path_info as canonical_url,
        HEX(su.id) as canonical_seo_id
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
        echo "SKIP: $productNumber - No canonical URL\n\n";
        $skipped++;
        continue;
    }

    // Extract product slug from canonical URL
    $urlParts = explode('/', $canonicalUrl);
    $productSlug = end($urlParts);

    echo "Processing: $productNumber ($productName)\n";
    echo "  Canonical: $canonicalUrl\n";
    echo "  Slug: $productSlug\n";

    // Get all categories this product belongs to
    $stmt = $pdo->prepare("
        SELECT HEX(pc.category_id) as category_id
        FROM product_category pc
        WHERE pc.product_id = UNHEX(?)
    ");
    $stmt->execute([$productId]);
    $productCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "  Categories: " . count($productCategories) . "\n";

    // Check existing SEO URLs for this product
    $stmt = $pdo->prepare("
        SELECT seo_path_info
        FROM seo_url
        WHERE foreign_key = UNHEX(?)
        AND route_name = 'frontend.detail.page'
    ");
    $stmt->execute([$productId]);
    $existingUrls = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($productCategories as $catId) {
        if (!isset($categoryPaths[$catId])) {
            continue; // Skip categories without SEO paths
        }

        $catInfo = $categoryPaths[$catId];
        $catPath = rtrim($catInfo['path'], '/'); // Remove trailing slash
        $catName = $catInfo['name'];

        // Build new SEO URL path: category-path/product-slug
        $newSeoPath = $catPath . '/' . $productSlug;

        // Check if this URL already exists
        if (in_array($newSeoPath, $existingUrls)) {
            echo "    SKIP: $catName - URL already exists\n";
            $skipped++;
            continue;
        }

        // These URLs are NOT canonical - the canonical one already exists
        $isCanonical = 0;

        // Create new SEO URL
        try {
            $newId = bin2hex(random_bytes(16));

            $stmt = $pdo->prepare("
                INSERT INTO seo_url (
                    id, language_id, sales_channel_id, foreign_key,
                    route_name, path_info, seo_path_info,
                    is_canonical, is_modified, is_deleted,
                    created_at
                ) VALUES (
                    UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?),
                    'frontend.detail.page', ?, ?,
                    ?, 0, 0,
                    NOW()
                )
            ");

            $pathInfo = '/detail/' . strtolower($productId);

            $stmt->execute([
                $newId,
                $config['language_id'],
                $config['sales_channel_id'],
                $productId,
                $pathInfo,
                $newSeoPath,
                $isCanonical
            ]);

            echo "    CREATED: $catName -> $newSeoPath\n";
            $created++;

        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "    SKIP: $catName - Duplicate entry\n";
                $skipped++;
            } else {
                echo "    ERROR: $catName - " . $e->getMessage() . "\n";
                $errors++;
            }
        }
    }

    echo "\n";
}

echo "=== Complete ===\n";
echo "Created: $created\n";
echo "Skipped: $skipped\n";
echo "Errors: $errors\n";
