<?php
/**
 * Check navigation tree via Store API to see actual structure
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
];

echo "=== Checking Store API Navigation Tree ===\n\n";

// Get navigation tree from Store API
$salesChannelId = '0191c12dd4b970949e9aeec40433be3e';
$navigationId = '0191c12ccf00712e8c0cf733425fe315'; // Root category

$ch = curl_init($config['shopware_url'] . '/store-api/navigation/' . $navigationId . '/' . $navigationId);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'sw-access-key: SWSCWWRXRLLXNWHNB0F2NJNIUG', // Store API access key
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'depth' => 5,
        'buildTree' => true,
    ]),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $httpCode\n\n";

$data = json_decode($response, true);

if (empty($data)) {
    echo "No data returned\n";
    echo "Response: " . substr($response, 0, 500) . "\n";
    exit;
}

function printTree($items, $level = 0) {
    foreach ($items as $item) {
        $indent = str_repeat('  ', $level);
        $name = $item['name'] ?? $item['translated']['name'] ?? 'Unknown';
        $id = $item['id'] ?? 'N/A';
        $childCount = isset($item['children']) ? count($item['children']) : 0;

        echo "$indent- $name (ID: " . substr($id, 0, 8) . "..., Children: $childCount)\n";

        if (!empty($item['children'])) {
            printTree($item['children'], $level + 1);
        }
    }
}

echo "Navigation Tree:\n";
printTree($data);

echo "\n=== Done ===\n";
