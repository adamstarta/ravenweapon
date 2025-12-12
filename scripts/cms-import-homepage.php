<?php
/**
 * Import Homepage CMS from OLD to NEW installation
 */

$OLD_URL = 'https://ortak.ch';
$NEW_URL = 'http://77.42.19.154:8080';

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => 'admin',
            'password' => 'shopware'
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function apiGet($baseUrl, $token, $endpoint) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function apiPost($baseUrl, $token, $endpoint, $data) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

function apiPatch($baseUrl, $token, $endpoint, $data) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

echo "═══════════════════════════════════════════════════════════\n";
echo "      HOMEPAGE CMS IMPORT: OLD → NEW                        \n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Get tokens
echo "🔑 Getting API tokens...\n";
$oldToken = getToken($OLD_URL);
$newToken = getToken($NEW_URL);

if (!$oldToken || !$newToken) {
    die("❌ Failed to get tokens\n");
}
echo "   ✅ Tokens obtained\n\n";

// Step 1: Get hero image media from OLD
$heroMediaId = 'de4b7dbe9d95435092cb85ce146ced28';
echo "🖼️ Getting hero image from OLD installation...\n";
$heroMedia = apiGet($OLD_URL, $oldToken, "media/{$heroMediaId}");

if (empty($heroMedia['data'])) {
    echo "   ⚠️ Hero media not found, will proceed without it\n";
    $heroUrl = null;
} else {
    $heroUrl = $heroMedia['data']['attributes']['url'] ?? $heroMedia['data']['url'] ?? null;
    $heroFileName = $heroMedia['data']['attributes']['fileName'] ?? $heroMedia['data']['fileName'] ?? 'hero';
    $heroExt = $heroMedia['data']['attributes']['fileExtension'] ?? $heroMedia['data']['fileExtension'] ?? 'jpg';
    echo "   Found: {$heroFileName}.{$heroExt}\n";
    echo "   URL: {$heroUrl}\n\n";
}

// Step 2: Upload hero image to NEW installation
if ($heroUrl) {
    echo "📤 Uploading hero image to NEW installation...\n";

    // Create media folder first
    $mediaFolders = apiGet($NEW_URL, $newToken, 'media-folder?filter[name]=Homepage%20Media');

    // Get or create default folder ID
    $defaultFolders = apiGet($NEW_URL, $newToken, 'media-default-folder?filter[entity]=cms_page');
    $folderId = null;
    if (!empty($defaultFolders['data'][0]['attributes']['folderId'])) {
        $folderId = $defaultFolders['data'][0]['attributes']['folderId'];
    }

    // Create media entry in NEW
    $newMediaId = bin2hex(random_bytes(16));
    $mediaData = [
        'id' => $newMediaId,
        'mediaFolderId' => $folderId
    ];

    $createMedia = apiPost($NEW_URL, $newToken, 'media', $mediaData);

    if ($createMedia['code'] >= 200 && $createMedia['code'] < 300) {
        echo "   ✅ Media entry created: {$newMediaId}\n";

        // Upload the actual file
        $uploadUrl = $NEW_URL . '/api/_action/media/' . $newMediaId . '/upload?extension=' . $heroExt . '&fileName=' . urlencode($heroFileName);

        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $newToken,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode(['url' => $heroUrl])
        ]);
        $uploadResponse = curl_exec($ch);
        $uploadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($uploadCode >= 200 && $uploadCode < 300) {
            echo "   ✅ Hero image uploaded successfully\n\n";
        } else {
            echo "   ⚠️ Upload returned code: {$uploadCode}\n";
            echo "   Response: " . substr($uploadResponse, 0, 200) . "\n\n";
            // Still continue with OLD media ID
            $newMediaId = $heroMediaId;
        }
    } else {
        echo "   ⚠️ Failed to create media entry, using original ID\n";
        $newMediaId = $heroMediaId;
    }
} else {
    $newMediaId = null;
}

// Step 3: Create Homepage CMS page in NEW
echo "📄 Creating Homepage CMS page in NEW installation...\n";

