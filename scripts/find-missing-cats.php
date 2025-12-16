<?php
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

// Search for specific categories
$searchTerms = ['Multicam', 'Patches', 'Source', 'Taktische Ausrüstung', 'Verschiedenes', 'Warnschutz', 'Zubehör'];

$result = apiRequest($API_URL, $token, 'POST', 'search/category', ['limit' => 500]);

echo "=== SEARCHING FOR MISSING CATEGORIES ===\n\n";

foreach ($result['data'] ?? [] as $cat) {
    $name = $cat['attributes']['name'] ?? $cat['name'] ?? '';
    $parentId = $cat['attributes']['parentId'] ?? $cat['parentId'] ?? null;
    $level = $cat['attributes']['level'] ?? $cat['level'] ?? 0;
    $id = $cat['id'];

    foreach ($searchTerms as $term) {
        if (stripos($name, $term) !== false) {
            echo "Found: $name (Level $level, ID: $id, Parent: $parentId)\n";
        }
    }
}

echo "\n=== ALL LEVEL 3 CATEGORIES ===\n\n";
foreach ($result['data'] ?? [] as $cat) {
    $name = $cat['attributes']['name'] ?? $cat['name'] ?? '';
    $level = $cat['attributes']['level'] ?? $cat['level'] ?? 0;
    $id = $cat['id'];

    if ($level == 3) {
        echo "- $name ($id)\n";
    }
}
