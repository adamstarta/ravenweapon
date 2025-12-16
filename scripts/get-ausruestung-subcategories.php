<?php
/**
 * Get all Ausrüstung subcategories for navigation
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
if (!$token) {
    die("Failed to get token\n");
}

// Find Ausrüstung category
$result = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => 'Ausrüstung']],
    'includes' => ['category' => ['id', 'name']]
], $token, $config);

$ausruestung = $result['data'][0] ?? null;
if (!$ausruestung) {
    die("Ausrüstung not found\n");
}

echo "Ausrüstung ID: " . $ausruestung['id'] . "\n\n";

// Get all subcategories
$subResult = apiRequest('POST', '/search/category', [
    'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $ausruestung['id']]],
    'includes' => ['category' => ['id', 'name', 'active']],
    'sort' => [['field' => 'name', 'order' => 'ASC']],
    'limit' => 50
], $token, $config);

echo "=== Ausrüstung Subcategories ===\n\n";

$subcategories = [];
foreach ($subResult['data'] ?? [] as $cat) {
    $name = $cat['name'];
    $active = $cat['active'] ? 'active' : 'inactive';

    // Generate SEO URL friendly name
    $urlName = str_replace(
        [' & ', ' ', 'ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'],
        ['-', '-', 'ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'],
        $name
    );
    $urlName = preg_replace('/[^a-zA-Z0-9-]/', '', $urlName);
    $urlName = preg_replace('/-+/', '-', $urlName);

    echo "- $name ($active)\n";
    echo "  URL: /Ausruestung/$urlName/\n";

    $subcategories[] = [
        'name' => $name,
        'url' => "/Ausruestung/$urlName/"
    ];
}

echo "\n=== HTML for Navigation ===\n\n";

// Generate HTML snippet
$columns = array_chunk($subcategories, 7);

echo '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px 24px;">' . "\n";
echo '    <a href="/Ausruestung/" style="display: flex; align-items: center; gap: 8px; padding: 6px 0; color: #6b7280; font-size: 13px; text-decoration: none;"><span style="color: #9ca3af;">↳</span> Alle Ausrüstung</a>' . "\n";

foreach ($subcategories as $cat) {
    echo '    <a href="' . $cat['url'] . '" style="display: flex; align-items: center; gap: 8px; padding: 6px 0; color: #6b7280; font-size: 13px; text-decoration: none;"><span style="color: #9ca3af;">↳</span> ' . $cat['name'] . '</a>' . "\n";
}

echo '</div>' . "\n";
