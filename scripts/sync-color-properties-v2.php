<?php
/**
 * Sync Color Properties from Custom Fields to Shopware Properties (v2 - with debug)
 */

$pdo = new PDO('mysql:host=127.0.0.1;dbname=shopware;charset=utf8mb4', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Sync Color Properties v2 ===\n\n";

$COLOR_WHITELIST = [
    'Black', 'Clear', 'Coyote', 'Grey', 'HighVis yellow', 'Khaki',
    'Multicam', 'Navy', 'Olive', 'Ranger Green', 'Swecam', 'Various', 'White'
];

// Get property group ID
$stmt = $pdo->query("
    SELECT LOWER(HEX(pg.id)) as id FROM property_group pg
    JOIN property_group_translation pgt ON pg.id = pgt.property_group_id
    WHERE pgt.name = 'Farbe' LIMIT 1
");
$propertyGroupId = $stmt->fetchColumn();
echo "Property Group ID: $propertyGroupId\n";

// Get color option IDs
$colorOptionIds = [];
$stmt = $pdo->prepare("
    SELECT LOWER(HEX(pgo.id)) as id, pgot.name
    FROM property_group_option pgo
    JOIN property_group_option_translation pgot ON pgo.id = pgot.property_group_option_id
    WHERE pgo.property_group_id = UNHEX(?)
");
$stmt->execute([$propertyGroupId]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $colorOptionIds[$row['name']] = $row['id'];
}
echo "Color options loaded: " . count($colorOptionIds) . "\n";
echo "Options: " . implode(', ', array_keys($colorOptionIds)) . "\n\n";

// Get products with color custom fields
$stmt = $pdo->query("
    SELECT LOWER(HEX(p.id)) as id, p.product_number, pt.custom_fields
    FROM product p
    JOIN product_translation pt ON p.id = pt.product_id
    WHERE p.parent_id IS NULL
    AND pt.custom_fields LIKE '%snigel_color_options%'
");

$totalUpdated = 0;
$totalAlreadyAssigned = 0;
$totalSkipped = 0;

while ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $productId = $product['id'];
    $productNumber = $product['product_number'];
    $customFields = json_decode($product['custom_fields'], true) ?: [];

    $snigelColors = $customFields['snigel_color_options'] ?? [];

    $productColors = [];
    foreach ($snigelColors as $colorData) {
        if (is_array($colorData) && isset($colorData['name'])) {
            $productColors[] = $colorData['name'];
        }
    }

    $validColors = array_filter($productColors, function($c) use ($COLOR_WHITELIST) {
        return in_array($c, $COLOR_WHITELIST);
    });

    if (empty($validColors)) {
        $totalSkipped++;
        continue;
    }

    $assigned = 0;
    $alreadyHas = 0;

    foreach ($validColors as $colorName) {
        if (!isset($colorOptionIds[$colorName])) {
            echo "  [MISSING OPTION] $colorName\n";
            continue;
        }

        $optionId = $colorOptionIds[$colorName];

        // Check if already assigned
        $checkStmt = $pdo->prepare("
            SELECT 1 FROM product_property
            WHERE product_id = UNHEX(?) AND property_group_option_id = UNHEX(?)
        ");
        $checkStmt->execute([$productId, $optionId]);

        if ($checkStmt->fetchColumn()) {
            $alreadyHas++;
        } else {
            // Insert new assignment
            try {
                $pdo->prepare("
                    INSERT INTO product_property (product_id, property_group_option_id)
                    VALUES (UNHEX(?), UNHEX(?))
                ")->execute([$productId, $optionId]);
                $assigned++;
            } catch (PDOException $e) {
                echo "  [ERROR] $productNumber: " . $e->getMessage() . "\n";
            }
        }
    }

    if ($assigned > 0) {
        $totalUpdated++;
        echo "[ASSIGNED] $productNumber: " . implode(', ', $validColors) . "\n";
    } elseif ($alreadyHas > 0) {
        $totalAlreadyAssigned++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Products updated: $totalUpdated\n";
echo "Products already had colors: $totalAlreadyAssigned\n";
echo "Products skipped (no valid colors): $totalSkipped\n";

// Clear cache hint
echo "\nRun: bin/console cache:clear\n";
