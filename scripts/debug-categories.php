<?php
/**
 * Debug categories - find exact names
 */

$config = [
    'shopware_url' => 'http://localhost',
    'api_user' => 'admin',
    'api_password' => 'shopware',
];

// Get token
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/oauth/token',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'grant_type' => 'password',
        'client_id' => 'administration',
        'username' => $config['api_user'],
        'password' => $config['api_password'],
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);
$token = $data['access_token'] ?? null;

// Get categories
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/search/category',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'limit' => 200,
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);
$categories = $result['data'] ?? [];

echo "All active categories (level 2 = main nav):\n";
echo str_repeat("=", 80) . "\n";

foreach ($categories as $cat) {
    $name = $cat['translated']['name'] ?? $cat['name'] ?? 'UNNAMED';
    $active = $cat['active'] ?? false;
    $level = $cat['level'] ?? 0;
    $cmsPageId = $cat['cmsPageId'] ?? 'NONE';

    if ($active) {
        echo sprintf("L%d | %-40s | CMS: %s\n", $level, $name, $cmsPageId);
    }
}

echo "\n\nSearching for 'Raven' or 'Waffen' in category names:\n";
echo str_repeat("=", 80) . "\n";

foreach ($categories as $cat) {
    $name = $cat['translated']['name'] ?? $cat['name'] ?? 'UNNAMED';

    if (stripos($name, 'raven') !== false || stripos($name, 'waffen') !== false) {
        echo "Name: $name\n";
        echo "  ID: {$cat['id']}\n";
        echo "  Active: " . ($cat['active'] ? 'YES' : 'NO') . "\n";
        echo "  Level: " . ($cat['level'] ?? 'N/A') . "\n";
        echo "  CMS Page: " . ($cat['cmsPageId'] ?? 'NONE') . "\n";
        echo "\n";
    }
}
