<?php
/**
 * Remove banner image from "Alle Produkte" category
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

echo "===========================================\n";
echo "  REMOVE ALLE PRODUKTE BANNER IMAGE\n";
echo "===========================================\n\n";

$token = getToken($API_URL);
if (!$token) {
    die("Error: Failed to get API token\n");
}
echo "Got API token\n\n";

// Find "Alle Produkte" category
$result = apiRequest($API_URL, $token, 'POST', 'search/category', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'Alle Produkte']
    ]
]);

$categoryId = null;
$categoryName = null;

foreach ($result['data']['data'] ?? [] as $cat) {
    $name = $cat['name'] ?? $cat['attributes']['name'] ?? '';
    if (stripos($name, 'alle produkte') !== false) {
        $categoryId = $cat['id'];
        $categoryName = $name;
        break;
    }
}

if (!$categoryId) {
    die("Error: Could not find 'Alle Produkte' category\n");
}

echo "Found category: $categoryName (ID: $categoryId)\n\n";

// Remove the media (set mediaId to null)
$updateResult = apiRequest($API_URL, $token, 'PATCH', "category/$categoryId", [
    'mediaId' => null
]);

if ($updateResult['code'] >= 200 && $updateResult['code'] < 300) {
    echo "SUCCESS: Removed banner image from '$categoryName'\n";
} else {
    echo "ERROR: Failed to remove image\n";
    print_r($updateResult['data']);
}

echo "\n===========================================\n";
echo "Done! Refresh the page to see the change.\n";
echo "===========================================\n";
