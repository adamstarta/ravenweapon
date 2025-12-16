<?php
/**
 * Create Shopware custom fields for Snigel color variants
 */

// Helper function to generate UUID
function generateUuid() {
    return bin2hex(random_bytes(16));
}

// Database connection
$host = '127.0.0.1';
$dbname = 'shopware';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to database.\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

// Check if custom field set already exists
$checkStmt = $pdo->prepare("SELECT HEX(id) as id FROM custom_field_set WHERE name = 'snigel_product_fields'");
$checkStmt->execute();
$existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $customFieldSetId = $existing['id'];
    echo "Custom field set already exists. ID: $customFieldSetId\n";
} else {
    // Create custom field set
    $customFieldSetId = generateUuid();

    $config = json_encode([
        'label' => [
            'en-GB' => 'Snigel Product Fields',
            'de-DE' => 'Snigel Produkt Felder'
        ],
        'translated' => true
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO custom_field_set (id, name, config, active, position, created_at)
        VALUES (UNHEX(?), 'snigel_product_fields', ?, 1, 1, NOW())
    ");
    $stmt->execute([$customFieldSetId, $config]);
    echo "Custom field set created. ID: $customFieldSetId\n";

    // Link to product entity
    $stmt = $pdo->prepare("
        INSERT INTO custom_field_set_relation (id, custom_field_set_id, entity_name, created_at)
        VALUES (UNHEX(?), UNHEX(?), 'product', NOW())
    ");
    $stmt->execute([generateUuid(), $customFieldSetId]);
    echo "Linked to product entity.\n";
}

// Create snigel_color_options field
$checkFieldStmt = $pdo->prepare("SELECT id FROM custom_field WHERE name = 'snigel_color_options'");
$checkFieldStmt->execute();
if (!$checkFieldStmt->fetch()) {
    $config = json_encode([
        'label' => [
            'en-GB' => 'Color Options',
            'de-DE' => 'Farboptionen'
        ],
        'helpText' => [
            'en-GB' => 'JSON array of color options',
            'de-DE' => 'JSON-Array von Farboptionen'
        ],
        'componentName' => 'sw-text-field',
        'customFieldType' => 'text',
        'customFieldPosition' => 1
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO custom_field (id, name, type, config, active, set_id, created_at)
        VALUES (UNHEX(?), 'snigel_color_options', 'json', ?, 1, UNHEX(?), NOW())
    ");
    $stmt->execute([generateUuid(), $config, $customFieldSetId]);
    echo "Created snigel_color_options field.\n";
} else {
    echo "snigel_color_options already exists.\n";
}

// Create snigel_has_colors field
$checkFieldStmt = $pdo->prepare("SELECT id FROM custom_field WHERE name = 'snigel_has_colors'");
$checkFieldStmt->execute();
if (!$checkFieldStmt->fetch()) {
    $config = json_encode([
        'label' => [
            'en-GB' => 'Has Color Variants',
            'de-DE' => 'Hat Farbvarianten'
        ],
        'componentName' => 'sw-field',
        'customFieldType' => 'checkbox',
        'customFieldPosition' => 2
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO custom_field (id, name, type, config, active, set_id, created_at)
        VALUES (UNHEX(?), 'snigel_has_colors', 'bool', ?, 1, UNHEX(?), NOW())
    ");
    $stmt->execute([generateUuid(), $config, $customFieldSetId]);
    echo "Created snigel_has_colors field.\n";
} else {
    echo "snigel_has_colors already exists.\n";
}

echo "\n=== Custom fields setup complete! ===\n";
