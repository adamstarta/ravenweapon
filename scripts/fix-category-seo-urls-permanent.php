<?php
/**
 * Fix Category SEO URLs - Permanent Solution
 *
 * Problem: Level 2+ categories have duplicate SEO URLs
 * Solution: Delete short URLs, keep long URLs, protect from indexer
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

echo "=== Fix Category SEO URLs - Permanent Solution ===\n\n";

// Step 1: Find all Level 3+ categories with their SEO URLs
echo "Step 1: Analyzing category SEO URLs...\n";

$sql = "
    SELECT
        LOWER(HEX(su.id)) as seo_id,
        su.seo_path_info,
        su.is_canonical,
        su.is_modified,
        LOWER(HEX(c.id)) as category_id,
        c.level,
        ct.name,
        LENGTH(su.seo_path_info) - LENGTH(REPLACE(su.seo_path_info, '/', '')) as slash_count
    FROM seo_url su
    JOIN category c ON su.foreign_key = c.id
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE c.level >= 3
    AND su.route_name = 'frontend.navigation.page'
    AND ct.name IS NOT NULL
    AND su.is_deleted = 0
    ORDER BY c.id, slash_count DESC
";

$stmt = $pdo->query($sql);
$allUrls = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by category
$categorizedUrls = [];
foreach ($allUrls as $url) {
    $catId = $url['category_id'];
    if (!isset($categorizedUrls[$catId])) {
        $categorizedUrls[$catId] = [
            'name' => $url['name'],
            'level' => $url['level'],
            'urls' => []
        ];
    }
    $categorizedUrls[$catId]['urls'][] = $url;
}

echo "Found " . count($categorizedUrls) . " Level 3+ categories\n\n";

// Step 2: Delete incorrect short URLs first
echo "Step 2: Deleting incorrect short URLs...\n\n";

$deleted = 0;
$deleteErrors = 0;

foreach ($categorizedUrls as $catId => $catData) {
    $urls = $catData['urls'];
    $name = $catData['name'];

    if (count($urls) <= 1) {
        continue; // No duplicates
    }

    echo "Category: $name\n";

    // Delete all except the first one (longest URL)
    for ($i = 1; $i < count($urls); $i++) {
        $wrongUrl = $urls[$i];
        echo "  Deleting: {$wrongUrl['seo_path_info']}\n";

        try {
            $deleteSql = "DELETE FROM seo_url WHERE id = UNHEX(?)";
            $stmt = $pdo->prepare($deleteSql);
            $stmt->execute([$wrongUrl['seo_id']]);
            $deleted++;
        } catch (Exception $e) {
            echo "  ERROR: " . $e->getMessage() . "\n";
            $deleteErrors++;
        }
    }
}

echo "\nDeleted: $deleted URLs\n";
echo "Errors: $deleteErrors\n\n";

// Step 3: Now update the remaining URLs to be canonical and protected
echo "Step 3: Protecting correct URLs from indexer...\n\n";

$protected = 0;
$updateErrors = 0;

// First, reset all is_canonical to 0 for Level 3+ category URLs
$resetSql = "
    UPDATE seo_url su
    JOIN category c ON su.foreign_key = c.id
    SET su.is_canonical = 0
    WHERE c.level >= 3
    AND su.route_name = 'frontend.navigation.page'
    AND su.is_deleted = 0
";
$pdo->exec($resetSql);
echo "Reset all is_canonical flags\n";

// Now set canonical and modified for remaining URLs
foreach ($categorizedUrls as $catId => $catData) {
    $urls = $catData['urls'];
    $name = $catData['name'];

    if (count($urls) === 0) {
        continue;
    }

    $correctUrl = $urls[0]; // First one has most slashes

    try {
        $updateSql = "UPDATE seo_url SET is_canonical = 1, is_modified = 1 WHERE id = UNHEX(?)";
        $stmt = $pdo->prepare($updateSql);
        $stmt->execute([$correctUrl['seo_id']]);
        $protected++;
    } catch (Exception $e) {
        echo "ERROR updating $name: " . $e->getMessage() . "\n";
        $updateErrors++;
    }
}

echo "\nProtected: $protected URLs\n";
echo "Errors: $updateErrors\n\n";

// Summary
echo "=== Summary ===\n";
echo "Categories: " . count($categorizedUrls) . "\n";
echo "URLs deleted: $deleted\n";
echo "URLs protected: $protected\n";
echo "Total errors: " . ($deleteErrors + $updateErrors) . "\n\n";

// Verification
echo "=== Verification ===\n";
$verifySql = "
    SELECT su.seo_path_info, su.is_canonical, su.is_modified, ct.name
    FROM seo_url su
    JOIN category c ON su.foreign_key = c.id
    JOIN category_translation ct ON c.id = ct.category_id
    WHERE su.route_name = 'frontend.navigation.page'
    AND c.level >= 3
    AND ct.name IS NOT NULL
    AND su.is_deleted = 0
    ORDER BY ct.name
    LIMIT 20
";
$stmt = $pdo->query($verifySql);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo sprintf("%-30s %-40s %s %s\n", "Category", "URL", "C", "M");
echo str_repeat("-", 80) . "\n";
foreach ($results as $row) {
    echo sprintf("%-30s %-40s %s %s\n",
        mb_substr($row['name'], 0, 28),
        mb_substr($row['seo_path_info'], 0, 38),
        $row['is_canonical'] ? 'Y' : 'N',
        $row['is_modified'] ? 'Y' : 'N'
    );
}

echo "\nDone!\n";
