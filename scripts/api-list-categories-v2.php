<?php
/**
 * List all categories via API - corrected for JSON:API format
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

echo "Total categories: " . count($categories) . "\n\n";

// Categories we want to find
$targetNames = ['Raven Weapons', 'Raven Caliber Kit', 'Waffenzubehör'];
$foundTargets = [];

echo "Active categories:\n";
echo str_repeat("=", 100) . "\n";

foreach ($categories as $cat) {
    // Handle JSON:API format
    $attrs = $cat['attributes'] ?? $cat;
    $active = $attrs['active'] ?? false;

    if (!$active) continue;

    $name = $attrs['translated']['name'] ?? $attrs['name'] ?? '(no name)';
    $level = $attrs['level'] ?? 0;
    $mediaId = $attrs['mediaId'] ?? 'NONE';
    $cmsPageId = $attrs['cmsPageId'] ?? 'NONE';
    $id = $cat['id'] ?? 'N/A';

    // Check if target
    $isTarget = in_array($name, $targetNames);
    if ($isTarget) {
        $foundTargets[$name] = [
            'id' => $id,
            'mediaId' => $mediaId,
            'cmsPageId' => $cmsPageId,
        ];
    }

    $marker = $isTarget ? '>>>' : '   ';
    echo sprintf("%s L%d | %-40s | media: %-10s | cms: %s\n",
        $marker,
        $level,
        $name,
        $mediaId ?: 'NONE',
        $cmsPageId ?: 'NONE'
    );
}

echo "\n\nTarget Categories Found:\n";
echo str_repeat("=", 100) . "\n";
foreach ($targetNames as $target) {
    if (isset($foundTargets[$target])) {
        $info = $foundTargets[$target];
        echo "$target:\n";
        echo "  ID: {$info['id']}\n";
        echo "  mediaId: {$info['mediaId']}\n";
        echo "  cmsPageId: {$info['cmsPageId']}\n\n";
    } else {
        echo "$target: NOT FOUND\n\n";
    }
}
