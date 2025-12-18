<?php
/**
 * List all categories to find the correct IDs
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

echo "=== Category List ===\n\n";

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "Got token\n\n";

// Get all categories with all fields
$response = apiRequest($baseUrl, $token, 'POST', '/search/category', [
    'limit' => 500
]);

if (isset($response['data'])) {
    echo "Found " . count($response['data']) . " categories\n\n";

    // Build lookup
    $byId = [];
    foreach ($response['data'] as $cat) {
        $name = $cat['translated']['name'] ?? $cat['name'] ?? 'Unknown';
        $byId[$cat['id']] = [
            'id' => $cat['id'],
            'name' => $name,
            'parentId' => $cat['parentId'] ?? null,
            'level' => $cat['level'] ?? 0,
            'path' => $cat['path'] ?? ''
        ];
    }

    // Sort by level then name
    uasort($byId, function($a, $b) {
        if ($a['level'] !== $b['level']) {
            return $a['level'] - $b['level'];
        }
        return strcmp($a['name'], $b['name']);
    });

    // Print tree
    echo "Category Tree:\n";
    echo str_repeat('-', 100) . "\n";

    foreach ($byId as $cat) {
        $indent = str_repeat('  ', $cat['level']);
        $parentName = isset($byId[$cat['parentId']]) ? $byId[$cat['parentId']]['name'] : 'ROOT';

        // Highlight categories we're interested in
        $highlight = '';
        if (stripos($cat['name'], 'Waffen') !== false ||
            stripos($cat['name'], 'RAPAX') !== false ||
            stripos($cat['name'], 'Caracal') !== false ||
            stripos($cat['name'], 'Lynx') !== false) {
            $highlight = ' <<< RELEVANT';
        }

        echo sprintf("%s[L%d] %s\n", $indent, $cat['level'], $cat['name']);
        echo sprintf("%s     ID: %s | Parent: %s%s\n\n",
            $indent,
            $cat['id'],
            $parentName,
            $highlight
        );
    }
} else {
    echo "Error response:\n";
    print_r($response);
}
