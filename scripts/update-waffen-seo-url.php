<?php
$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
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
$data = json_decode($response, true);
$token = $data['access_token'] ?? null;
curl_close($ch);

if (!$token) {
    die("Failed to authenticate\n");
}

echo "Authenticated successfully\n";

$categoryId = 'a61f19c9cb4b11f0b4074aca3d279c31';

// First, find existing SEO URL for this category
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/search/seo-url',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'filter' => [
            ['type' => 'equals', 'field' => 'foreignKey', 'value' => $categoryId],
            ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
        ]
    ]),
]);
$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);

echo "Found SEO URLs:\n";
if (!empty($result['data'])) {
    foreach ($result['data'] as $seo) {
        echo "  - " . $seo['seoPathInfo'] . " (ID: " . $seo['id'] . ", isCanonical: " . ($seo['isCanonical'] ? 'yes' : 'no') . ")\n";
    }
}

// Update or create the SEO URL
echo "\nUpdating SEO URL to 'Waffen/'...\n";

// Delete old SEO URLs first
if (!empty($result['data'])) {
    foreach ($result['data'] as $seo) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $config['shopware_url'] . '/api/seo-url/' . $seo['id'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        echo "Deleted old SEO URL: " . $seo['seoPathInfo'] . " (HTTP $httpCode)\n";
    }
}

// Get sales channel ID
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/search/sales-channel',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'limit' => 1
    ]),
]);
$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);
$salesChannelId = $result['data'][0]['id'] ?? null;
echo "Sales Channel ID: $salesChannelId\n";

// Get language ID
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/search/language',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'filter' => [
            ['type' => 'equals', 'field' => 'name', 'value' => 'Deutsch']
        ],
        'limit' => 1
    ]),
]);
$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);
$languageId = $result['data'][0]['id'] ?? '2fbb5fe2e29a4d70aa5854ce7ce3e20b'; // fallback to default
echo "Language ID: $languageId\n";

// Create new SEO URL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/seo-url',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'salesChannelId' => $salesChannelId,
        'languageId' => $languageId,
        'foreignKey' => $categoryId,
        'routeName' => 'frontend.navigation.page',
        'pathInfo' => '/navigation/' . $categoryId,
        'seoPathInfo' => 'Waffen/',
        'isCanonical' => true,
        'isModified' => true
    ]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 204 || $httpCode === 200 || $httpCode === 201) {
    echo "SUCCESS: SEO URL created as 'Waffen/'\n";
} else {
    echo "Response (HTTP $httpCode): $response\n";
}

// Now update all child categories' SEO URLs
echo "\n=== Updating child category SEO URLs ===\n";

// Get all child categories
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
        'filter' => [
            ['type' => 'contains', 'field' => 'path', 'value' => $categoryId]
        ],
        'includes' => ['category' => ['id', 'name', 'path', 'parentId']]
    ]),
]);
$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);

$childCategories = $result['data'] ?? [];
echo "Found " . count($childCategories) . " child categories\n";

// Map of old paths to new paths for SEO URLs
$pathMapping = [
    'Raven-Weapons/' => 'Waffen/',
];

foreach ($childCategories as $child) {
    $childId = $child['id'];
    $childName = $child['name'] ?? 'Unknown';

    // Get SEO URLs for this child
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . '/api/search/seo-url',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'filter' => [
                ['type' => 'equals', 'field' => 'foreignKey', 'value' => $childId],
                ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
            ]
        ]),
    ]);
    $response = curl_exec($ch);
    $seoResult = json_decode($response, true);
    curl_close($ch);

    if (!empty($seoResult['data'])) {
        foreach ($seoResult['data'] as $seo) {
            $oldPath = $seo['seoPathInfo'];
            $newPath = str_replace('Raven-Weapons/', 'Waffen/', $oldPath);

            if ($oldPath !== $newPath) {
                // Update the SEO URL
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $config['shopware_url'] . '/api/seo-url/' . $seo['id'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => 'PATCH',
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $token,
                        'Content-Type: application/json',
                    ],
                    CURLOPT_POSTFIELDS => json_encode([
                        'seoPathInfo' => $newPath
                    ]),
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 204 || $httpCode === 200) {
                    echo "Updated: $oldPath -> $newPath\n";
                } else {
                    echo "Failed to update $oldPath (HTTP $httpCode)\n";
                }
            }
        }
    }
}

echo "\n=== Done! ===\n";
echo "Don't forget to clear the cache!\n";
