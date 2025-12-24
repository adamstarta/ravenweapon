<?php
$pdo = new PDO('mysql:host=localhost;dbname=shopware;charset=utf8mb4', 'root', 'root');
$articles = json_decode(file_get_contents('/tmp/snigel-article-data/snigel-articles.json'), true);
echo "Loaded " . count($articles) . " articles\n\n";

$updated = 0;
$notFound = 0;
$skipped = 0;

$updateStmt = $pdo->prepare("UPDATE product SET weight = ?, width = ?, height = ?, length = ?, updated_at = NOW() WHERE product_number = ?");
$findStmt = $pdo->prepare("SELECT product_number FROM product WHERE product_number = ?");

foreach ($articles as $a) {
    $articleNo = trim($a['article_no'] ?? '');
    if (empty($articleNo)) { $skipped++; continue; }

    $findStmt->execute([$articleNo]);
    if (!$findStmt->fetch()) {
        echo "NOT FOUND: $articleNo\n";
        $notFound++;
        continue;
    }

    $weight = $a['weight_g'] ?? null;
    $dim = $a['dimensions_mm'] ?? null;
    $width = $dim['width'] ?? null;
    $height = $dim['height'] ?? null;
    $length = $dim['length'] ?? null;

    if (!$weight && !$width) { $skipped++; continue; }

    $updateStmt->execute([$weight, $width, $height, $length, $articleNo]);
    $updated++;
    echo "UPDATED: $articleNo | Weight: {$weight}g | Dim: {$length}x{$width}x{$height}mm\n";
}

echo "\n=== SYNC COMPLETE ===\n";
echo "Updated: $updated\n";
echo "Not found: $notFound\n";
echo "Skipped: $skipped\n";

// Verify
echo "\n=== VERIFICATION ===\n";
$result = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN weight IS NOT NULL THEN 1 ELSE 0 END) as has_weight,
        SUM(CASE WHEN width IS NOT NULL THEN 1 ELSE 0 END) as has_dim
    FROM product
    WHERE parent_id IS NULL
")->fetch(PDO::FETCH_ASSOC);
echo "Products with weight: {$result['has_weight']} / {$result['total']}\n";
echo "Products with dimensions: {$result['has_dim']} / {$result['total']}\n";
