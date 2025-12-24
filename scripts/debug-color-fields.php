<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=shopware;charset=utf8mb4', 'root', 'root');

$stmt = $pdo->query("
    SELECT LOWER(HEX(p.id)) as id, p.product_number, pt.custom_fields
    FROM product p
    JOIN product_translation pt ON p.id = pt.product_id
    WHERE p.parent_id IS NULL
    AND pt.custom_fields LIKE '%snigel_color_options%'
    LIMIT 3
");

$whitelist = ['Black', 'Grey', 'Olive', 'Multicam', 'Navy', 'Coyote', 'Khaki', 'White', 'Clear', 'Swecam', 'HighVis yellow', 'Ranger Green', 'Various'];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Product: " . $row['product_number'] . "\n";

    $cf = json_decode($row['custom_fields'], true);
    echo "  Parsed custom_fields: " . (is_array($cf) ? "YES" : "NO") . "\n";

    $colors = $cf['snigel_color_options'] ?? [];
    echo "  snigel_color_options type: " . gettype($colors) . "\n";

    if (is_array($colors)) {
        echo "  Colors found:\n";
        foreach ($colors as $c) {
            $name = is_array($c) && isset($c['name']) ? $c['name'] : (is_string($c) ? $c : 'unknown');
            $inWhitelist = in_array($name, $whitelist) ? "YES" : "NO";
            echo "    - $name (in whitelist: $inWhitelist)\n";
        }
    } else {
        echo "  Colors is NOT array, raw: " . substr(print_r($colors, true), 0, 200) . "\n";
    }
    echo "\n";
}
