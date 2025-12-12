<?php
/**
 * CMS Content Migration Script
 * Exports CMS pages from OLD (EUR) installation and imports to NEW (CHF) installation
 */

// Configuration
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
            'Content-Type: application/json',
            'Accept: application/json'
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
            'Content-Type: application/json',
            'Accept: application/json'
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
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

echo "═══════════════════════════════════════════════════════════\n";
echo "         CMS CONTENT MIGRATION: OLD → NEW                   \n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Get tokens
echo "🔑 Getting API tokens...\n";
$oldToken = getToken($OLD_URL);
$newToken = getToken($NEW_URL);

if (!$oldToken || !$newToken) {
    die("❌ Failed to get tokens\n");
}
echo "   ✅ Tokens obtained\n\n";

// Step 1: Get CMS pages from OLD
echo "📄 Fetching CMS pages from OLD installation...\n";
$oldPages = apiGet($OLD_URL, $oldToken, 'cms-page?associations[sections][associations][blocks][associations][slots]=true&limit=100');

if (empty($oldPages['data'])) {
    die("❌ No CMS pages found in OLD installation\n");
}

echo "   Found " . count($oldPages['data']) . " CMS pages:\n";
foreach ($oldPages['data'] as $page) {
    echo "   - " . ($page['attributes']['name'] ?? 'Unnamed') . " (type: " . ($page['attributes']['type'] ?? 'unknown') . ")\n";
}
echo "\n";

// Step 2: Get CMS pages from NEW (to check what exists)
echo "📄 Checking existing CMS pages in NEW installation...\n";
$newPages = apiGet($NEW_URL, $newToken, 'cms-page?limit=100');
$existingPageIds = [];
if (!empty($newPages['data'])) {
    foreach ($newPages['data'] as $page) {
        $existingPageIds[$page['id']] = true;
        echo "   - " . ($page['attributes']['name'] ?? 'Unnamed') . " (ID: " . substr($page['id'], 0, 8) . "...)\n";
    }
}
echo "\n";

// Step 3: Get media from OLD
echo "🖼️ Fetching media from OLD installation...\n";
$oldMedia = apiGet($OLD_URL, $oldToken, 'media?limit=500');
$mediaMapping = [];

if (!empty($oldMedia['data'])) {
    echo "   Found " . count($oldMedia['data']) . " media items\n";

    // For each media, try to download and upload to NEW
    foreach ($oldMedia['data'] as $media) {
        $mediaId = $media['id'];
        $url = $media['attributes']['url'] ?? null;
        $fileName = $media['attributes']['fileName'] ?? 'unknown';

        if ($url) {
            // Store mapping (old ID -> old URL for later reference)
            $mediaMapping[$mediaId] = [
                'url' => $url,
                'fileName' => $fileName
            ];
        }
    }
}
echo "\n";

// Step 4: Export full CMS page data with all associations
echo "📦 Exporting full CMS page data...\n";
$fullExport = [];

foreach ($oldPages['data'] as $page) {
    $pageId = $page['id'];
    $pageName = $page['attributes']['name'] ?? 'Unnamed';

    // Get full page with all nested data
    $fullPage = apiGet($OLD_URL, $oldToken, "cms-page/{$pageId}?associations[sections][associations][blocks][associations][slots][associations][media]=true");

    if (!empty($fullPage['data'])) {
        $fullExport[$pageId] = $fullPage['data'];
        echo "   ✅ Exported: {$pageName}\n";
    }
}
echo "\n";

// Step 5: Get category assignments (which CMS page is assigned to which category)
echo "📂 Fetching category-CMS assignments...\n";
$categories = apiGet($OLD_URL, $oldToken, 'category?associations[cmsPage]=true&limit=500');
$categoryAssignments = [];

if (!empty($categories['data'])) {
    foreach ($categories['data'] as $cat) {
        $catName = $cat['attributes']['name'] ?? 'Unknown';
        $cmsPageId = $cat['attributes']['cmsPageId'] ?? null;
        if ($cmsPageId) {
            $categoryAssignments[$cat['id']] = $cmsPageId;
            echo "   - {$catName} → CMS Page: " . substr($cmsPageId, 0, 8) . "...\n";
        }
    }
}
echo "\n";

// Save export data to file
$exportFile = __DIR__ . '/cms-export.json';
file_put_contents($exportFile, json_encode([
    'pages' => $fullExport,
    'media' => $mediaMapping,
    'categoryAssignments' => $categoryAssignments
], JSON_PRETTY_PRINT));

echo "💾 Export saved to: {$exportFile}\n\n";

// Step 6: Summary
echo "═══════════════════════════════════════════════════════════\n";
echo "                     EXPORT SUMMARY                         \n";
echo "═══════════════════════════════════════════════════════════\n";
echo "   CMS Pages exported:        " . count($fullExport) . "\n";
echo "   Media items found:         " . count($mediaMapping) . "\n";
echo "   Category assignments:      " . count($categoryAssignments) . "\n";
echo "\n";
echo "Next step: Run cms-import.php to import to NEW installation\n";
echo "═══════════════════════════════════════════════════════════\n";
