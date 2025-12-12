<?php
/**
 * Check CMS layouts for category images
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

echo "Checking CMS Pages assigned to categories...\n";
echo str_repeat("=", 70) . "\n\n";

// Get categories with their CMS page
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
        'associations' => [
            'cmsPage' => [
                'associations' => [
                    'sections' => [
                        'associations' => [
                            'blocks' => [
                                'associations' => [
                                    'slots' => []
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ],
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);
$categories = $result['data'] ?? [];

$targetCategories = ['Raven Weapons', 'Raven Caliber Kit', 'Waffenzubehör'];

foreach ($categories as $cat) {
    $name = $cat['translated']['name'] ?? $cat['name'] ?? 'UNNAMED';

    if (!in_array($name, $targetCategories)) continue;

    echo "Category: $name\n";
    echo "  ID: {$cat['id']}\n";
    echo "  CMS Page ID: " . ($cat['cmsPageId'] ?? 'NONE') . "\n";

    $cmsPage = $cat['cmsPage'] ?? null;
    if ($cmsPage) {
        echo "  CMS Page Name: " . ($cmsPage['name'] ?? 'UNNAMED') . "\n";
        echo "  CMS Page Type: " . ($cmsPage['type'] ?? 'N/A') . "\n";

        $sections = $cmsPage['sections'] ?? [];
        echo "  Sections: " . count($sections) . "\n";

        foreach ($sections as $si => $section) {
            $blocks = $section['blocks'] ?? [];
            echo "    Section $si: " . count($blocks) . " blocks\n";

            foreach ($blocks as $bi => $block) {
                $blockType = $block['type'] ?? 'unknown';
                echo "      Block $bi: type=$blockType\n";

                // Check for image blocks
                if (strpos($blockType, 'image') !== false) {
                    $slots = $block['slots'] ?? [];
                    foreach ($slots as $slot) {
                        $slotType = $slot['type'] ?? 'unknown';
                        $config = $slot['config'] ?? [];
                        echo "        Slot type: $slotType\n";
                        if (!empty($config['media']['value'])) {
                            echo "        Media ID: " . $config['media']['value'] . "\n";
                        }
                    }
                }

                // Check category-navigation blocks
                if ($blockType === 'category-navigation') {
                    $slots = $block['slots'] ?? [];
                    foreach ($slots as $slot) {
                        $slotConfig = $slot['config'] ?? [];
                        echo "        Slot config: " . json_encode(array_keys($slotConfig)) . "\n";
                    }
                }
            }
        }
    }

    echo "\n";
}

// Also check for "listing" type CMS pages
echo "\n\nChecking CMS Pages of type 'product_list'...\n";
echo str_repeat("=", 70) . "\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/search/cms-page',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'limit' => 50,
        'filter' => [['type' => 'equals', 'field' => 'type', 'value' => 'product_list']],
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);
$pages = $result['data'] ?? [];

foreach ($pages as $page) {
    echo "CMS Page: " . ($page['name'] ?? 'UNNAMED') . " (ID: {$page['id']})\n";
}
