<?php
/**
 * Check Snigel parent category
 */

$API_URL = 'http://localhost';

$ch = curl_init($API_URL . '/api/oauth/token');
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
$token = json_decode($response, true)['access_token'] ?? null;

if (!$token) die("No token\n");

// Search for categories containing "Snigel" or all top-level categories
$ch = curl_init($API_URL . '/api/search/category');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'limit' => 100,
        'includes' => ['category' => ['id', 'name', 'parentId', 'level']]
    ])
]);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);

echo "All categories:\n";
foreach ($data['data'] ?? [] as $cat) {
    $name = $cat['name'] ?? $cat['attributes']['name'] ?? 'NO NAME';
    $parentId = $cat['parentId'] ?? $cat['attributes']['parentId'] ?? 'root';
    $level = $cat['level'] ?? $cat['attributes']['level'] ?? '?';
    echo "  [{$cat['id']}] $name (level: $level, parent: $parentId)\n";
}
