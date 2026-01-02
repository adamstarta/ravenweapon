<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=shopware;charset=utf8mb4", "root", "root");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$salesChannelId = "0191c12dd4b970949e9aeec40433be3e";

echo "=== SEO URL ISSUE CHECK ===\n\n";

// 1. Products WITHOUT SEO URLs
echo "1. Products WITHOUT SEO URLs:\n";
$sql = "SELECT COUNT(*) as cnt FROM product p WHERE p.parent_id IS NULL AND NOT EXISTS (SELECT 1 FROM seo_url s WHERE s.foreign_key = p.id AND s.route_name = 'frontend.detail.page' AND s.sales_channel_id = UNHEX('$salesChannelId'))";
$cnt = $pdo->query($sql)->fetch()['cnt'];
echo "   Count: $cnt\n";

if ($cnt > 0) {
    $sql = "SELECT LOWER(HEX(p.id)) as pid, (SELECT name FROM product_translation WHERE product_id = p.id LIMIT 1) as name FROM product p WHERE p.parent_id IS NULL AND NOT EXISTS (SELECT 1 FROM seo_url s WHERE s.foreign_key = p.id AND s.route_name = 'frontend.detail.page' AND s.sales_channel_id = UNHEX('$salesChannelId')) LIMIT 10";
    foreach ($pdo->query($sql) as $row) {
        echo "   - {$row['pid']}: {$row['name']}\n";
    }
}

// 2. Products with alle-produkte in URL (potential wrong category)
echo "\n2. Products with 'alle-produkte' in URL (wrong category?):\n";
$sql = "SELECT LOWER(HEX(s.foreign_key)) as pid, s.seo_path_info, (SELECT name FROM product_translation WHERE product_id = s.foreign_key LIMIT 1) as name FROM seo_url s WHERE s.route_name = 'frontend.detail.page' AND s.sales_channel_id = UNHEX('$salesChannelId') AND s.seo_path_info LIKE 'alle-produkte%'";
$results = $pdo->query($sql)->fetchAll();
echo "   Count: " . count($results) . "\n";
foreach ($results as $row) {
    echo "   - {$row['seo_path_info']} ({$row['name']})\n";
}

// 3. Products with technical URLs (hex IDs)
echo "\n3. Products with technical URLs (hex IDs in path):\n";
$sql = "SELECT LOWER(HEX(s.foreign_key)) as pid, s.seo_path_info FROM seo_url s WHERE s.route_name = 'frontend.detail.page' AND s.sales_channel_id = UNHEX('$salesChannelId') AND s.seo_path_info REGEXP '[a-f0-9]{20}'";
$results = $pdo->query($sql)->fetchAll();
echo "   Count: " . count($results) . "\n";
foreach (array_slice($results, 0, 10) as $row) {
    echo "   - {$row['seo_path_info']}\n";
}

// 4. Products without category path in URL (no slash)
echo "\n4. Products without category path (no '/' in URL):\n";
$sql = "SELECT s.seo_path_info, (SELECT name FROM product_translation WHERE product_id = s.foreign_key LIMIT 1) as name FROM seo_url s WHERE s.route_name = 'frontend.detail.page' AND s.sales_channel_id = UNHEX('$salesChannelId') AND s.seo_path_info NOT LIKE '%/%'";
$results = $pdo->query($sql)->fetchAll();
echo "   Count: " . count($results) . "\n";
foreach (array_slice($results, 0, 10) as $row) {
    echo "   - {$row['seo_path_info']} ({$row['name']})\n";
}

// 5. Products with main_category = "Alle Produkte"
echo "\n5. Products with main_category = 'Alle Produkte':\n";
$sql = "SELECT LOWER(HEX(p.id)) as pid, MAX(pt.name) as product_name, MAX(s.seo_path_info) as seo_path_info
FROM product p
JOIN product_translation pt ON p.id = pt.product_id
JOIN main_category mc ON p.id = mc.product_id AND mc.sales_channel_id = UNHEX('$salesChannelId')
JOIN category_translation ct ON mc.category_id = ct.category_id
LEFT JOIN seo_url s ON p.id = s.foreign_key AND s.route_name = 'frontend.detail.page' AND s.sales_channel_id = UNHEX('$salesChannelId')
WHERE ct.name = 'Alle Produkte' AND p.parent_id IS NULL
GROUP BY p.id";
$results = $pdo->query($sql)->fetchAll();
echo "   Count: " . count($results) . "\n";
foreach ($results as $row) {
    echo "   - {$row['product_name']}: {$row['seo_path_info']}\n";
}

// 6. Categories with technical URLs
echo "\n6. Categories with technical URLs:\n";
$sql = "SELECT LOWER(HEX(s.foreign_key)) as cid, s.seo_path_info FROM seo_url s WHERE s.route_name = 'frontend.navigation.page' AND s.sales_channel_id = UNHEX('$salesChannelId') AND (s.seo_path_info REGEXP '[a-f0-9]{20}' OR s.seo_path_info LIKE '%navigation%')";
$results = $pdo->query($sql)->fetchAll();
echo "   Count: " . count($results) . "\n";
foreach (array_slice($results, 0, 10) as $row) {
    echo "   - {$row['seo_path_info']}\n";
}

// 7. Duplicate SEO URLs
echo "\n7. Duplicate SEO URLs (same path, different products):\n";
$sql = "SELECT seo_path_info, COUNT(*) as cnt FROM seo_url WHERE route_name = 'frontend.detail.page' AND sales_channel_id = UNHEX('$salesChannelId') GROUP BY seo_path_info HAVING cnt > 1";
$results = $pdo->query($sql)->fetchAll();
echo "   Count: " . count($results) . "\n";
foreach (array_slice($results, 0, 10) as $row) {
    echo "   - {$row['seo_path_info']} (x{$row['cnt']})\n";
}

echo "\n=== CHECK COMPLETE ===\n";
