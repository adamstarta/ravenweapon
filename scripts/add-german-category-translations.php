<?php
/**
 * Add Missing German Category Translations
 * Copies English translations to German for categories that are missing them
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

echo "=== Add Missing German Category Translations ===\n\n";

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

// Find categories with English but no German translation
$sql = "
    SELECT
        ct_en.category_id,
        ct_en.name as english_name,
        ct_en.breadcrumb as english_breadcrumb,
        ct_en.meta_title,
        ct_en.meta_description,
        ct_en.keywords,
        ct_en.custom_fields,
        c.level
    FROM category_translation ct_en
    JOIN category c ON ct_en.category_id = c.id
    LEFT JOIN category_translation ct_de ON ct_de.category_id = ct_en.category_id AND ct_de.language_id = ?
    WHERE ct_en.language_id = ?
    AND ct_de.category_id IS NULL
    AND ct_en.name IS NOT NULL
    ORDER BY c.level, ct_en.name
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$germanLangId, $englishLangId]);
$missingTranslations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($missingTranslations) . " categories missing German translation\n\n";

$created = 0;
$errors = 0;

foreach ($missingTranslations as $cat) {
    $categoryId = $cat['category_id'];
    $englishName = $cat['english_name'];
    $level = $cat['level'];

    echo "Level $level: $englishName\n";

    try {
        $insertSql = "
            INSERT INTO category_translation (
                category_id, language_id, name, breadcrumb,
                meta_title, meta_description, keywords, custom_fields,
                created_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                NOW()
            )
        ";
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            $categoryId,
            $germanLangId,
            $englishName,
            $cat['english_breadcrumb'],
            $cat['meta_title'],
            $cat['meta_description'],
            $cat['keywords'],
            $cat['custom_fields']
        ]);

        echo "  CREATED German translation\n";
        $created++;
    } catch (Exception $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== Summary ===\n";
echo "Created: $created German translations\n";
echo "Errors: $errors\n";
