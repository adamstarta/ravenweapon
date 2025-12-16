<?php
/**
 * Update Navbar Categories via Shopware Admin API
 *
 * Changes:
 * 1. Rename "Waffenzubehör" → "Zielhilfen, Optik & Zubehör"
 * 2. Replace its subcategories with: Zielfernrohre, Rotpunktvisiere, Spektive, Ferngläser
 * 3. Create new "Zubehör" category with subcategories
 * 4. Update Raven Weapons subcategories to: Sturmgewehre, RAPAX
 * 5. Delete Munition subcategories (keep parent)
 */

$config = [
    'base_url' => 'http://localhost',
    'client_id' => 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
    'client_secret' => 'Q1BLY2MwcE9NQ3d0RXJZak9yT2NLcjFYZUVqU0NMdFZkdEd2Wnk'
];

// API Helper Functions
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
        CURLOPT_RETURNTRANSFER => true
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
        CURLOPT_RETURNTRANSFER => true
    ];
    if ($data !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

function findCategoryByName($name, $token, $config) {
    $result = apiRequest('POST', '/search/category', [
        'filter' => [['type' => 'equals', 'field' => 'name', 'value' => $name]],
        'includes' => ['category' => ['id', 'name', 'parentId', 'level']]
    ], $token, $config);
    return $result['body']['data'][0] ?? null;
}

function findSubcategories($parentId, $token, $config) {
    $result = apiRequest('POST', '/search/category', [
        'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $parentId]],
        'includes' => ['category' => ['id', 'name']]
    ], $token, $config);
    return $result['body']['data'] ?? [];
}

function deleteCategory($id, $token, $config) {
    return apiRequest('DELETE', '/category/' . $id, null, $token, $config);
}

function createCategory($data, $token, $config) {
    return apiRequest('POST', '/category', $data, $token, $config);
}

function updateCategory($id, $data, $token, $config) {
    return apiRequest('PATCH', '/category/' . $id, $data, $token, $config);
}

function generateUuid() {
    return sprintf('%04x%04x%04x%04x%04x%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
}

// Get root category ID
function getRootCategoryId($token, $config) {
    $result = apiRequest('POST', '/search/category', [
        'filter' => [['type' => 'equals', 'field' => 'level', 'value' => 1]],
        'includes' => ['category' => ['id', 'name']]
    ], $token, $config);
    return $result['body']['data'][0]['id'] ?? null;
}

// Main execution
echo "=== Navbar Categories Update Script ===\n\n";

$token = getAccessToken($config);
if (!$token) {
    die("ERROR: Failed to get access token\n");
}
echo "✓ Got API access token\n\n";

$rootId = getRootCategoryId($token, $config);
echo "Root category ID: $rootId\n\n";

// ============================================
// 1. RENAME "Waffenzubehör" → "Zielhilfen, Optik & Zubehör"
// ============================================
echo "--- Step 1: Rename Waffenzubehör ---\n";
$waffenzubehoer = findCategoryByName('Waffenzubehör', $token, $config);
if ($waffenzubehoer) {
    $waffenId = $waffenzubehoer['id'];
    echo "Found Waffenzubehör: $waffenId\n";

    // Rename it
    $result = updateCategory($waffenId, [
        'name' => 'Zielhilfen, Optik & Zubehör'
    ], $token, $config);

    if ($result['code'] >= 200 && $result['code'] < 300) {
        echo "✓ Renamed to 'Zielhilfen, Optik & Zubehör'\n";
    } else {
        echo "✗ Failed to rename: " . json_encode($result['body']) . "\n";
    }

    // Delete old subcategories
    echo "Deleting old subcategories...\n";
    $oldSubs = findSubcategories($waffenId, $token, $config);
    foreach ($oldSubs as $sub) {
        $delResult = deleteCategory($sub['id'], $token, $config);
        echo "  - Deleted: {$sub['name']} (" . ($delResult['code'] < 300 ? 'OK' : 'FAIL') . ")\n";
    }

    // Create new subcategories
    echo "Creating new subcategories...\n";
    $newSubs = ['Zielfernrohre', 'Rotpunktvisiere', 'Spektive', 'Ferngläser'];
    foreach ($newSubs as $subName) {
        $subId = generateUuid();
        $result = createCategory([
            'id' => $subId,
            'parentId' => $waffenId,
            'name' => $subName,
            'active' => true,
            'visible' => true
        ], $token, $config);
        echo "  + Created: $subName (" . ($result['code'] < 300 ? 'OK' : 'FAIL') . ")\n";
    }
} else {
    echo "✗ Waffenzubehör not found\n";
}

// ============================================
// 2. CREATE NEW "Zubehör" CATEGORY
// ============================================
echo "\n--- Step 2: Create Zubehör category ---\n";
$existingZubehoer = findCategoryByName('Zubehör', $token, $config);
if ($existingZubehoer) {
    echo "Zubehör already exists, updating subcategories...\n";
    $zubehoerId = $existingZubehoer['id'];
} else {
    $zubehoerId = generateUuid();
    $result = createCategory([
        'id' => $zubehoerId,
        'parentId' => $rootId,
        'name' => 'Zubehör',
        'active' => true,
        'visible' => true
    ], $token, $config);
    echo ($result['code'] < 300 ? "✓" : "✗") . " Created Zubehör category\n";
}

// Create Zubehör subcategories
$zubehoerSubs = [
    'Magazine',
    'Griffe & Handschutz',
    'Schienen & Zubehör',
    'Zweibeine',
    'Mündungsaufsätze',
    'Zielfernrohrmontagen'
];
echo "Creating Zubehör subcategories...\n";
foreach ($zubehoerSubs as $subName) {
    // Check if exists first
    $existing = findCategoryByName($subName, $token, $config);
    if (!$existing) {
        $subId = generateUuid();
        $result = createCategory([
            'id' => $subId,
            'parentId' => $zubehoerId,
            'name' => $subName,
            'active' => true,
            'visible' => true
        ], $token, $config);
        echo "  + Created: $subName (" . ($result['code'] < 300 ? 'OK' : 'FAIL') . ")\n";
    } else {
        echo "  ~ Exists: $subName\n";
    }
}

// ============================================
// 3. UPDATE RAVEN WEAPONS SUBCATEGORIES
// ============================================
echo "\n--- Step 3: Update Raven Weapons subcategories ---\n";
$ravenWeapons = findCategoryByName('Raven Weapons', $token, $config);
if ($ravenWeapons) {
    $ravenId = $ravenWeapons['id'];
    echo "Found Raven Weapons: $ravenId\n";

    // Delete old subcategories
    $oldSubs = findSubcategories($ravenId, $token, $config);
    foreach ($oldSubs as $sub) {
        $delResult = deleteCategory($sub['id'], $token, $config);
        echo "  - Deleted: {$sub['name']} (" . ($delResult['code'] < 300 ? 'OK' : 'FAIL') . ")\n";
    }

    // Create new subcategories
    $ravenSubs = ['Sturmgewehre', 'RAPAX'];
    foreach ($ravenSubs as $subName) {
        $subId = generateUuid();
        $result = createCategory([
            'id' => $subId,
            'parentId' => $ravenId,
            'name' => $subName,
            'active' => true,
            'visible' => true
        ], $token, $config);
        echo "  + Created: $subName (" . ($result['code'] < 300 ? 'OK' : 'FAIL') . ")\n";
    }
} else {
    echo "✗ Raven Weapons not found\n";
}

// ============================================
// 4. DELETE MUNITION SUBCATEGORIES
// ============================================
echo "\n--- Step 4: Delete Munition subcategories ---\n";
$munition = findCategoryByName('Munition', $token, $config);
if ($munition) {
    $munitionId = $munition['id'];
    $oldSubs = findSubcategories($munitionId, $token, $config);
    if (count($oldSubs) > 0) {
        foreach ($oldSubs as $sub) {
            $delResult = deleteCategory($sub['id'], $token, $config);
            echo "  - Deleted: {$sub['name']} (" . ($delResult['code'] < 300 ? 'OK' : 'FAIL') . ")\n";
        }
    } else {
        echo "No subcategories to delete\n";
    }
} else {
    echo "✗ Munition not found\n";
}

// ============================================
// 5. CLEAR CACHE
// ============================================
echo "\n--- Step 5: Clear cache ---\n";
$result = apiRequest('DELETE', '/_action/cache', null, $token, $config);
echo ($result['code'] < 300 ? "✓" : "✗") . " Cache cleared\n";

echo "\n=== DONE ===\n";
echo "Please refresh https://ortak.ch to see changes.\n";
