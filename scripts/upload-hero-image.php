<?php
/**
 * Upload Hero Image from OLD to NEW
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
    return json_decode($response, true)['access_token'] ?? null;
}

echo "═══════════════════════════════════════════════════════════\n";
echo "              UPLOAD HERO IMAGE                             \n";
echo "═══════════════════════════════════════════════════════════\n\n";

$oldToken = getToken($OLD_URL);
$newToken = getToken($NEW_URL);

if (!$oldToken || !$newToken) {
    die("❌ Failed to get tokens\n");
}

// Get all media from OLD
echo "🖼️ Searching for hero image in OLD installation...\n";

$ch = curl_init($OLD_URL . '/api/media?limit=500');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $oldToken,
        'Content-Type: application/json'
    ]
]);
$response = curl_exec($ch);
curl_close($ch);

$media = json_decode($response, true);
$heroUrl = null;
$heroFileName = null;
$heroExt = null;

if (!empty($media['data'])) {
    echo "   Found " . count($media['data']) . " media items\n";

    foreach ($media['data'] as $item) {
        $url = $item['attributes']['url'] ?? $item['url'] ?? null;
        $fileName = $item['attributes']['fileName'] ?? $item['fileName'] ?? '';

        // Look for hero/banner type images
        if (preg_match('/hero|banner|slider|header|home/i', $fileName) && $url) {
            echo "   📷 Found potential hero: {$fileName}\n";
            echo "      URL: {$url}\n";
            $heroUrl = $url;
            $heroFileName = $item['attributes']['fileName'] ?? $item['fileName'] ?? 'hero';
            $heroExt = $item['attributes']['fileExtension'] ?? $item['fileExtension'] ?? 'jpg';
            break;
        }
    }

    // If no hero found, show first few images
    if (!$heroUrl) {
        echo "\n   No 'hero' named image found. First 10 images:\n";
        $count = 0;
        foreach ($media['data'] as $item) {
            $fileName = $item['attributes']['fileName'] ?? $item['fileName'] ?? 'unknown';
            $url = $item['attributes']['url'] ?? $item['url'] ?? 'no url';
            echo "   - {$fileName}: " . substr($url, 0, 60) . "...\n";
            $count++;
            if ($count >= 10) break;
        }
    }
}

// Manually check the known hero media ID
echo "\n🔍 Checking specific media ID: de4b7dbe9d95435092cb85ce146ced28...\n";

$ch = curl_init($OLD_URL . '/api/media/de4b7dbe9d95435092cb85ce146ced28');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $oldToken,
        'Content-Type: application/json'
    ]
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Code: {$httpCode}\n";
$mediaData = json_decode($response, true);

if (!empty($mediaData['data'])) {
    echo "   Found media entry!\n";
    $data = $mediaData['data'];

    // Handle both formats
    $heroUrl = $data['attributes']['url'] ?? $data['url'] ?? null;
    $heroFileName = $data['attributes']['fileName'] ?? $data['fileName'] ?? 'hero';
    $heroExt = $data['attributes']['fileExtension'] ?? $data['fileExtension'] ?? 'jpg';

    echo "   File: {$heroFileName}.{$heroExt}\n";
    echo "   URL: {$heroUrl}\n";
} else {
    echo "   Response: " . substr($response, 0, 300) . "\n";
}

// Try to get image directly from public URL
if (!$heroUrl) {
    echo "\n🔍 Trying common hero image paths...\n";
    $possiblePaths = [
        '/media/banner.jpg',
        '/media/hero.jpg',
        '/media/slider.jpg',
        '/bundles/raventheme/assets/hero.jpg',
        '/theme/hero.jpg'
    ];

    foreach ($possiblePaths as $path) {
        $ch = curl_init($OLD_URL . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_NOBODY => true
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code == 200) {
            echo "   ✅ Found: {$OLD_URL}{$path}\n";
            $heroUrl = $OLD_URL . $path;
            $heroFileName = basename($path, '.jpg');
            $heroExt = 'jpg';
            break;
        }
    }
}

// Upload to NEW if we found an image
if ($heroUrl) {
    echo "\n📤 Uploading hero image to NEW installation...\n";

    // Create media entry
    $newMediaId = 'e95477e02ef643e5a016b83ed4cdf63a';

    $ch = curl_init($NEW_URL . '/api/media');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $newToken,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode(['id' => $newMediaId])
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300 || $httpCode == 204) {
        echo "   ✅ Media entry created\n";

        // Upload file from URL
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

        echo "   Upload response code: {$uploadCode}\n";

        if ($uploadCode >= 200 && $uploadCode < 300 || $uploadCode == 204) {
            echo "   ✅ Hero image uploaded!\n";

            // Now update the Homepage CMS slot with this media ID
            echo "\n📄 Updating Homepage CMS slot with hero image...\n";

            $slotId = 'd95477e02ef643e5a016b83ed4cdf63a';

            $ch = curl_init($NEW_URL . '/api/cms-slot/' . $slotId);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => 'PATCH',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $newToken,
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'config' => [
                        'url' => ['value' => null, 'source' => 'static'],
                        'media' => ['value' => $newMediaId, 'source' => 'static'],
                        'newTab' => ['value' => false, 'source' => 'static'],
                        'minHeight' => ['value' => '340px', 'source' => 'static'],
                        'displayMode' => ['value' => 'standard', 'source' => 'static']
                    ]
                ])
            ]);
            $patchResponse = curl_exec($ch);
            $patchCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            echo "   Slot update code: {$patchCode}\n";
            if ($patchCode >= 200 && $patchCode < 300 || $patchCode == 204) {
                echo "   ✅ Homepage CMS slot updated with hero image!\n";
            }
        } else {
            echo "   ❌ Upload failed: {$uploadResponse}\n";
        }
    } else {
        echo "   ⚠️ Failed to create media entry: {$response}\n";
    }
} else {
    echo "\n❌ No hero image found\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "                        DONE!                               \n";
echo "═══════════════════════════════════════════════════════════\n";
