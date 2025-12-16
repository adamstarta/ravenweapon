<?php
/**
 * Check actual SEO URLs for Ausrüstung categories
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

echo "=== CHECKING AUSRÜSTUNG SEO URLS ===\n\n";

$token = getToken($API_URL);
if (!$token) {
    die("Failed to authenticate!\n");
}

// Get all categories
$result = apiRequest($API_URL, $token, 'POST', 'search/category', [
    'limit' => 500
]);

$categories = [];
$ausrustungId = null;
$byId = [];

foreach ($result['data'] ?? [] as $cat) {
    $id = $cat['id'];
    $name = $cat['attributes']['name'] ?? $cat['name'] ?? 'Unknown';
    $parentId = $cat['attributes']['parentId'] ?? $cat['parentId'] ?? null;
    $level = $cat['attributes']['level'] ?? $cat['level'] ?? 0;

    $byId[$id] = [
        'name' => $name,
        'parentId' => $parentId,
        'level' => $level
    ];

    if (stripos($name, 'Ausrüstung') !== false && $level == 2) {
        $ausrustungId = $id;
    }
}

// Get SEO URLs for all Ausrüstung-related categories
$seoResult = apiRequest($API_URL, $token, 'POST', 'search/seo-url', [
    'filter' => [
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page'],
        ['type' => 'equals', 'field' => 'isCanonical', 'value' => true]
    ],
    'limit' => 500
]);

$seoByCategory = [];
foreach ($seoResult['data'] ?? [] as $seo) {
    $foreignKey = $seo['attributes']['foreignKey'] ?? $seo['foreignKey'] ?? null;
    $pathInfo = $seo['attributes']['seoPathInfo'] ?? $seo['seoPathInfo'] ?? 'N/A';
    if ($foreignKey) {
        $seoByCategory[$foreignKey] = '/' . $pathInfo;
    }
}

echo "=== AUSRÜSTUNG CATEGORY TREE WITH SEO URLS ===\n\n";

// Build and print tree
function isAusrustungRelated($catId, $ausrustungId, $byId, $depth = 0) {
    if ($depth > 10) return false;
    if ($catId === $ausrustungId) return true;
    $parentId = $byId[$catId]['parentId'] ?? null;
    if ($parentId && isset($byId[$parentId])) {
        return isAusrustungRelated($parentId, $ausrustungId, $byId, $depth + 1);
    }
    return false;
}

function printCategory($catId, $byId, $seoByCategory, $indent = 0) {
    $cat = $byId[$catId];
    $prefix = str_repeat('  ', $indent);
    $symbol = $indent > 0 ? '└─ ' : '';
    $seoUrl = $seoByCategory[$catId] ?? 'NO SEO URL';
    echo $prefix . $symbol . $cat['name'] . "\n";
    echo $prefix . "   " . $seoUrl . "\n";
}

// Print Ausrüstung
echo "Ausrüstung\n";
echo "   " . ($seoByCategory[$ausrustungId] ?? 'NO SEO URL') . "\n\n";

// Get direct children of Ausrüstung (new parent categories)
$parents = [];
foreach ($byId as $id => $cat) {
    if ($cat['parentId'] === $ausrustungId) {
        $parents[$id] = $cat['name'];
    }
}

// Sort by name
asort($parents);

foreach ($parents as $parentId => $parentName) {
    $seoUrl = $seoByCategory[$parentId] ?? 'NO SEO URL';
    echo "└─ $parentName\n";
    echo "   $seoUrl\n";

    // Get children of this parent
    $children = [];
    foreach ($byId as $id => $cat) {
        if ($cat['parentId'] === $parentId) {
            $children[$id] = $cat['name'];
        }
    }
    asort($children);

    foreach ($children as $childId => $childName) {
        $childSeoUrl = $seoByCategory[$childId] ?? 'NO SEO URL';
        echo "   └─ $childName\n";
        echo "      $childSeoUrl\n";
    }
    echo "\n";
}

echo "=== SUMMARY FOR FRONTEND MENU UPDATE ===\n\n";
echo "Copy these URLs to update the mega-menu:\n\n";

$menuMapping = [
    'Taschen & Transport' => ['Taschen & Rucksäcke', 'Halter & Taschen'],
    'Körperschutz' => ['Ballistischer Schutz', 'Westen & Chest Rigs'],
    'Bekleidung & Tragen' => ['Taktische Bekleidung', 'Gürtel', 'Tragegurte & Holster', 'Beinpaneele'],
    'Spezialausrüstung' => ['Medizinische Ausrüstung', 'K9', 'Scharfschützen', 'Verdeckte'],
    'Behörden & Dienst' => ['Polizeiausrüstung', 'Verwaltung', 'Dienstausrüstung'],
    'Zubehör' => ['Taktische Ausrüstung', 'Patches', 'Source Hydration', 'Verschiedenes', 'Multicam', 'Warnschutz']
];

foreach ($menuMapping as $parentName => $children) {
    // Find parent ID
    $parentId = null;
    foreach ($byId as $id => $cat) {
        if ($cat['name'] === $parentName && $cat['parentId'] === $ausrustungId) {
            $parentId = $id;
            break;
        }
    }

    if (!$parentId) {
        echo "// $parentName - NOT FOUND\n";
        continue;
    }

    echo "// $parentName\n";

    foreach ($children as $childName) {
        // Find child ID
        $childId = null;
        foreach ($byId as $id => $cat) {
            if ($cat['name'] === $childName && $cat['parentId'] === $parentId) {
                $childId = $id;
                break;
            }
        }

        if ($childId && isset($seoByCategory[$childId])) {
            echo "\"$childName\" => \"{$seoByCategory[$childId]}\"\n";
        } else {
            echo "\"$childName\" => NOT FOUND\n";
        }
    }
    echo "\n";
}
