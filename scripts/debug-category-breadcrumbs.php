<?php
/**
 * Debug category seoBreadcrumb structure
 */

$baseUrl = 'https://ortak.ch/api';
$clientId = 'SWIAC3HJVHFJMHQYRWRUM1E1SG';
$clientSecret = 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg';

function getAccessToken($baseUrl, $clientId, $clientSecret) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/oauth/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['access_token'];
}

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    return json_decode($response, true);
}

echo "=== Category SeoBreadcrumb Debug ===\n\n";

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "Got token\n\n";

// Get all categories
$response = apiRequest($baseUrl, $token, 'POST', '/search/category', [
    'limit' => 500
]);

if (!isset($response['data'])) {
    echo "Error:\n";
    print_r($response);
    exit(1);
}

$categories = $response['data'];
echo "Found " . count($categories) . " categories\n\n";

// Build lookup by ID
$byId = [];
foreach ($categories as $cat) {
    $attrs = $cat['attributes'] ?? $cat;
    $name = $attrs['translated']['name'] ?? $attrs['name'] ?? 'Unknown';
    $seoBreadcrumb = $attrs['translated']['breadcrumb'] ?? $attrs['breadcrumb'] ?? [];

    $byId[$cat['id']] = [
        'id' => $cat['id'],
        'name' => $name,
        'parentId' => $attrs['parentId'] ?? null,
        'level' => $attrs['level'] ?? 0,
        'path' => $attrs['path'] ?? '',
        'seoBreadcrumb' => $seoBreadcrumb
    ];
}

// Find and analyze Ausrüstung subcategories
echo "=== Ausrüstung Category Tree ===\n\n";

// First find the main Ausrüstung category
$ausrustungId = null;
foreach ($byId as $id => $cat) {
    if ($cat['name'] === 'Ausrüstung' && $cat['level'] == 2) {
        $ausrustungId = $id;
        echo "Main Ausrüstung (Level 2):\n";
        echo "  ID: {$cat['id']}\n";
        echo "  Path: {$cat['path']}\n";
        echo "  SeoBreadcrumb: " . json_encode($cat['seoBreadcrumb']) . "\n\n";
        break;
    }
}

// Find all categories under Ausrüstung tree
function getDescendants($parentId, $byId, $depth = 0) {
    $results = [];
    foreach ($byId as $id => $cat) {
        if ($cat['parentId'] === $parentId) {
            $results[$id] = $cat;
            $results[$id]['_depth'] = $depth;
            $results = array_merge($results, getDescendants($id, $byId, $depth + 1));
        }
    }
    return $results;
}

if ($ausrustungId) {
    $descendants = getDescendants($ausrustungId, $byId);

    echo "Ausrüstung subcategories:\n";
    echo str_repeat('-', 100) . "\n";

    foreach ($descendants as $id => $cat) {
        $indent = str_repeat('  ', $cat['_depth']);

        echo "{$indent}[L{$cat['level']}] {$cat['name']}\n";
        echo "{$indent}  ID: {$cat['id']}\n";
        echo "{$indent}  ParentID: {$cat['parentId']}\n";
        echo "{$indent}  Path: {$cat['path']}\n";
        echo "{$indent}  SeoBreadcrumb: " . json_encode($cat['seoBreadcrumb']) . "\n";

        // Calculate what the SEO URL should be based on template
        // Template: {% for part in category.seoBreadcrumb|slice(1) %}{{ part|lower|replace(...) }}/{% endfor %}
        if (!empty($cat['seoBreadcrumb'])) {
            $sliced = array_slice($cat['seoBreadcrumb'], 1);
            $seoUrl = '';
            foreach ($sliced as $part) {
                $slug = strtolower(str_replace([' ', '/', '&', 'ä', 'ö', 'ü', 'ß'], ['-', '-', '', 'ae', 'oe', 'ue', 'ss'], $part));
                $slug = preg_replace('/-+/', '-', $slug);
                $slug = trim($slug, '-');
                $seoUrl .= $slug . '/';
            }
            echo "{$indent}  Expected SEO URL: /{$seoUrl}\n";
        }
        echo "\n";
    }
}

// Also check Körperschutz specifically
echo "\n=== Direct check for Körperschutz ===\n";
foreach ($byId as $id => $cat) {
    if (stripos($cat['name'], 'Körperschutz') !== false || stripos($cat['name'], 'Koerperschutz') !== false) {
        echo "Category: {$cat['name']}\n";
        echo "  ID: {$cat['id']}\n";
        echo "  Level: {$cat['level']}\n";
        echo "  ParentID: {$cat['parentId']}\n";
        echo "  Path: {$cat['path']}\n";
        echo "  SeoBreadcrumb: " . json_encode($cat['seoBreadcrumb']) . "\n";

        // Get parent chain
        echo "  Parent chain:\n";
        $parentId = $cat['parentId'];
        while ($parentId && isset($byId[$parentId])) {
            $parent = $byId[$parentId];
            echo "    -> {$parent['name']} (ID: {$parent['id']}, Level: {$parent['level']})\n";
            $parentId = $parent['parentId'];
        }
        echo "\n";
    }
}
