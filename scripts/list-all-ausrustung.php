<?php
/**
 * List ALL Ausrüstung categories (flat list)
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

$token = getToken($API_URL);
if (!$token) die("Auth failed\n");

// Get all categories
$result = apiRequest($API_URL, $token, 'POST', 'search/category', ['limit' => 500]);

$categories = [];
$ausrustungId = null;

foreach ($result['data'] ?? [] as $cat) {
    $id = $cat['id'];
    $name = $cat['attributes']['name'] ?? $cat['name'] ?? 'Unknown';
    $parentId = $cat['attributes']['parentId'] ?? $cat['parentId'] ?? null;
    $level = $cat['attributes']['level'] ?? $cat['level'] ?? 0;

    $categories[$id] = [
        'id' => $id,
        'name' => $name,
        'parentId' => $parentId,
        'level' => $level
    ];

    if (stripos($name, 'Ausrüstung') !== false && $level == 2) {
        $ausrustungId = $id;
    }
}

// Function to check if category is under Ausrüstung
function isUnderAusrustung($catId, $ausrustungId, $categories, $depth = 0) {
    if ($depth > 10) return false;
    if ($catId === $ausrustungId) return true;
    $parentId = $categories[$catId]['parentId'] ?? null;
    if ($parentId && isset($categories[$parentId])) {
        return isUnderAusrustung($parentId, $ausrustungId, $categories, $depth + 1);
    }
    return false;
}

// Get SEO URLs
$seoResult = apiRequest($API_URL, $token, 'POST', 'search/seo-url', [
    'filter' => [
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page'],
        ['type' => 'equals', 'field' => 'isCanonical', 'value' => true]
    ],
    'limit' => 500
]);

$seoUrls = [];
foreach ($seoResult['data'] ?? [] as $seo) {
    $foreignKey = $seo['attributes']['foreignKey'] ?? $seo['foreignKey'] ?? null;
    $pathInfo = $seo['attributes']['seoPathInfo'] ?? $seo['seoPathInfo'] ?? '';
    if ($foreignKey) {
        $seoUrls[$foreignKey] = '/' . $pathInfo;
    }
}

echo "=== ALL AUSRÜSTUNG CATEGORIES (FOR MENU) ===\n\n";

$allAusrustung = [];
foreach ($categories as $id => $cat) {
    if ($id !== $ausrustungId && isUnderAusrustung($id, $ausrustungId, $categories)) {
        $allAusrustung[] = [
            'name' => $cat['name'],
            'level' => $cat['level'],
            'url' => $seoUrls[$id] ?? '/navigation/' . $id,
            'id' => $id
        ];
    }
}

// Sort by name
usort($allAusrustung, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

echo "Total categories: " . count($allAusrustung) . "\n\n";

// Print for menu copy-paste
echo "=== MENU HTML (Copy this) ===\n\n";
foreach ($allAusrustung as $cat) {
    // Skip parent categories (level 3 that have children)
    $hasChildren = false;
    foreach ($categories as $c) {
        if ($c['parentId'] === $cat['id']) {
            $hasChildren = true;
            break;
        }
    }

    if ($hasChildren) {
        echo "// PARENT: {$cat['name']} (has children, skip)\n";
        continue;
    }

    $url = $cat['url'];
    $name = $cat['name'];
    echo "<a href=\"{$url}\" style=\"display: flex; align-items: center; gap: 8px; padding: 6px 0; color: #6b7280; font-size: 13px; text-decoration: none;\"><span style=\"color: #9ca3af;\">↳</span> {$name}</a>\n";
}

echo "\n\n=== CATEGORY LIST ===\n";
foreach ($allAusrustung as $cat) {
    $level = $cat['level'];
    $indent = str_repeat('  ', $level - 3);
    echo "{$indent}{$cat['name']} (L{$level}) - {$cat['url']}\n";
}
