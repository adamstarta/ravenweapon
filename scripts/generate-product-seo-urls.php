<?php
/**
 * Generate Product SEO URLs with Category Path
 *
 * Creates URLs like: /snigel/tactical-gear/product-name
 * Instead of: /product-name/product-number
 */

$host = '127.0.0.1';
$dbname = 'shopware';
$username = 'root';
$password = 'root';

function slugify($text) {
    // German umlaut replacements
    $replacements = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
        ' ' => '-', '/' => '-', '\\' => '-', '&' => '-',
        '(' => '', ')' => '', '"' => '', "'" => '',
        ',' => '', '.' => '', ':' => '', ';' => '',
        '+' => '-', '*' => '', '!' => '', '?' => ''
    ];

    $text = str_replace(array_keys($replacements), array_values($replacements), $text);
    $text = preg_replace('/[^a-zA-Z0-9\-]/', '', $text);
    $text = preg_replace('/-+/', '-', $text); // Multiple dashes to single
    $text = strtolower(trim($text, '-'));

    return $text;
}

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "=== Generating Product SEO URLs with Category Path ===\n\n";

    // Get sales channel ID
    $scStmt = $pdo->query("SELECT HEX(id) as id FROM sales_channel WHERE type_id = UNHEX('8A243080F92E4C719546314B577CF82B') LIMIT 1");
    $salesChannel = $scStmt->fetch(PDO::FETCH_ASSOC);
    $salesChannelId = $salesChannel['id'];
    echo "Sales Channel ID: $salesChannelId\n";

    // Get language ID
    $langStmt = $pdo->query("SELECT HEX(id) as id FROM language WHERE HEX(locale_id) IN (SELECT HEX(id) FROM locale WHERE code = 'de-DE') LIMIT 1");
    $language = $langStmt->fetch(PDO::FETCH_ASSOC);
    $languageId = $language['id'];
    echo "Language ID: $languageId\n\n";

    // Get all products with their categories
    $productStmt = $pdo->query("
        SELECT
            HEX(p.id) as product_id,
            pt.name as product_name,
            p.product_number
        FROM product p
        JOIN product_translation pt ON p.id = pt.product_id
        WHERE p.parent_id IS NULL
        AND p.active = 1
        ORDER BY pt.name
    ");
    $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($products) . " active products\n\n";

    // Prepare to get category path for each product
    $catPathStmt = $pdo->prepare("
        SELECT su.seo_path_info
        FROM product_category pc
        JOIN seo_url su ON pc.category_id = su.foreign_key
            AND su.route_name = 'frontend.navigation.page'
            AND su.is_canonical = 1
            AND HEX(su.sales_channel_id) = ?
        JOIN category c ON pc.category_id = c.id
        JOIN category_translation ct ON c.id = ct.category_id
        WHERE HEX(pc.product_id) = ?
        AND ct.name NOT IN ('Alle Produkte', 'Home', 'Catalogue #1')
        ORDER BY c.level DESC
        LIMIT 1
    ");

    // Prepare insert/update for SEO URLs
    $checkStmt = $pdo->prepare("
        SELECT HEX(id) as id FROM seo_url
        WHERE HEX(foreign_key) = ?
        AND route_name = 'frontend.detail.page'
        AND HEX(sales_channel_id) = ?
        AND is_canonical = 1
    ");

    $updateStmt = $pdo->prepare("
        UPDATE seo_url
        SET seo_path_info = ?, updated_at = NOW()
        WHERE HEX(id) = ?
    ");

    $insertStmt = $pdo->prepare("
        INSERT INTO seo_url (id, language_id, sales_channel_id, foreign_key, route_name, path_info, seo_path_info, is_canonical, is_modified, is_deleted, created_at)
        VALUES (UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), 'frontend.detail.page', ?, ?, 1, 1, 0, NOW())
    ");

    $updated = 0;
    $inserted = 0;
    $skipped = 0;

    foreach ($products as $product) {
        // Get category path
        $catPathStmt->execute([$salesChannelId, $product['product_id']]);
        $catPath = $catPathStmt->fetch(PDO::FETCH_ASSOC);

        $categoryPath = '';
        if ($catPath && !empty($catPath['seo_path_info'])) {
            $categoryPath = rtrim($catPath['seo_path_info'], '/') . '/';
        }

        // Generate SEO URL (add product number suffix to ensure uniqueness)
        $productSlug = slugify($product['product_name']);
        $productNumSlug = slugify($product['product_number']);
        // Use product number as suffix to ensure uniqueness
        $newSeoPath = $categoryPath . $productSlug . '-' . $productNumSlug . '/';
        $pathInfo = '/detail/' . strtolower($product['product_id']);

        // Check if SEO URL exists
        $checkStmt->execute([$product['product_id'], $salesChannelId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing
            $updateStmt->execute([$newSeoPath, $existing['id']]);
            if ($updateStmt->rowCount() > 0) {
                $updated++;
                echo "  ✓ Updated: $newSeoPath ({$product['product_name']})\n";
            } else {
                $skipped++;
            }
        } else {
            // Insert new
            $newId = strtoupper(bin2hex(random_bytes(16)));
            $insertStmt->execute([$newId, $languageId, $salesChannelId, $product['product_id'], $pathInfo, $newSeoPath]);
            $inserted++;
            echo "  + Inserted: $newSeoPath ({$product['product_name']})\n";
        }
    }

    echo "\n=== Summary ===\n";
    echo "Updated: $updated\n";
    echo "Inserted: $inserted\n";
    echo "Skipped (unchanged): $skipped\n";

    echo "\nClearing cache...\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
