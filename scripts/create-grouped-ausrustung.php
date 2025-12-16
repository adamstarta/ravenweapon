<?php
/**
 * Create grouped parent categories under Ausrüstung
 * Following use-case grouping for better customer navigation
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

function generateUuid() {
    return sprintf('%04x%04x%04x%04x%04x%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

echo "=== CREATING GROUPED AUSRÜSTUNG STRUCTURE ===\n\n";

$token = getToken($API_URL);
if (!$token) die("Auth failed\n");
echo "Authenticated\n\n";

// Get all categories
$result = apiRequest($API_URL, $token, 'POST', 'search/category', ['limit' => 500]);

$ausrustungId = null;
$categories = [];

foreach ($result['data'] ?? [] as $cat) {
    $id = $cat['id'];
    $name = $cat['attributes']['name'] ?? $cat['name'] ?? '';
    $level = $cat['attributes']['level'] ?? $cat['level'] ?? 0;
    $parentId = $cat['attributes']['parentId'] ?? $cat['parentId'] ?? null;

    $categories[$name] = [
        'id' => $id,
        'level' => $level,
        'parentId' => $parentId
    ];

    if ($name === 'Ausrüstung' && $level == 2) {
        $ausrustungId = $id;
        echo "Found Ausrüstung: $id\n";
    }
}

if (!$ausrustungId) die("ERROR: Ausrüstung not found!\n");

// Parent categories to create with their children (use-case based grouping)
$parentGroups = [
    'Körperschutz' => [
        'children' => ['Ballistischer Schutz', 'Westen & Chest Rigs'],
        'description' => 'Ballistische Schutzausrüstung und Westen'
    ],
    'Taschen & Transport' => [
        'children' => ['Taschen & Rucksäcke', 'Halter & Taschen'],
        'description' => 'Taschen, Rucksäcke und Transportlösungen'
    ],
    'Bekleidung & Tragen' => [
        'children' => ['Taktische Bekleidung', 'Gürtel', 'Tragegurte & Holster', 'Beinpaneele'],
        'description' => 'Taktische Bekleidung und Tragesysteme'
    ],
    'Spezialausrüstung' => [
        'children' => ['Medizinische Ausrüstung', 'K9 Ausrüstung', 'Scharfschützen-Ausrüstung', 'Verdeckte Ausrüstung'],
        'description' => 'Spezialisierte Ausrüstung für besondere Einsätze'
    ],
    'Behörden & Dienst' => [
        'children' => ['Polizeiausrüstung', 'Verwaltungsausrüstung', 'Dienstausrüstung'],
        'description' => 'Ausrüstung für Behörden und Dienststellen'
    ],
    'Zubehör & Sonstiges' => [
        'children' => ['Taktische Ausrüstung', 'Patches', 'Source Hydration', 'Verschiedenes', 'Multicam', 'Warnschutz'],
        'description' => 'Zubehör und sonstige Ausrüstung'
    ]
];

echo "\n=== STEP 1: CREATE PARENT CATEGORIES ===\n\n";

$createdParents = [];
$position = 1;

foreach ($parentGroups as $parentName => $config) {
    echo "Creating: $parentName... ";

    $parentId = generateUuid();

    $response = apiRequest($API_URL, $token, 'POST', 'category', [
        'id' => $parentId,
        'parentId' => $ausrustungId,
        'name' => $parentName,
        'active' => true,
        'visible' => true,
        'type' => 'page',
        'displayNestedProducts' => true,
        'productAssignmentType' => 'product',
        'description' => $config['description'],
        'position' => $position++
    ]);

    if (isset($response['errors'])) {
        echo "ERROR: " . json_encode($response['errors']) . "\n";
    } else {
        echo "CREATED ($parentId)\n";
        $createdParents[$parentName] = $parentId;
    }

    usleep(200000);
}

echo "\n=== STEP 2: MOVE SUBCATEGORIES UNDER PARENTS ===\n\n";

foreach ($parentGroups as $parentName => $config) {
    if (!isset($createdParents[$parentName])) {
        echo "Skipping $parentName - parent not created\n";
        continue;
    }

    $newParentId = $createdParents[$parentName];
    echo "\n--- Moving to: $parentName ---\n";

    foreach ($config['children'] as $childName) {
        if (!isset($categories[$childName])) {
            echo "  WARNING: '$childName' not found, skipping\n";
            continue;
        }

        $childId = $categories[$childName]['id'];
        echo "  Moving: $childName... ";

        $response = apiRequest($API_URL, $token, 'PATCH', "category/$childId", [
            'parentId' => $newParentId
        ]);

        if (isset($response['errors'])) {
            echo "ERROR: " . json_encode($response['errors']) . "\n";
        } else {
            echo "OK\n";
        }

        usleep(100000);
    }
}

// Clear cache
echo "\n=== CLEARING CACHE ===\n";
apiRequest($API_URL, $token, 'DELETE', '_action/cache');
echo "Cache cleared\n";

echo "\n=== DONE ===\n";
echo "Created parent categories and moved subcategories.\n";
echo "New structure:\n";
foreach ($parentGroups as $parentName => $config) {
    echo "  $parentName\n";
    foreach ($config['children'] as $child) {
        echo "    - $child\n";
    }
}
