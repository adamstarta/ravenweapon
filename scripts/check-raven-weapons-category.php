<?php
/**
 * Check Raven Weapons category structure
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

echo "=== Checking Raven Weapons Category Structure ===\n\n";

// Find Raven Weapons category
$ch = curl_init($config['shopware_url'] . '/api/search/category');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'filter' => [
            ['type' => 'contains', 'field' => 'name', 'value' => 'Raven']
        ],
        'associations' => [
            'children' => [
                'associations' => [
                    'seoUrls' => []
                ]
            ],
            'seoUrls' => []
        ],
        'limit' => 50
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (!empty($result['data'])) {
    foreach ($result['data'] as $cat) {
        $name = $cat['translated']['name'] ?? $cat['name'] ?? 'Unknown';
        echo "Category: $name\n";
        echo "  ID: {$cat['id']}\n";
        echo "  Active: " . ($cat['active'] ? 'Yes' : 'No') . "\n";
        echo "  Visible: " . ($cat['visible'] ? 'Yes' : 'No') . "\n";
        echo "  Type: " . ($cat['type'] ?? 'N/A') . "\n";
        echo "  CMS Page ID: " . ($cat['cmsPageId'] ?? 'None') . "\n";

        // SEO URLs
        if (!empty($cat['seoUrls'])) {
            echo "  SEO URLs:\n";
            foreach ($cat['seoUrls'] as $seo) {
                $canonical = $seo['isCanonical'] ? ' [CANONICAL]' : '';
                echo "    - {$seo['seoPathInfo']}$canonical\n";
            }
        }

        // Children
        if (!empty($cat['children'])) {
            echo "  Children:\n";
            foreach ($cat['children'] as $child) {
                $childName = $child['translated']['name'] ?? $child['name'] ?? 'Unknown';
                echo "    - $childName (ID: {$child['id']})\n";
                echo "      Active: " . ($child['active'] ? 'Yes' : 'No') . "\n";
                echo "      Visible: " . ($child['visible'] ? 'Yes' : 'No') . "\n";
                echo "      Type: " . ($child['type'] ?? 'N/A') . "\n";
                echo "      CMS Page ID: " . ($child['cmsPageId'] ?? 'None') . "\n";

                if (!empty($child['seoUrls'])) {
                    echo "      SEO URLs:\n";
                    foreach ($child['seoUrls'] as $seo) {
                        $canonical = $seo['isCanonical'] ? ' [CANONICAL]' : '';
                        echo "        - {$seo['seoPathInfo']}$canonical\n";
                    }
                }
            }
        }
        echo "\n";
    }
} else {
    echo "No Raven categories found\n";
}

// Also check for any RAPAX category
echo "\n=== Checking RAPAX Categories ===\n\n";

$ch = curl_init($config['shopware_url'] . '/api/search/category');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'filter' => [
            ['type' => 'contains', 'field' => 'name', 'value' => 'RAPAX']
        ],
        'associations' => [
            'seoUrls' => [],
            'parent' => []
        ],
        'limit' => 50
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (!empty($result['data'])) {
    foreach ($result['data'] as $cat) {
        $name = $cat['translated']['name'] ?? $cat['name'] ?? 'Unknown';
        $parentName = $cat['parent']['translated']['name'] ?? $cat['parent']['name'] ?? 'No parent';
        echo "Category: $name\n";
        echo "  ID: {$cat['id']}\n";
        echo "  Parent: $parentName\n";
        echo "  Parent ID: " . ($cat['parentId'] ?? 'None') . "\n";
        echo "  Active: " . ($cat['active'] ? 'Yes' : 'No') . "\n";
        echo "  Visible: " . ($cat['visible'] ? 'Yes' : 'No') . "\n";
        echo "  Type: " . ($cat['type'] ?? 'N/A') . "\n";
        echo "  CMS Page ID: " . ($cat['cmsPageId'] ?? 'None') . "\n";

        if (!empty($cat['seoUrls'])) {
            echo "  SEO URLs:\n";
            foreach ($cat['seoUrls'] as $seo) {
                $canonical = $seo['isCanonical'] ? ' [CANONICAL]' : '';
                echo "    - {$seo['seoPathInfo']}$canonical\n";
            }
        }
        echo "\n";
    }
} else {
    echo "No RAPAX categories found\n";
}
