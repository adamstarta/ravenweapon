<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=shopware;charset=utf8mb4", "root", "root");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$languageId = "0191c12cc15e72189d57328fb3d2d987";
$salesChannelId = "0191c12dd4b970949e9aeec40433be3e";

function slugify($text) {
    $text = strtolower($text);
    $text = str_replace([" ", "/", "&", "ä", "ö", "ü", "ß", "®", "™"], ["-", "-", "-", "ae", "oe", "ue", "ss", "", ""], $text);
    $text = preg_replace("/[^a-z0-9\.-]/", "", $text);
    $text = preg_replace("/-+/", "-", $text);
    return trim($text, "-");
}

echo "=== STEP 1: Build category hierarchy ===\n";

$sql = "
SELECT
    LOWER(HEX(c.id)) as cat_id,
    LOWER(HEX(c.parent_id)) as parent_id,
    c.level,
    (SELECT name FROM category_translation WHERE category_id = c.id AND name IS NOT NULL LIMIT 1) as name
FROM category c
WHERE c.active = 1
ORDER BY c.level ASC
";

$categories = [];
$categoryNames = [];
foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $categories[$row["cat_id"]] = $row["parent_id"];
    if ($row["name"]) {
        $categoryNames[$row["cat_id"]] = slugify($row["name"]);
    }
}
echo "Found " . count($categories) . " categories\n";

function buildCategoryPath($catId, &$categories, &$categoryNames, &$pathCache) {
    if (isset($pathCache[$catId])) return $pathCache[$catId];
    if (!isset($categories[$catId]) || !$categories[$catId]) {
        $pathCache[$catId] = isset($categoryNames[$catId]) ? $categoryNames[$catId] . "/" : "";
        return $pathCache[$catId];
    }
    $parentPath = buildCategoryPath($categories[$catId], $categories, $categoryNames, $pathCache);
    $myName = isset($categoryNames[$catId]) ? $categoryNames[$catId] : "";
    $pathCache[$catId] = $parentPath . ($myName ? $myName . "/" : "");
    return $pathCache[$catId];
}

$pathCache = [];
$categoryPaths = [];
foreach (array_keys($categories) as $catId) {
    $path = buildCategoryPath($catId, $categories, $categoryNames, $pathCache);
    $parts = explode("/", trim($path, "/"));
    if (count($parts) > 1) {
        array_shift($parts); // Remove root category
        $path = implode("/", $parts) . "/";
    } elseif (count($parts) == 1 && stripos($parts[0], "raven") !== false) {
        $path = "";
    }
    if (!empty($path) && $path != "/") {
        $categoryPaths[$catId] = $path;
    }
}

echo "Built paths for " . count($categoryPaths) . " categories\n";

echo "\n=== STEP 2: Fix category SEO URLs ===\n";

$pdo->exec("DELETE FROM seo_url WHERE route_name = 'frontend.navigation.page' AND sales_channel_id = UNHEX('" . $salesChannelId . "')");
echo "Deleted existing category SEO URLs\n";

$catInserted = 0;
foreach ($categoryPaths as $catId => $path) {
    $id = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("INSERT INTO seo_url (id, language_id, sales_channel_id, foreign_key, route_name, path_info, seo_path_info, is_canonical, is_modified, is_deleted, created_at) VALUES (UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), ?, ?, ?, 1, 0, 0, NOW())");
    try {
        $stmt->execute([$id, $languageId, $salesChannelId, $catId, "frontend.navigation.page", "/navigation/" . $catId, $path]);
        $catInserted++;
    } catch (Exception $e) {}
}
echo "Inserted $catInserted category SEO URLs\n";

echo "\nSample category paths:\n";
$i = 0;
foreach ($categoryPaths as $path) {
    if ($i++ < 20) echo "  $path\n";
}

echo "\n=== STEP 3: Fix product SEO URLs ===\n";

$sql = "SELECT LOWER(HEX(p.id)) as product_id, pt.name as product_name, p.product_number, LOWER(HEX(mc.category_id)) as main_category_id FROM product p JOIN product_translation pt ON p.id = pt.product_id LEFT JOIN main_category mc ON p.id = mc.product_id AND mc.sales_channel_id = UNHEX('" . $salesChannelId . "') WHERE p.parent_id IS NULL";

$products = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($products) . " products\n";

$pdo->exec("DELETE FROM seo_url WHERE route_name = 'frontend.detail.page' AND sales_channel_id = UNHEX('" . $salesChannelId . "')");
echo "Deleted existing product SEO URLs\n";

$prodInserted = 0;
$usedPaths = [];
foreach ($products as $product) {
    $categoryPath = "";
    if ($product["main_category_id"] && isset($categoryPaths[$product["main_category_id"]])) {
        $categoryPath = $categoryPaths[$product["main_category_id"]];
    }

    $productSlug = slugify($product["product_name"]);
    $seoPath = $categoryPath . $productSlug;

    if (isset($usedPaths[$seoPath])) {
        $seoPath = $categoryPath . $productSlug . "-" . slugify($product["product_number"]);
    }
    $usedPaths[$seoPath] = true;

    $id = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("INSERT INTO seo_url (id, language_id, sales_channel_id, foreign_key, route_name, path_info, seo_path_info, is_canonical, is_modified, is_deleted, created_at) VALUES (UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), ?, ?, ?, 1, 0, 0, NOW())");
    try {
        $stmt->execute([$id, $languageId, $salesChannelId, $product["product_id"], "frontend.detail.page", "/detail/" . $product["product_id"], $seoPath]);
        $prodInserted++;
    } catch (Exception $e) {}
}
echo "Inserted $prodInserted product SEO URLs\n";

echo "\nSample product paths:\n";
$samples = array_slice(array_keys($usedPaths), 0, 20);
foreach ($samples as $path) { echo "  $path\n"; }

echo "\n=== DONE ===\n";
echo "Categories: $catInserted\n";
echo "Products: $prodInserted\n";
