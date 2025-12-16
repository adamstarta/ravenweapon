<?php
/**
 * Delete the 5 empty parent categories created during restructure
 * - Taschen & Transport
 * - Körperschutz
 * - Bekleidung & Tragen
 * - Spezialausrüstung
 * - Behörden & Dienst
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

echo "=== DELETING EMPTY PARENT CATEGORIES ===\n\n";

$token = getToken($API_URL);
if (!$token) die("Auth failed\n");
echo "Authenticated\n\n";

// Categories to delete (empty parents from restructure)
$categoriesToDelete = [
    'Taschen & Transport',
    'Körperschutz',
    'Bekleidung & Tragen',
    'Spezialausrüstung',
    'Behörden & Dienst'
];

// Get all categories
$result = apiRequest($API_URL, $token, 'POST', 'search/category', ['limit' => 500]);

$toDelete = [];
foreach ($result['data'] ?? [] as $cat) {
    $name = $cat['attributes']['name'] ?? $cat['name'] ?? '';
    $id = $cat['id'];

    if (in_array($name, $categoriesToDelete)) {
        $toDelete[$name] = $id;
    }
}

echo "Found categories to delete:\n";
foreach ($toDelete as $name => $id) {
    echo "- $name ($id)\n";
}

echo "\n=== DELETING ===\n\n";

foreach ($toDelete as $name => $id) {
    echo "Deleting: $name ($id)... ";

    $response = apiRequest($API_URL, $token, 'DELETE', "category/$id");

    if (isset($response['errors'])) {
        echo "ERROR: " . json_encode($response['errors']) . "\n";
    } else {
        echo "DELETED\n";
    }

    usleep(200000);
}

// Clear cache
echo "\n=== CLEARING CACHE ===\n";
apiRequest($API_URL, $token, 'DELETE', '_action/cache');
echo "Cache cleared\n";

echo "\n=== DONE ===\n";
echo "Now only the 21 Snigel categories should remain under Ausrüstung\n";
