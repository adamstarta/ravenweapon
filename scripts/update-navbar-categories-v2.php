<?php
/**
 * Update Navbar Categories via Shopware Admin API
 * Using admin username/password authentication
 */

$config = [
    'base_url' => 'http://localhost',
    'client_id' => 'SWIARAVEN03399CEA2C931269',
    'client_secret' => 'RavenNavbarUpdate2025!'
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
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) {
        echo "CURL Error: $err\n";
        return null;
    }
    $data = json_decode($response, true);
    if (isset($data['errors'])) {
        echo "Auth Error: " . json_encode($data['errors']) . "\n";
        return null;
    }
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
echo "Got API access token\n\n";

$rootId = getRootCategoryId($token, $config);
echo "Root category ID: $rootId\n\n";

// 1. RENAME Waffenzubehoer
echo "--- Step 1: Rename Waffenzubehoer ---\n";
$waffenzubehoer = findCategoryByName("Waffenzubeh\xc3\xb6r", $token, $config);
if ($waffenzubehoer) {
    $waffenId = $waffenzubehoer['id'];
    echo "Found Waffenzubehoer: $waffenId\n";

    $result = updateCategory($waffenId, ['name' => "Zielhilfen, Optik & Zubeh\xc3\xb6r"], $token, $config);
    echo ($result['code'] < 300 ? "OK" : "FAIL") . " Renamed to Zielhilfen, Optik & Zubehoer\n";

    echo "Deleting old subcategories...\n";
    $oldSubs = findSubcategories($waffenId, $token, $config);
    foreach ($oldSubs as $sub) {
        $delResult = deleteCategory($sub['id'], $token, $config);
        echo "  - Deleted: " . $sub['name'] . " (" . ($delResult['code'] < 300 ? 'OK' : 'FAIL') . ")\n";
    }

    echo "Creating new subcategories...\n";
    $newSubs = ['Zielfernrohre', 'Rotpunktvisiere', 'Spektive', "Ferngl\xc3\xa4ser"];
    foreach ($newSubs as $subName) {
        $result = createCategory([
            'id' => generateUuid(),
            'parentId' => $waffenId,
            'name' => $subName,
            'active' => true,
            'visible' => true
        ], $token, $config);
        echo "  + Created: $subName (" . ($result['code'] < 300 ? 'OK' : 'FAIL') . ")\n";
    }
} else {
    echo "Waffenzubehoer not found\n";
}

// 2. CREATE Zubehoer
echo "\n--- Step 2: Create Zubehoer category ---\n";
$existingZubehoer = findCategoryByName("Zubeh\xc3\xb6r", $token, $config);
if ($existingZubehoer) {
    echo "Zubehoer already exists\n";
    $zubehoerId = $existingZubehoer['id'];
} else {
    $zubehoerId = generateUuid();
    $result = createCategory([
        'id' => $zubehoerId,
        'parentId' => $rootId,
        'name' => "Zubeh\xc3\xb6r",
        'active' => true,
        'visible' => true
    ], $token, $config);
    echo ($result['code'] < 300 ? "OK" : "FAIL") . " Created Zubehoer category\n";
}

echo "Creating Zubehoer subcategories...\n";
$zubehoerSubs = ['Magazine', 'Griffe & Handschutz', "Schienen & Zubeh\xc3\xb6r", 'Zweibeine', "M\xc3\xbcndungsaufs\xc3\xa4tze", 'Zielfernrohrmontagen'];
foreach ($zubehoerSubs as $subName) {
    $existing = findCategoryByName($subName, $token, $config);
    if (!$existing) {
        $result = createCategory([
            'id' => generateUuid(),
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

// 3. UPDATE Raven Weapons
echo "\n--- Step 3: Update Raven Weapons subcategories ---\n";
$ravenWeapons = findCategoryByName('Raven Weapons', $token, $config);
if ($ravenWeapons) {
    $ravenId = $ravenWeapons['id'];
    echo "Found Raven Weapons: $ravenId\n";

    $oldSubs = findSubcategories($ravenId, $token, $config);
    foreach ($oldSubs as $sub) {
        $delResult = deleteCategory($sub['id'], $token, $config);
        echo "  - Deleted: " . $sub['name'] . " (" . ($delResult['code'] < 300 ? 'OK' : 'FAIL') . ")\n";
    }

    $ravenSubs = ['Sturmgewehre', 'RAPAX'];
    foreach ($ravenSubs as $subName) {
        $result = createCategory([
            'id' => generateUuid(),
            'parentId' => $ravenId,
            'name' => $subName,
            'active' => true,
            'visible' => true
        ], $token, $config);
        echo "  + Created: $subName (" . ($result['code'] < 300 ? 'OK' : 'FAIL') . ")\n";
    }
} else {
    echo "Raven Weapons not found\n";
}

// 4. DELETE Munition subcategories
echo "\n--- Step 4: Delete Munition subcategories ---\n";
$munition = findCategoryByName('Munition', $token, $config);
if ($munition) {
    $munitionId = $munition['id'];
    $oldSubs = findSubcategories($munitionId, $token, $config);
    if (count($oldSubs) > 0) {
        foreach ($oldSubs as $sub) {
            $delResult = deleteCategory($sub['id'], $token, $config);
            echo "  - Deleted: " . $sub['name'] . " (" . ($delResult['code'] < 300 ? 'OK' : 'FAIL') . ")\n";
        }
    } else {
        echo "No subcategories to delete\n";
    }
}

// 5. CLEAR CACHE
echo "\n--- Step 5: Clear cache ---\n";
$result = apiRequest('DELETE', '/_action/cache', null, $token, $config);
echo ($result['code'] < 300 ? "OK" : "FAIL") . " Cache cleared\n";

echo "\n=== DONE ===\n";
