<?php
$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

// Get token
$ch = curl_init($config['shopware_url'] . '/api/oauth/token');
curl_setopt_array($ch, [
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
curl_close($ch);
$token = json_decode($response, true)['access_token'];
echo "Got token\n";

// Get products directly (not search)
$ch = curl_init($config['shopware_url'] . '/api/product?limit=500');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response (first 1000 chars): " . substr($response, 0, 1000) . "\n\n";
$result = json_decode($response, true);

if ($httpCode !== 200) {
    echo "Error: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
}

echo 'Total from API: ' . ($result['total'] ?? 0) . "\n";
echo 'Data count: ' . count($result['data'] ?? []) . "\n\n";
echo "RAPAX/CARACAL products:\n";

$rapax = [];
if (isset($result['data'])) {
    foreach ($result['data'] as $p) {
        // Handle JSON:API format (attributes) vs regular format
        $attrs = $p['attributes'] ?? $p;
        $name = $attrs['translated']['name'] ?? $attrs['name'] ?? $p['translated']['name'] ?? $p['name'] ?? '';
        $id = $p['id'] ?? '';

        if (stripos($name, 'RAPAX') !== false || stripos($name, 'CARACAL') !== false || stripos($name, 'Lynx') !== false) {
            $rapax[$id] = $name;
        }
    }
}

echo count($rapax) . " found:\n";
foreach ($rapax as $id => $name) {
    echo "- $name (ID: $id)\n";
}
