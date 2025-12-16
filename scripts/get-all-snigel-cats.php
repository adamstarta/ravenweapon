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

$result = apiRequest($API_URL, $token, 'POST', 'search/category', ['limit' => 500]);

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

// Categories we want for Snigel/Ausrüstung menu (21 total)
$snigelCategories = [
    'Ballistischer Schutz',
    'Beinpaneele',
    'Dienstausrüstung',
    'Gürtel',
    'Halter & Taschen',
    'K9 Ausrüstung',
    'Medizinische Ausrüstung',
    'Multicam',
    'Patches',
    'Polizeiausrüstung',
    'Scharfschützen-Ausrüstung',
    'Source Hydration',
    'Taktische Ausrüstung',
    'Taktische Bekleidung',
    'Taschen & Rucksäcke',
    'Tragegurte & Holster',
    'Verdeckte Ausrüstung',
    'Verschiedenes',
    'Verwaltungsausrüstung',
    'Warnschutz',
    'Westen & Chest Rigs'
];

$found = [];
foreach ($result['data'] ?? [] as $cat) {
    $name = $cat['attributes']['name'] ?? $cat['name'] ?? '';
    $id = $cat['id'];

    if (in_array($name, $snigelCategories)) {
        $url = $seoUrls[$id] ?? "/navigation/$id";
        $found[$name] = $url;
    }
}

// Sort alphabetically
ksort($found);

echo "=== ALL 21 SNIGEL CATEGORIES FOR MENU ===\n\n";
echo "Found: " . count($found) . " / 21\n\n";

echo "=== MENU HTML (Flat/Straight Layout) ===\n\n";
echo '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px 24px;">' . "\n";
foreach ($found as $name => $url) {
    echo "    <a href=\"$url\" style=\"display: flex; align-items: center; gap: 8px; padding: 6px 0; color: #6b7280; font-size: 13px; text-decoration: none;\"><span style=\"color: #9ca3af;\">↳</span> $name</a>\n";
}
echo "</div>\n";

echo "\n=== MISSING CATEGORIES ===\n";
foreach ($snigelCategories as $cat) {
    if (!isset($found[$cat])) {
        echo "- $cat\n";
    }
}
