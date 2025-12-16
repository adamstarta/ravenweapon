<?php
/**
 * Create missing SEO URLs - WITH DEBUG
 */

$host = '127.0.0.1';
$port = 3306;
$dbname = 'shopware';
$username = 'root';
$password = 'root';

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$config = $pdo->query("
    SELECT DISTINCT HEX(sales_channel_id) as sales_channel_id, HEX(language_id) as language_id
    FROM seo_url WHERE route_name = 'frontend.detail.page' AND is_canonical = 1 LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

// Focus on ONE product for debug
$productNumber = 'SN-25-30l-specialist-backpack-14';

$stmt = $pdo->prepare("
    SELECT HEX(p.id) as product_id, p.product_number, su.seo_path_info as canonical_url
    FROM product p
    LEFT JOIN seo_url su ON su.foreign_key = p.id AND su.route_name = 'frontend.detail.page' AND su.is_canonical = 1
    WHERE p.product_number = ?
");
$stmt->execute([$productNumber]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

$productId = $product['product_id'];
$canonicalUrl = $product['canonical_url'];

echo "Product: $productNumber\n";
echo "Product ID: $productId\n";
echo "Canonical URL: $canonicalUrl\n";

$urlParts = explode('/', $canonicalUrl);
$productSlug = end($urlParts);
echo "Product slug: $productSlug\n\n";

// Get category paths
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

echo "Category paths: " . count($categoryPaths) . "\n";
foreach ($categoryPaths as $p) {
    echo "  - $p\n";
}

// Get existing URLs
$stmt = $pdo->prepare("
    SELECT seo_path_info FROM seo_url
    WHERE foreign_key = UNHEX(?) AND route_name = 'frontend.detail.page'
");
$stmt->execute([$productId]);
$existingUrls = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "\nExisting URLs: " . count($existingUrls) . "\n";
foreach ($existingUrls as $u) {
    echo "  - $u\n";
}

echo "\n=== Processing each category path ===\n";
foreach ($categoryPaths as $catPath) {
    $catPath = rtrim($catPath, '/');

    if ($catPath === 'Ausruestung' || $catPath === 'Alle-Produkte') {
        echo "SKIP parent: $catPath\n";
        continue;
    }

    $newSeoPath = $catPath . '/' . $productSlug;

    // Check existence
    $exists = false;
    $matchedExisting = '';
    foreach ($existingUrls as $existing) {
        if (strtolower($existing) === strtolower($newSeoPath)) {
            $exists = true;
            $matchedExisting = $existing;
            break;
        }
    }

    if ($exists) {
        echo "EXISTS: $newSeoPath (matched: $matchedExisting)\n";
    } else {
        echo "MISSING: $newSeoPath\n";

        // Try to create it
        try {
            $newId = bin2hex(random_bytes(16));
            $pathInfo = '/detail/' . strtolower($productId);

            $stmt = $pdo->prepare("
                INSERT INTO seo_url (
                    id, language_id, sales_channel_id, foreign_key,
                    route_name, path_info, seo_path_info,
                    is_canonical, is_modified, is_deleted, created_at
                ) VALUES (
                    UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?),
                    'frontend.detail.page', ?, ?, 0, 0, 0, NOW()
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

            echo "  -> CREATED!\n";
        } catch (PDOException $e) {
            echo "  -> ERROR: " . $e->getMessage() . "\n";
        }
    }
}
