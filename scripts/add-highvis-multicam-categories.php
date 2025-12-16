<?php
/**
 * Add HighVis and Multicam categories to Snigel
 * Run: docker exec shopware-chf php /tmp/add-highvis-multicam-categories.php
 */

$API_URL = 'http://localhost';

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
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

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

echo "\n=== Adding HighVis and Multicam Categories ===\n\n";

$token = getToken($API_URL);
if (!$token) die("Error: Failed to get token\n");

// Snigel parent ID (level 2, parent of all subcategories)
$snigelParentId = '019b0857613474e6a799cfa07d143c76';
echo "Using Snigel parent: $snigelParentId\n\n";

// Create HighVis and Multicam
$categories = ['HighVis', 'Multicam'];

foreach ($categories as $catName) {
    // Check if exists
    $result = apiRequest($API_URL, $token, 'POST', 'search/category', [
        'filter' => [
            ['type' => 'equals', 'field' => 'name', 'value' => $catName],
            ['type' => 'equals', 'field' => 'parentId', 'value' => $snigelParentId]
        ]
    ]);

    if (!empty($result['data']['data'])) {
        echo "EXISTS: $catName\n";
        continue;
    }

    // Create
    $newId = bin2hex(random_bytes(16));
    $createResult = apiRequest($API_URL, $token, 'POST', 'category', [
        'id' => $newId,
        'name' => $catName,
        'parentId' => $snigelParentId,
        'active' => true,
        'displayNestedProducts' => true,
        'type' => 'page'
    ]);

    if ($createResult['code'] >= 200 && $createResult['code'] < 300) {
        echo "CREATED: $catName\n";
    } else {
        echo "FAILED: $catName\n";
    }
}

echo "\nDone! Now 21 categories total.\n";
