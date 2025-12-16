<?php
$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

// Get token
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
$token = $data['access_token'] ?? null;
curl_close($ch);

if (!$token) {
    die("Failed to authenticate\n");
}
echo "Authenticated successfully\n\n";

// Search for Sturmgewehre category
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/search/category',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'filter' => [
            ['type' => 'equals', 'field' => 'name', 'value' => 'Sturmgewehre']
        ]
    ]),
]);
$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);

if (!empty($result['data'])) {
    $category = $result['data'][0];
    $categoryId = $category['id'];
    echo "Found: Sturmgewehre (ID: $categoryId)\n";

    // Update category name
    echo "Renaming to 'Raven Weapons'...\n";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . '/api/category/' . $categoryId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'name' => 'Raven Weapons'
        ]),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 204 || $httpCode === 200) {
        echo "SUCCESS: Category renamed to 'Raven Weapons'\n";
    } else {
        echo "Failed (HTTP $httpCode): $response\n";
    }
} else {
    echo "Category 'Sturmgewehre' not found\n";
}
