<?php
/**
 * List all categories with their parent hierarchy
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

// Get OAuth token
$ch = curl_init($config['shopware_url'] . '/api/oauth/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'grant_type' => 'password',
        'client_id' => 'administration',
        'username' => $config['api_user'],
        'password' => $config['api_password'],
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
]);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);
$token = $data['access_token'];

echo "=== All Categories with Navigation ===\n\n";

// Get all categories
$ch = curl_init($config['shopware_url'] . '/api/search/category');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'associations' => [
            'seoUrls' => []
        ],
        'limit' => 500
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (!empty($result['data'])) {
    // Build parent lookup
    $categories = [];
    foreach ($result['data'] as $cat) {
        $categories[$cat['id']] = $cat;
    }

    // Print categories
    foreach ($result['data'] as $cat) {
        $name = $cat['translated']['name'] ?? $cat['name'] ?? 'Unknown';

        // Get canonical SEO URL
        $seoUrl = 'N/A';
        if (!empty($cat['seoUrls'])) {
            foreach ($cat['seoUrls'] as $seo) {
                if (!empty($seo['isCanonical'])) {
                    $seoUrl = $seo['seoPathInfo'];
                    break;
                }
            }
            if ($seoUrl === 'N/A' && !empty($cat['seoUrls'][0])) {
                $seoUrl = $cat['seoUrls'][0]['seoPathInfo'];
            }
        }

        // Get parent path
        $parentPath = '';
        $parentId = $cat['parentId'] ?? null;
        $depth = 0;
        while ($parentId && $depth < 5) {
            if (isset($categories[$parentId])) {
                $parentName = $categories[$parentId]['translated']['name'] ?? $categories[$parentId]['name'] ?? '?';
                $parentPath = $parentName . ' > ' . $parentPath;
                $parentId = $categories[$parentId]['parentId'] ?? null;
            } else {
                break;
            }
            $depth++;
        }

        $fullPath = $parentPath . $name;

        echo "Path: $fullPath\n";
        echo "  ID: {$cat['id']}\n";
        echo "  SEO URL: $seoUrl\n";
        echo "  Active: " . (($cat['active'] ?? false) ? 'Yes' : 'No') . "\n";
        echo "  Visible: " . (($cat['visible'] ?? false) ? 'Yes' : 'No') . "\n";
        echo "\n";
    }

    echo "Total: " . count($result['data']) . " categories\n";
} else {
    echo "No categories found\n";
}
