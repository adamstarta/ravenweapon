<?php
/**
 * Check all SEO URLs for Zubehör & Sonstiges category
 * Look for any incorrect/old entries causing breadcrumb issues
 */

$host = 'localhost';
$dbname = 'shopware';
$user = 'root';
$pass = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

echo "=== Check Zubehör & Sonstiges SEO URLs ===\n\n";

// Find the category ID for "Zubehör & Sonstiges"
$sql = "
    SELECT
        LOWER(HEX(ct.category_id)) as category_id,
        ct.name,
        ct.language_id,
        l.name as lang_name
    FROM category_translation ct
    JOIN language l ON ct.language_id = l.id
    WHERE ct.name LIKE '%Zubeh%'
    ORDER BY ct.name
";
$stmt = $pdo->query($sql);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Categories matching 'Zubehör':\n";
foreach ($categories as $cat) {
    echo "  {$cat['category_id']} - {$cat['name']} ({$cat['lang_name']})\n";
}

// Get the category ID
$categoryId = null;
foreach ($categories as $cat) {
    if (strpos($cat['name'], 'Zubehör') !== false) {
        $categoryId = $cat['category_id'];
        break;
    }
}

if (!$categoryId) {
    die("\nCould not find category!\n");
}

echo "\n=== All SEO URLs for category $categoryId ===\n\n";

// Get ALL SEO URL entries for this category
$sql = "
    SELECT
        LOWER(HEX(su.id)) as seo_id,
        su.seo_path_info,
        su.path_info,
        su.is_canonical,
        su.is_modified,
        su.is_deleted,
        LOWER(HEX(su.language_id)) as lang_id,
        l.name as lang_name,
        su.created_at,
        su.updated_at
    FROM seo_url su
    JOIN language l ON su.language_id = l.id
    WHERE LOWER(HEX(su.foreign_key)) = ?
    AND su.route_name = 'frontend.navigation.page'
    ORDER BY su.is_deleted, su.is_canonical DESC, su.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$categoryId]);
$seoUrls = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($seoUrls) === 0) {
    echo "NO SEO URLs found for this category!\n";
} else {
    echo "Found " . count($seoUrls) . " SEO URL entries:\n\n";

    foreach ($seoUrls as $url) {
        $status = [];
        if ($url['is_canonical']) $status[] = 'CANONICAL';
        if ($url['is_modified']) $status[] = 'MODIFIED';
        if ($url['is_deleted']) $status[] = 'DELETED';
        $statusStr = implode(', ', $status) ?: 'none';

        echo "ID: {$url['seo_id']}\n";
        echo "  SEO Path: {$url['seo_path_info']}\n";
        echo "  Path Info: {$url['path_info']}\n";
        echo "  Language: {$url['lang_name']} ({$url['lang_id']})\n";
        echo "  Status: $statusStr\n";
        echo "  Created: {$url['created_at']}\n";
        echo "  Updated: {$url['updated_at']}\n";
        echo "\n";
    }
}

// Also check for any SEO URLs with double dashes or wrong patterns
echo "\n=== Search for any SEO URLs with 'zubehoer' ===\n\n";

$sql = "
    SELECT
        LOWER(HEX(su.id)) as seo_id,
        su.seo_path_info,
        su.is_canonical,
        su.is_deleted,
        LOWER(HEX(su.foreign_key)) as foreign_key,
        LOWER(HEX(su.language_id)) as lang_id,
        l.name as lang_name
    FROM seo_url su
    JOIN language l ON su.language_id = l.id
    WHERE su.seo_path_info LIKE '%zubehoer%'
    ORDER BY su.seo_path_info
";
$stmt = $pdo->query($sql);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $url) {
    $flags = [];
    if ($url['is_canonical']) $flags[] = 'canonical';
    if ($url['is_deleted']) $flags[] = 'deleted';
    $flagStr = implode(',', $flags) ?: '-';

    echo "{$url['seo_path_info']} | {$url['lang_name']} | $flagStr | FK: {$url['foreign_key']}\n";
}

echo "\n=== Check for English language sales channel configuration ===\n\n";

$sql = "
    SELECT
        LOWER(HEX(scd.id)) as domain_id,
        scd.url,
        LOWER(HEX(scd.language_id)) as lang_id,
        l.name as lang_name
    FROM sales_channel_domain scd
    JOIN language l ON scd.language_id = l.id
    ORDER BY scd.url
";
$stmt = $pdo->query($sql);
$domains = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($domains as $d) {
    echo "{$d['url']} | Language: {$d['lang_name']} ({$d['lang_id']})\n";
}
