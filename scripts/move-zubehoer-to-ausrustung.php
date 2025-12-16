<?php
/**
 * Move 6 categories from Zubehör to Ausrüstung
 * - Taktische Ausrüstung
 * - Patches
 * - Source Hydration
 * - Verschiedenes
 * - Multicam
 * - Warnschutz
 */

$API_URL = 'https://ortak.ch';

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'client_credentials',
            'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
            'client_secret' => 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

echo "=== MOVING 6 CATEGORIES FROM ZUBEHÖR TO AUSRÜSTUNG ===\n\n";

$token = getToken($API_URL);
if (!$token) die("Auth failed\n");
echo "✓ Authenticated\n\n";

// Categories to move
$categoriesToMove = [
    'Taktische Ausrüstung',
    'Patches',
    'Source Hydration',
    'Verschiedenes',
    'Multicam',
    'Warnschutz'
];

// Get all categories
$result = apiRequest($API_URL, $token, 'POST', 'search/category', ['limit' => 500]);

$ausrustungId = null;
$zubehoerId = null;
$categoryIds = [];

foreach ($result['data'] ?? [] as $cat) {
    $id = $cat['id'];
    $name = $cat['attributes']['name'] ?? $cat['name'] ?? '';
    $level = $cat['attributes']['level'] ?? $cat['level'] ?? 0;
    $parentId = $cat['attributes']['parentId'] ?? $cat['parentId'] ?? null;

    // Find Ausrüstung (level 2)
    if ($name === 'Ausrüstung' && $level == 2) {
        $ausrustungId = $id;
        echo "Found Ausrüstung: $id\n";
    }

    // Find Zubehör (level 2)
    if ($name === 'Zubehör' && $level == 2) {
        $zubehoerId = $id;
        echo "Found Zubehör: $id\n";
    }

    // Find categories to move
    if (in_array($name, $categoriesToMove)) {
        $categoryIds[$name] = [
            'id' => $id,
            'currentParent' => $parentId
        ];
    }
}

if (!$ausrustungId) die("ERROR: Ausrüstung not found!\n");
if (!$zubehoerId) die("ERROR: Zubehör not found!\n");

echo "\n=== MOVING CATEGORIES ===\n\n";

foreach ($categoriesToMove as $catName) {
    if (!isset($categoryIds[$catName])) {
        echo "WARNING: '$catName' not found, skipping\n";
        continue;
    }

    $catId = $categoryIds[$catName]['id'];
    $currentParent = $categoryIds[$catName]['currentParent'];

    if ($currentParent === $ausrustungId) {
        echo "- $catName: Already under Ausrüstung, skipping\n";
        continue;
    }

    echo "Moving: $catName ($catId) from $currentParent to $ausrustungId\n";

    $response = apiRequest($API_URL, $token, 'PATCH', "category/$catId", [
        'parentId' => $ausrustungId
    ]);

    if (isset($response['errors'])) {
        echo "  ERROR: " . json_encode($response['errors']) . "\n";
    } else {
        echo "  ✓ Moved successfully\n";
    }

    usleep(100000);
}

// Clear cache
echo "\n=== CLEARING CACHE ===\n";
apiRequest($API_URL, $token, 'DELETE', '_action/cache');
echo "✓ Cache cleared\n";

echo "\n=== DONE ===\n";
echo "Now all 21 Snigel categories should be under Ausrüstung\n";
