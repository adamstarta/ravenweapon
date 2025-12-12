<?php
/**
 * List all categories via API with full details
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

if (!$token) die("Auth failed\n");

// Get categories with includes
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
        'includes' => [
            'category' => ['id', 'name', 'active', 'level', 'cmsPageId', 'mediaId', 'translated']
        ]
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);
$categories = $result['data'] ?? [];

echo "Total categories: " . count($categories) . "\n\n";
echo "Active categories:\n";
echo str_repeat("=", 100) . "\n";

foreach ($categories as $cat) {
    if (!($cat['active'] ?? false)) continue;

    $name = '';
    // Try different ways to get the name
    if (!empty($cat['translated']['name'])) {
        $name = $cat['translated']['name'];
    } elseif (!empty($cat['name'])) {
        $name = $cat['name'];
    }

    $level = $cat['level'] ?? 0;
    $cmsPageId = $cat['cmsPageId'] ?? 'NONE';
    $mediaId = $cat['mediaId'] ?? 'NONE';

    echo sprintf("L%d | %-40s | media: %s\n",
        $level,
        $name ?: '(no name)',
        $mediaId
    );
}

echo "\n\nRAW first category:\n";
echo str_repeat("=", 100) . "\n";
print_r($categories[0] ?? 'none');
