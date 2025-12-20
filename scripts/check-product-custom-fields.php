<?php
require_once __DIR__ . '/shopware-api.php';

$api = new ShopwareAPI();

// Search for coverall product
$response = $api->request('POST', '/api/search/product', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'coverall']
    ],
    'associations' => [
        'customFields' => []
    ],
    'limit' => 1
]);

if (!empty($response['data'])) {
    $product = $response['data'][0];
    echo "=== Product: " . $product['name'] . " ===\n\n";
    echo "ID: " . $product['id'] . "\n";
    echo "Product Number: " . $product['productNumber'] . "\n\n";
    
    echo "=== Custom Fields ===\n";
    if (isset($product['customFields']) && is_array($product['customFields'])) {
        foreach ($product['customFields'] as $key => $value) {
            if (strpos($key, 'snigel') !== false) {
                echo "$key:\n";
                if (is_array($value) || is_object($value)) {
                    echo json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                } else {
                    echo "  " . substr($value, 0, 500) . "\n";
                }
            }
        }
    } else {
        echo "No custom fields found\n";
    }
}
