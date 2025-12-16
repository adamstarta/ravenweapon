<?php
/**
 * Update all Raven-Weapons SEO URLs to Waffen
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

function getToken($config) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . '/api/oauth/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => $config['api_user'],
            'password' => $config['api_password'],
        ]),
    ]);
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);
    return $data['access_token'] ?? null;
}

function apiRequest($config, $token, $method, $endpoint, $data = null) {
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $config['shopware_url'] . $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($data) $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    } elseif ($method === 'PATCH' || $method === 'DELETE') {
        $opts[CURLOPT_CUSTOMREQUEST] = $method;
        if ($data) $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

$token = getToken($config);
if (!$token) {
    die("Failed to authenticate\n");
}
echo "Authenticated successfully\n\n";

// Find all SEO URLs containing 'Raven-Weapons'
echo "=== Finding all Raven-Weapons SEO URLs ===\n";

$result = apiRequest($config, $token, 'POST', '/api/search/seo-url', [
    'filter' => [
        ['type' => 'contains', 'field' => 'seoPathInfo', 'value' => 'Raven-Weapons']
    ],
    'limit' => 500
]);

$seoUrls = $result['data']['data'] ?? [];
echo "Found " . count($seoUrls) . " SEO URLs with 'Raven-Weapons'\n\n";

// Update each SEO URL
$updated = 0;
$failed = 0;
$skipped = 0;

foreach ($seoUrls as $seo) {
    $id = $seo['id'];
    // Data is in attributes subarray
    $oldPath = $seo['attributes']['seoPathInfo'] ?? ($seo['seoPathInfo'] ?? '');
    $isDeleted = $seo['attributes']['isDeleted'] ?? false;

    if (empty($oldPath)) {
        $skipped++;
        continue;
    }

    // Skip deleted SEO URLs
    if ($isDeleted) {
        echo "Skipping deleted: $oldPath\n";
        $skipped++;
        continue;
    }

    $newPath = str_replace('Raven-Weapons', 'Waffen', $oldPath);

    if ($oldPath === $newPath) {
        $skipped++;
        continue;
    }

    echo "Updating: $oldPath -> $newPath ... ";

    $updateResult = apiRequest($config, $token, 'PATCH', '/api/seo-url/' . $id, [
        'seoPathInfo' => $newPath,
        'isModified' => true
    ]);

    if ($updateResult['code'] === 204 || $updateResult['code'] === 200) {
        echo "OK\n";
        $updated++;
    } else {
        echo "FAILED (HTTP " . $updateResult['code'] . ")\n";
        if (isset($updateResult['data']['errors'])) {
            print_r($updateResult['data']['errors']);
        }
        $failed++;
    }
}

echo "\n=== Summary ===\n";
echo "Updated: $updated\n";
echo "Failed: $failed\n";
echo "Skipped: $skipped\n";

echo "\nDone! Clearing cache now...\n";
