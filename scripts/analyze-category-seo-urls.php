<?php
/**
 * Analyze Category SEO URLs
 * Find categories with missing or incorrect SEO URLs
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

echo "=== Category SEO URL Analysis ===\n\n";

// Get all categories with their SEO URLs
$sql = "
    SELECT
        LOWER(HEX(c.id)) as category_id,
        c.level,
        ct.name,
        ct.breadcrumb as seo_breadcrumb,
        (SELECT GROUP_CONCAT(su.seo_path_info SEPARATOR ' | ')
         FROM seo_url su
         WHERE su.foreign_key = c.id
         AND su.route_name = 'frontend.navigation.page'
         AND su.is_deleted = 0) as seo_urls,
        (SELECT su.seo_path_info
         FROM seo_url su
         WHERE su.foreign_key = c.id
         AND su.route_name = 'frontend.navigation.page'
         AND su.is_canonical = 1
         AND su.is_deleted = 0
         LIMIT 1) as canonical_url
    FROM category c
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE c.level >= 2
    AND ct.name IS NOT NULL
    ORDER BY c.level, ct.name
";

$stmt = $pdo->query($sql);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($categories) . " categories (Level 2+)\n\n";

// Analyze issues
$noSeoUrl = [];
$wrongSeoUrl = [];
$okSeoUrl = [];

foreach ($categories as $cat) {
    $name = $cat['name'];
    $level = $cat['level'];
    $seoUrls = $cat['seo_urls'];
    $canonicalUrl = $cat['canonical_url'];
    $breadcrumb = json_decode($cat['seo_breadcrumb'], true);

    // Expected URL should have (level - 1) slashes for the parent path
    // e.g., Level 3 = parent/child/ (1 parent segment)
    // Level 4 = parent/child/grandchild/ (2 parent segments)

    if (empty($seoUrls)) {
        $noSeoUrl[] = [
            'name' => $name,
            'level' => $level,
            'id' => $cat['category_id'],
            'breadcrumb' => $breadcrumb
        ];
    } else {
        // Check if canonical URL has proper parent path
        $slashCount = $canonicalUrl ? substr_count($canonicalUrl, '/') : 0;
        $expectedSlashes = $level - 1; // Level 3 needs 2 slashes (parent/child/)

        if ($slashCount < $expectedSlashes) {
            $wrongSeoUrl[] = [
                'name' => $name,
                'level' => $level,
                'id' => $cat['category_id'],
                'canonical' => $canonicalUrl,
                'all_urls' => $seoUrls,
                'expected_slashes' => $expectedSlashes,
                'actual_slashes' => $slashCount,
                'breadcrumb' => $breadcrumb
            ];
        } else {
            $okSeoUrl[] = [
                'name' => $name,
                'level' => $level,
                'canonical' => $canonicalUrl
            ];
        }
    }
}

echo "=== Categories WITHOUT SEO URL (" . count($noSeoUrl) . ") ===\n";
foreach ($noSeoUrl as $cat) {
    $breadcrumbPath = is_array($cat['breadcrumb']) ? implode(' > ', $cat['breadcrumb']) : 'N/A';
    echo sprintf("  Level %d: %s (ID: %s)\n", $cat['level'], $cat['name'], substr($cat['id'], 0, 8));
    echo sprintf("           Path: %s\n", $breadcrumbPath);
}

echo "\n=== Categories with WRONG SEO URL (" . count($wrongSeoUrl) . ") ===\n";
foreach ($wrongSeoUrl as $cat) {
    echo sprintf("  Level %d: %s\n", $cat['level'], $cat['name']);
    echo sprintf("           Canonical: %s\n", $cat['canonical']);
    echo sprintf("           All URLs: %s\n", $cat['all_urls']);
    echo sprintf("           Expected %d slashes, got %d\n", $cat['expected_slashes'], $cat['actual_slashes']);
}

echo "\n=== Categories with CORRECT SEO URL (" . count($okSeoUrl) . ") ===\n";
foreach ($okSeoUrl as $cat) {
    echo sprintf("  Level %d: %-30s → %s\n", $cat['level'], mb_substr($cat['name'], 0, 28), $cat['canonical']);
}

echo "\n=== Summary ===\n";
echo "Total categories: " . count($categories) . "\n";
echo "Without SEO URL: " . count($noSeoUrl) . "\n";
echo "Wrong SEO URL: " . count($wrongSeoUrl) . "\n";
echo "Correct SEO URL: " . count($okSeoUrl) . "\n";
