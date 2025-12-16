<?php
/**
 * Get SEO URLs for grouped Ausrüstung structure
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

$token = getToken($API_URL);

// Get all categories
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

$ausrustungId = null;
$categories = [];

foreach ($result['data'] ?? [] as $cat) {
    $id = $cat['id'];
    $name = $cat['attributes']['name'] ?? $cat['name'] ?? '';
    $level = $cat['attributes']['level'] ?? $cat['level'] ?? 0;
    $parentId = $cat['attributes']['parentId'] ?? $cat['parentId'] ?? null;

    $categories[$id] = [
        'name' => $name,
        'level' => $level,
        'parentId' => $parentId,
        'url' => $seoUrls[$id] ?? "/navigation/$id"
    ];

    if ($name === 'Ausrüstung' && $level == 2) {
        $ausrustungId = $id;
    }
}

// Parent groups structure
$parentGroups = [
    'Körperschutz' => ['Ballistischer Schutz', 'Westen & Chest Rigs'],
    'Taschen & Transport' => ['Taschen & Rucksäcke', 'Halter & Taschen'],
    'Bekleidung & Tragen' => ['Taktische Bekleidung', 'Gürtel', 'Tragegurte & Holster', 'Beinpaneele'],
    'Spezialausrüstung' => ['Medizinische Ausrüstung', 'K9 Ausrüstung', 'Scharfschützen-Ausrüstung', 'Verdeckte Ausrüstung'],
    'Behörden & Dienst' => ['Polizeiausrüstung', 'Verwaltungsausrüstung', 'Dienstausrüstung'],
    'Zubehör & Sonstiges' => ['Taktische Ausrüstung', 'Patches', 'Source Hydration', 'Verschiedenes', 'Multicam', 'Warnschutz']
];

// Build name to ID mapping
$nameToId = [];
foreach ($categories as $id => $cat) {
    $nameToId[$cat['name']] = $id;
}

echo "=== MEGA-MENU HTML FOR GROUPED AUSRÜSTUNG ===\n\n";

echo '<div class="raven-mega-menu" style="min-width: 800px; padding: 24px;">' . "\n";
echo '    <div class="menu-header" style="margin-bottom: 16px; font-size: 15px; font-weight: 600; color: #1f2937;">Taktische Ausrüstung</div>' . "\n";
echo '    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px;">' . "\n";

foreach ($parentGroups as $parentName => $children) {
    $parentId = $nameToId[$parentName] ?? null;
    $parentUrl = $parentId ? ($seoUrls[$parentId] ?? "/navigation/$parentId") : "#";

    echo "        <!-- $parentName -->\n";
    echo "        <div>\n";
    echo "            <a href=\"$parentUrl\" style=\"display: block; font-weight: 600; font-size: 13px; color: #374151; margin-bottom: 8px; text-decoration: none;\">$parentName</a>\n";

    foreach ($children as $childName) {
        $childId = $nameToId[$childName] ?? null;
        $childUrl = $childId ? ($seoUrls[$childId] ?? "/navigation/$childId") : "#";
        echo "            <a href=\"$childUrl\" style=\"display: block; padding: 4px 0; color: #6b7280; font-size: 12px; text-decoration: none;\">$childName</a>\n";
    }

    echo "        </div>\n";
}

echo "    </div>\n";
echo "</div>\n";

echo "\n\n=== URL MAPPING ===\n\n";
foreach ($parentGroups as $parentName => $children) {
    $parentId = $nameToId[$parentName] ?? null;
    $parentUrl = $parentId ? ($seoUrls[$parentId] ?? "/navigation/$parentId") : "NOT FOUND";
    echo "$parentName: $parentUrl\n";
    foreach ($children as $childName) {
        $childId = $nameToId[$childName] ?? null;
        $childUrl = $childId ? ($seoUrls[$childId] ?? "/navigation/$childId") : "NOT FOUND";
        echo "  - $childName: $childUrl\n";
    }
    echo "\n";
}
