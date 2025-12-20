<?php
/**
 * Fix German Category Names
 * Updates German translation names where they are NULL, using English names
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

echo "=== Fix German Category Names ===\n\n";

// Get German language ID
$sql = "SELECT id FROM language WHERE name = 'Deutsch' LIMIT 1";
$stmt = $pdo->query($sql);
$germanLangId = $stmt->fetchColumn();
echo "German Language ID: " . bin2hex($germanLangId) . "\n";

// Get English language ID
$sql = "SELECT id FROM language WHERE name = 'English' LIMIT 1";
$stmt = $pdo->query($sql);
$englishLangId = $stmt->fetchColumn();
echo "English Language ID: " . bin2hex($englishLangId) . "\n\n";

// Find German translations with NULL names that have English names
$sql = "
    SELECT
        ct_de.category_id,
        ct_en.name as english_name,
        ct_en.breadcrumb as english_breadcrumb,
        c.level
    FROM category_translation ct_de
    JOIN category c ON ct_de.category_id = c.id
    JOIN category_translation ct_en ON ct_en.category_id = ct_de.category_id AND ct_en.language_id = ?
    WHERE ct_de.language_id = ?
    AND ct_de.name IS NULL
    AND ct_en.name IS NOT NULL
    ORDER BY c.level, ct_en.name
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$englishLangId, $germanLangId]);
$nullNames = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($nullNames) . " German translations with NULL names\n\n";

$updated = 0;
$errors = 0;

foreach ($nullNames as $cat) {
    $categoryId = $cat['category_id'];
    $englishName = $cat['english_name'];
    $englishBreadcrumb = $cat['english_breadcrumb'];
    $level = $cat['level'];

    echo "Level $level: $englishName\n";

    try {
        // Update German name and breadcrumb from English
        $updateSql = "
            UPDATE category_translation
            SET name = ?, breadcrumb = ?
            WHERE category_id = ? AND language_id = ?
        ";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            $englishName,
            $englishBreadcrumb,
            $categoryId,
            $germanLangId
        ]);

        echo "  UPDATED to: $englishName\n";
        $updated++;
    } catch (Exception $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== Summary ===\n";
echo "Updated: $updated German category names\n";
echo "Errors: $errors\n";

echo "\n⚠️  Now run the SEO URL fix script!\n";
