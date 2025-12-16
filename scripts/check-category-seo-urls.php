<?php
/**
 * Check SEO URLs for categories
 */

$config = [
    'base_url' => 'https://ortak.ch',
    'client_id' => 'SWIARAVEN03399CEA2C931269',
    'client_secret' => 'RavenNavbarUpdate2025!'
];

function getAccessToken($config) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['base_url'] . '/api/oauth/token',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'client_credentials',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret']
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function apiRequest($method, $endpoint, $data, $token, $config) {
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $config['base_url'] . '/api' . $endpoint,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ];
    if ($data !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$token = getAccessToken($config);
if (!$token) die("Failed to get token\n");

// Get categories under Zubehör
echo "=== Checking SEO URLs for Zubehör subcategories ===\n\n";

// First find the Zubehör category
$result = apiRequest('POST', '/search/category', [
    'filter' => [
        ['type' => 'contains', 'field' => 'name', 'value' => 'Zubehör']
    ],
    'limit' => 20
], $token, $config);

echo "Found categories with 'Zubehör':\n";
foreach ($result['data'] ?? [] as $cat) {
    echo "  - {$cat['name']} (ID: {$cat['id']})\n";
}

// Get Magazine category specifically
$magazineCategoryId = '00a19869155b4c0d9508dfcfeeaf93d7';
echo "\n=== Magazine Category SEO URLs ===\n";

$seoResult = apiRequest('POST', '/search/seo-url', [
    'filter' => [
        ['type' => 'equals', 'field' => 'foreignKey', 'value' => $magazineCategoryId]
    ],
    'limit' => 10
], $token, $config);

echo "SEO URLs for Magazine category:\n";
foreach ($seoResult['data'] ?? [] as $seo) {
    echo "  - Path: {$seo['seoPathInfo']}\n";
    echo "    Is Canonical: " . ($seo['isCanonical'] ? 'Yes' : 'No') . "\n";
    echo "    Is Deleted: " . ($seo['isDeleted'] ? 'Yes' : 'No') . "\n";
}

if (empty($seoResult['data'])) {
    echo "  No SEO URLs found!\n";
}

// Check all category SEO URLs
echo "\n=== All Category SEO URLs ===\n";

$allSeoResult = apiRequest('POST', '/search/seo-url', [
    'filter' => [
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
    ],
    'limit' => 50
], $token, $config);

foreach ($allSeoResult['data'] ?? [] as $seo) {
    $canonical = $seo['isCanonical'] ? '[CANONICAL]' : '';
    echo "  {$seo['seoPathInfo']} $canonical\n";
}

echo "\nTotal: " . ($allSeoResult['total'] ?? 0) . " category SEO URLs\n";
