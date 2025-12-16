<?php
/**
 * Add SIZE options to Snigel products that have size variants
 * Creates snigel_size_options custom field
 */

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=shopware', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Adding Size Options to Snigel Products ===\n\n";

// Products with size variants - manual mapping based on analysis
$sizeProducts = [
    'SN-covert-equipment-vest-12' => [
        ['name' => 'Size 1', 'value' => 'size-1'],
        ['name' => 'Size 2', 'value' => 'size-2'],
        ['name' => 'Size 3', 'value' => 'size-3'],
    ],
    'SN-technical-equipment-vest-11' => [
        ['name' => 'XS-M', 'value' => 'xs-m'],
        ['name' => 'M-XXL', 'value' => 'm-xxl'],
    ],
    'SN-covert-surveillance-equipment-vest-05' => [
        ['name' => 'Size 1', 'value' => 'size-1'],
        ['name' => 'Size 2', 'value' => 'size-2'],
        ['name' => 'Size 3', 'value' => 'size-3'],
    ],
    'SN-ribs-combat-belt-1-0' => [
        ['name' => 'Size 1', 'value' => 'size-1'],
        ['name' => 'Size 2', 'value' => 'size-2'],
        ['name' => 'Size 3', 'value' => 'size-3'],
    ],
    'SN-police-equipment-belt-09' => [
        ['name' => 'XSmall', 'value' => 'xsmall'],
        ['name' => 'Small', 'value' => 'small'],
        ['name' => 'Medium', 'value' => 'medium'],
        ['name' => 'Large', 'value' => 'large'],
        ['name' => 'XLarge', 'value' => 'xlarge'],
        ['name' => 'XXL', 'value' => 'xxl'],
    ],
    'SN-basic-equipment-belt' => [
        ['name' => 'Small', 'value' => 'small'],
        ['name' => 'Medium', 'value' => 'medium'],
        ['name' => 'Large', 'value' => 'large'],
        ['name' => 'XLarge', 'value' => 'xlarge'],
    ],
    'SN-elastic-pouch-1-0' => [
        ['name' => '5.56', 'value' => '556'],
        ['name' => '7.62', 'value' => '762'],
    ],
    'SN-covert-equipment-belt-17' => [
        ['name' => 'Small', 'value' => 'small'],
        ['name' => 'Medium', 'value' => 'medium'],
        ['name' => 'Large', 'value' => 'large'],
        ['name' => 'XLarge', 'value' => 'xlarge'],
        ['name' => 'XXL', 'value' => 'xxl'],
    ],
    'SN-multi-insert-panel-1-0' => [
        ['name' => '30L', 'value' => '30l'],
        ['name' => '40L', 'value' => '40l'],
    ],
    'SN-tactical-coverall-digi-09f' => [
        ['name' => '241', 'value' => '241'],
        ['name' => '242', 'value' => '242'],
    ],
    'SN-tactical-coverall-09f-complete' => [
        ['name' => 'Briefs', 'value' => 'briefs'],
        ['name' => 'Coverall', 'value' => 'coverall'],
        ['name' => 'Vest', 'value' => 'vest'],
        ['name' => 'COMPLETE', 'value' => 'complete'],
    ],
    'SN-leg-strap-11' => [
        ['name' => 'XSmall', 'value' => 'xsmall'],
        ['name' => 'Small', 'value' => 'small'],
        ['name' => 'Medium', 'value' => 'medium'],
        ['name' => 'Large', 'value' => 'large'],
        ['name' => 'XLarge', 'value' => 'xlarge'],
    ],
    // Also add Fleece jacket which has size variants
    'SN-fleece-jacket-1-0' => [
        ['name' => 'Small short', 'value' => 'small-short'],
        ['name' => 'Medium regular', 'value' => 'medium-regular'],
        ['name' => 'Large regular', 'value' => 'large-regular'],
        ['name' => 'XL regular', 'value' => 'xl-regular'],
    ],
    // Binder mechanism has "2" and "4" which are ring counts
    'SN-binder-mechanism' => [
        ['name' => '2 Rings', 'value' => '2-rings'],
        ['name' => '4 Rings', 'value' => '4-rings'],
    ],
];

// Prepare update statement
$updateStmt = $pdo->prepare("
    UPDATE product_translation pt
    JOIN product p ON pt.product_id = p.id
    SET pt.custom_fields = :custom_fields
    WHERE p.product_number = :product_number
");

$updated = 0;

foreach ($sizeProducts as $productNumber => $sizeOptions) {
    // Get current custom fields
    $stmt = $pdo->prepare("
        SELECT pt.custom_fields
        FROM product p
        JOIN product_translation pt ON p.id = pt.product_id
        WHERE p.product_number = ?
        LIMIT 1
    ");
    $stmt->execute([$productNumber]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo "[NOT FOUND] $productNumber\n";
        continue;
    }

    $customFields = json_decode($row['custom_fields'] ?? '{}', true) ?: [];

    // Add size options
    $customFields['snigel_size_options'] = json_encode($sizeOptions);
    $customFields['snigel_has_sizes'] = true;

    // Update database
    $updateStmt->execute([
        'custom_fields' => json_encode($customFields),
        'product_number' => $productNumber,
    ]);

    $sizes = array_map(function($s) { return $s['name']; }, $sizeOptions);
    echo "[UPDATED] $productNumber\n";
    echo "  Sizes: " . implode(', ', $sizes) . "\n\n";
    $updated++;
}

echo "=== SUMMARY ===\n";
echo "Products updated with size options: $updated\n";
