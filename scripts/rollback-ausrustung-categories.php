<?php
/**
 * Rollback Ausrüstung category restructuring
 *
 * Restores subcategories to their original parent (flat structure)
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

echo "=== ROLLBACK AUSRÜSTUNG RESTRUCTURING ===\n\n";

// Load backup
$backupFile = __DIR__ . '/ausrustung-backup.json';
if (!file_exists($backupFile)) {
    die("ERROR: Backup file not found: $backupFile\n");
}

$backup = json_decode(file_get_contents($backupFile), true);
if (!$backup) {
    die("ERROR: Could not parse backup file\n");
}

echo "✓ Loaded backup with " . count($backup) . " categories\n\n";

$token = getToken($API_URL);
if (!$token) {
    die("Failed to authenticate!\n");
}
echo "✓ Authenticated\n\n";

// Restore original parent IDs
echo "=== RESTORING ORIGINAL PARENTS ===\n";

foreach ($backup as $categoryName => $data) {
    $categoryId = $data['id'];
    $originalParentId = $data['oldParentId'];

    echo "Restoring: $categoryName -> $originalParentId\n";

    $response = apiRequest($API_URL, $token, 'PATCH', "category/$categoryId", [
        'parentId' => $originalParentId
    ]);

    if (isset($response['errors'])) {
        echo "  ERROR: " . json_encode($response['errors']) . "\n";
    } else {
        echo "  ✓ Restored\n";
    }

    usleep(100000); // 100ms delay
}

// Clear cache
echo "\n=== CLEARING CACHE ===\n";
$cacheResponse = apiRequest($API_URL, $token, 'DELETE', '_action/cache');
echo "✓ Cache cleared\n";

echo "\n=== ROLLBACK COMPLETE ===\n";
echo "\nNote: The new parent categories (Taschen & Transport, etc.) still exist but are now empty.\n";
echo "You can delete them manually from Shopware Admin if needed.\n";
