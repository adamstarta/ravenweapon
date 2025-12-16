<?php
/**
 * Create Snigel Custom Field Set in Shopware Admin
 *
 * This creates the custom field set so colorOptions are visible in backend
 *
 * Run: docker exec shopware-chf php /tmp/create-snigel-custom-field-set.php
 */

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=shopware', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Creating Snigel Custom Field Set ===\n\n";

// Get default language ID
$stmt = $pdo->query("SELECT HEX(id) as id FROM language WHERE name = 'Deutsch' OR name = 'German' LIMIT 1");
$lang = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$lang) {
    $stmt = $pdo->query("SELECT HEX(id) as id FROM language LIMIT 1");
    $lang = $stmt->fetch(PDO::FETCH_ASSOC);
}
$languageId = $lang['id'];
echo "Language ID: $languageId\n";

// Check if custom field set already exists
$setName = 'snigel_product_colors';
$stmt = $pdo->prepare("SELECT HEX(id) as id FROM custom_field_set WHERE name = ?");
$stmt->execute([$setName]);
$existingSet = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingSet) {
    $setId = $existingSet['id'];
    echo "Custom field set already exists: $setId\n";
} else {
    // Create custom field set
    $setId = strtoupper(bin2hex(random_bytes(16)));

    $config = json_encode([
        'label' => [
            'de-DE' => 'Snigel Farboptionen',
            'en-GB' => 'Snigel Color Options'
        ],
        'translated' => true
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO custom_field_set (id, name, config, active, position, created_at)
        VALUES (UNHEX(?), ?, ?, 1, 1, NOW())
    ");
    $stmt->execute([$setId, $setName, $config]);
    echo "Created custom field set: $setId\n";

    // Create translation
    $stmt = $pdo->prepare("
        INSERT INTO custom_field_set_translation (custom_field_set_id, language_id, label, created_at)
        VALUES (UNHEX(?), UNHEX(?), 'Snigel Farboptionen', NOW())
    ");
    $stmt->execute([$setId, $languageId]);
    echo "Created translation\n";
}

// Link to product entity
$stmt = $pdo->prepare("
    SELECT id FROM custom_field_set_relation
    WHERE custom_field_set_id = UNHEX(?) AND entity_name = 'product'
");
$stmt->execute([$setId]);
$existingRelation = $stmt->fetch();

if (!$existingRelation) {
    $relationId = strtoupper(bin2hex(random_bytes(16)));
    $stmt = $pdo->prepare("
        INSERT INTO custom_field_set_relation (id, custom_field_set_id, entity_name, created_at)
        VALUES (UNHEX(?), UNHEX(?), 'product', NOW())
    ");
    $stmt->execute([$relationId, $setId]);
    echo "Linked to product entity\n";
} else {
    echo "Already linked to product entity\n";
}

// Create custom fields
$fields = [
    [
        'name' => 'snigel_color_options',
        'type' => 'json',
        'label' => 'Farboptionen (JSON)',
        'config' => [
            'componentName' => 'sw-text-editor',
            'customFieldType' => 'textEditor',
            'customFieldPosition' => 1
        ]
    ],
    [
        'name' => 'snigel_has_color_variants',
        'type' => 'bool',
        'label' => 'Hat Farbvarianten',
        'config' => [
            'componentName' => 'sw-field',
            'type' => 'checkbox',
            'customFieldType' => 'checkbox',
            'customFieldPosition' => 2
        ]
    ]
];

foreach ($fields as $field) {
    $stmt = $pdo->prepare("SELECT HEX(id) as id FROM custom_field WHERE name = ?");
    $stmt->execute([$field['name']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo "Field '{$field['name']}' already exists\n";
        continue;
    }

    $fieldId = strtoupper(bin2hex(random_bytes(16)));
    $stmt = $pdo->prepare("
        INSERT INTO custom_field (id, name, type, config, active, custom_field_set_id, created_at)
        VALUES (UNHEX(?), ?, ?, ?, 1, UNHEX(?), NOW())
    ");
    $stmt->execute([$fieldId, $field['name'], $field['type'], json_encode($field['config']), $setId]);
    echo "Created field: {$field['name']}\n";

    // Create translation
    $stmt = $pdo->prepare("
        INSERT INTO custom_field_translation (custom_field_id, language_id, label, created_at)
        VALUES (UNHEX(?), UNHEX(?), ?, NOW())
    ");
    $stmt->execute([$fieldId, $languageId, $field['label']]);
}

echo "\n=== DONE ===\n";
echo "Custom field set 'snigel_product_colors' created!\n";
echo "Fields: snigel_color_options, snigel_has_color_variants\n";
echo "\nRun: bin/console cache:clear\n";
echo "Then check: Admin > Settings > Custom Fields\n";
