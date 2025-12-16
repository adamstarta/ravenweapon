<?php
/**
 * Verify Color Options were saved correctly
 */

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=shopware', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check a multi-color product
$stmt = $pdo->query("
    SELECT
        p.product_number,
        pt.name,
        pt.custom_fields
    FROM product p
    JOIN product_translation pt ON pt.product_id = p.id
    WHERE p.product_number IN (
        'SN-25-30l-specialist-backpack-14',
        'SN-30l-mission-backpack-16',
        'SN-squeeze-vest-plate-carrier-17'
    )
");

echo "=== Verifying Color Options ===\n\n";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Product: {$row['name']}\n";
    echo "Number: {$row['product_number']}\n";

    $customFields = json_decode($row['custom_fields'], true);
    if (isset($customFields['snigel_color_options'])) {
        echo "Has Color Variants: " . ($customFields['snigel_has_color_variants'] ? 'Yes' : 'No') . "\n";
        echo "Color Options:\n";
        foreach ($customFields['snigel_color_options'] as $color) {
            echo "  - {$color['name']}: {$color['imageUrl']}\n";
        }
    } else {
        echo "NO COLOR OPTIONS FOUND!\n";
    }
    echo "\n---\n\n";
}

// Count totals
$stmt = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN pt.custom_fields LIKE '%snigel_color_options%' THEN 1 ELSE 0 END) as with_colors
    FROM product p
    JOIN product_translation pt ON pt.product_id = p.id
    WHERE p.product_number LIKE 'SN-%'
    AND p.parent_id IS NULL
");
$counts = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== TOTALS ===\n";
echo "Total Snigel products: {$counts['total']}\n";
echo "With color options: {$counts['with_colors']}\n";
