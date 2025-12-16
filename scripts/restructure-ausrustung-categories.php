<?php
/**
 * Restructure Ausrüstung categories into hierarchical structure
 *
 * Creates 6 parent groups and moves existing 21 subcategories under them
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

echo "=== RESTRUCTURING AUSRÜSTUNG CATEGORIES ===\n\n";

$token = getToken($API_URL);
if (!$token) {
    die("Failed to authenticate!\n");
}
echo "✓ Authenticated\n\n";

// Define the new parent groups and their children
$parentGroups = [
    'Taschen & Transport' => [
        'Taschen & Rucksäcke',
        'Halter & Taschen'
    ],
    'Körperschutz' => [
        'Ballistischer Schutz',
        'Westen & Chest Rigs'
    ],
    'Bekleidung & Tragen' => [
        'Taktische Bekleidung',
        'Gürtel',
        'Tragegurte & Holster',
        'Beinpaneele'
    ],
    'Spezialausrüstung' => [
        'Medizinische Ausrüstung',
        'K9',
        'Scharfschützen',
        'Verdeckte'
    ],
    'Behörden & Dienst' => [
        'Polizeiausrüstung',
        'Verwaltung',
        'Dienstausrüstung'
    ],
    'Zubehör' => [
        'Taktische Ausrüstung',
        'Patches',
        'Source Hydration',
        'Verschiedenes',
        'Multicam',
        'Warnschutz'
    ]
];

// Get all categories
echo "Fetching existing categories...\n";
$result = apiRequest($API_URL, $token, 'POST', 'search/category', [
    'limit' => 500
]);

$categories = [];
$ausrustungId = null;

foreach ($result['data'] ?? [] as $cat) {
    $id = $cat['id'];
    $name = $cat['attributes']['name'] ?? $cat['name'] ?? 'Unknown';
    $parentId = $cat['attributes']['parentId'] ?? $cat['parentId'] ?? null;
    $level = $cat['attributes']['level'] ?? $cat['level'] ?? 0;

    $categories[$name] = [
        'id' => $id,
        'name' => $name,
        'parentId' => $parentId,
        'level' => $level
    ];

    // Find Ausrüstung parent category (Level 2)
    if (stripos($name, 'Ausrüstung') !== false && $level == 2) {
        $ausrustungId = $id;
        echo "✓ Found Ausrüstung category: $id\n";
    }
}

if (!$ausrustungId) {
    die("ERROR: Ausrüstung category not found!\n");
}

// Backup current structure
echo "\n=== CURRENT STRUCTURE BACKUP ===\n";
$backup = [];
foreach ($parentGroups as $parent => $children) {
    foreach ($children as $childName) {
        if (isset($categories[$childName])) {
            $backup[$childName] = [
                'id' => $categories[$childName]['id'],
                'oldParentId' => $categories[$childName]['parentId']
            ];
            echo "- $childName: {$categories[$childName]['id']} (parent: {$categories[$childName]['parentId']})\n";
        } else {
            echo "WARNING: Category '$childName' not found!\n";
        }
    }
}

// Save backup to file
file_put_contents(__DIR__ . '/ausrustung-backup.json', json_encode($backup, JSON_PRETTY_PRINT));
echo "\n✓ Backup saved to ausrustung-backup.json\n";

// Create new parent categories
echo "\n=== CREATING PARENT CATEGORIES ===\n";
$newParentIds = [];
$afterCategoryId = null; // For positioning

foreach ($parentGroups as $parentName => $children) {
    // Check if parent already exists
    if (isset($categories[$parentName])) {
        echo "- $parentName already exists: {$categories[$parentName]['id']}\n";
        $newParentIds[$parentName] = $categories[$parentName]['id'];
        continue;
    }

    $newId = generateUuid();

    $createData = [
        'id' => $newId,
        'name' => $parentName,
        'parentId' => $ausrustungId,
        'active' => true,
        'displayNestedProducts' => true,
        'type' => 'page'
    ];

    if ($afterCategoryId) {
        $createData['afterCategoryId'] = $afterCategoryId;
    }

    $response = apiRequest($API_URL, $token, 'POST', 'category', $createData);

    if (isset($response['errors'])) {
        echo "ERROR creating $parentName: " . json_encode($response['errors']) . "\n";
    } else {
        echo "✓ Created parent: $parentName ($newId)\n";
        $newParentIds[$parentName] = $newId;
        $afterCategoryId = $newId;
    }

    usleep(100000); // 100ms delay
}

// Move subcategories under new parents
echo "\n=== MOVING SUBCATEGORIES ===\n";

foreach ($parentGroups as $parentName => $children) {
    $parentId = $newParentIds[$parentName] ?? null;

    if (!$parentId) {
        echo "ERROR: Parent ID not found for $parentName\n";
        continue;
    }

    echo "\nMoving to '$parentName' ($parentId):\n";

    foreach ($children as $childName) {
        if (!isset($categories[$childName])) {
            echo "  - WARNING: '$childName' not found, skipping\n";
            continue;
        }

        $childId = $categories[$childName]['id'];

        // Update parent ID
        $response = apiRequest($API_URL, $token, 'PATCH', "category/$childId", [
            'parentId' => $parentId
        ]);

        if (isset($response['errors'])) {
            echo "  - ERROR moving $childName: " . json_encode($response['errors']) . "\n";
        } else {
            echo "  ✓ Moved: $childName\n";
        }

        usleep(100000); // 100ms delay
    }
}

// Clear cache
echo "\n=== CLEARING CACHE ===\n";
$cacheResponse = apiRequest($API_URL, $token, 'DELETE', '_action/cache');
echo "✓ Cache cleared\n";

echo "\n=== RESTRUCTURING COMPLETE ===\n";
echo "\nNew structure created:\n";
foreach ($parentGroups as $parentName => $children) {
    echo "\n$parentName:\n";
    foreach ($children as $child) {
        echo "  └── $child\n";
    }
}

echo "\nBackup saved to: ausrustung-backup.json\n";
echo "To rollback, run: php rollback-ausrustung-categories.php\n";