// Generate new UUIDs
$pageId = 'a95477e02ef643e5a016b83ed4cdf63a'; // New homepage ID
$sectionId = 'b95477e02ef643e5a016b83ed4cdf63a';
$blockId = 'c95477e02ef643e5a016b83ed4cdf63a';
$slotId = 'd95477e02ef643e5a016b83ed4cdf63a';

$homepageData = [
    'id' => $pageId,
    'name' => 'Homepage',
    'type' => 'landingpage',
    'sections' => [
        [
            'id' => $sectionId,
            'type' => 'default',
            'position' => 1,
            'sizingMode' => 'boxed',
            'mobileBehavior' => 'wrap',
            'blocks' => [
                [
                    'id' => $blockId,
                    'type' => 'image-cover',
                    'position' => 0,
                    'sectionPosition' => 'main',
                    'slots' => [
                        [
                            'id' => $slotId,
                            'type' => 'image',
                            'slot' => 'image',
                            'config' => [
                                'url' => ['value' => null, 'source' => 'static'],
                                'media' => ['value' => $newMediaId, 'source' => 'static'],
                                'newTab' => ['value' => false, 'source' => 'static'],
                                'minHeight' => ['value' => '340px', 'source' => 'static'],
                                'displayMode' => ['value' => 'standard', 'source' => 'static']
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ]
];

$createPage = apiPost($NEW_URL, $newToken, 'cms-page', $homepageData);

if ($createPage['code'] >= 200 && $createPage['code'] < 300) {
    echo "   ✅ Homepage CMS page created: {$pageId}\n\n";
} else {
    echo "   ❌ Failed to create page. Code: {$createPage['code']}\n";
    echo "   Error: " . json_encode($createPage['data'] ?? 'Unknown error') . "\n\n";

    // Try to update existing
    if (strpos(json_encode($createPage['data']), 'already exists') !== false ||
        strpos(json_encode($createPage['data']), 'Duplicate') !== false) {
        echo "   Trying to update existing page...\n";
        $updatePage = apiPatch($NEW_URL, $newToken, "cms-page/{$pageId}", $homepageData);
        if ($updatePage['code'] >= 200 && $updatePage['code'] < 300) {
            echo "   ✅ Homepage updated successfully\n\n";
        }
    }
}

// Step 4: Assign Homepage to Sales Channel
echo "🏪 Assigning Homepage to Sales Channel...\n";

// Get Sales Channel
$salesChannels = apiGet($NEW_URL, $newToken, 'sales-channel');
$salesChannelId = null;

if (!empty($salesChannels['data'])) {
    foreach ($salesChannels['data'] as $sc) {
        $scName = $sc['attributes']['name'] ?? $sc['name'] ?? '';
        if (stripos($scName, 'Storefront') !== false) {
            $salesChannelId = $sc['id'];
            break;
        }
    }
    if (!$salesChannelId) {
        $salesChannelId = $salesChannels['data'][0]['id'];
    }
}

if ($salesChannelId) {
    echo "   Found Sales Channel: {$salesChannelId}\n";

    // Update Sales Channel with homepage
    $scUpdate = apiPatch($NEW_URL, $newToken, "sales-channel/{$salesChannelId}", [
        'homeCmsPageId' => $pageId
    ]);

    if ($scUpdate['code'] >= 200 && $scUpdate['code'] < 300) {
        echo "   ✅ Homepage assigned to Sales Channel\n\n";
    } else {
        echo "   ⚠️ Failed to assign homepage: " . json_encode($scUpdate['data']) . "\n\n";
    }
}

// Step 5: Clear cache
echo "🧹 Clearing cache...\n";
$clearCache = apiPost($NEW_URL, $newToken, '_action/cache', []);
echo "   Cache clear requested\n\n";

echo "═══════════════════════════════════════════════════════════\n";
echo "                   IMPORT COMPLETE!                         \n";
echo "═══════════════════════════════════════════════════════════\n";
echo "   Homepage ID: {$pageId}\n";
echo "   Hero Media ID: " . ($newMediaId ?? 'None') . "\n";
echo "\n";
echo "   Visit: http://new.ortak.ch:8080/ to see the homepage\n";
echo "═══════════════════════════════════════════════════════════\n";
