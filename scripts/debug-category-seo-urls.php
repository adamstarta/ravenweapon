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

if (!$token) die("Failed to authenticate\n");

echo "=== Checking SEO URLs for Main Categories ===\n\n";

$categories = [
    'Alle Produkte' => '019aee0f487c79a1a8814377c46e0c10',
    'Waffen' => 'a61f19c9cb4b11f0b4074aca3d279c31',
    'Raven Caliber Kit' => 'a61f1ec3cb4b11f0b4074aca3d279c31',
    'Zielhilfen' => '019adeff65f97225927586968691dc02',
    'Munition' => '2f40311624aea6de289c770f0bfd0ff9',
    'Zubehör' => '604131c6ae1646c98623da4fe61a739b',
    'Ausrüstung' => '019b0857613474e6a799cfa07d143c76',
];

foreach ($categories as $name => $categoryId) {
    echo "=== $name (ID: $categoryId) ===\n";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['shopware_url'] . '/api/search/seo-url',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'filter' => [
                ['type' => 'equals', 'field' => 'foreignKey', 'value' => $categoryId],
                ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page']
            ],
            'limit' => 10
        ]),
    ]);
    $response = curl_exec($ch);
    $result = json_decode($response, true);
    curl_close($ch);

    if (!empty($result['data'])) {
        foreach ($result['data'] as $seo) {
            $attrs = $seo['attributes'] ?? $seo;
            echo "  SEO Path: " . ($attrs['seoPathInfo'] ?? 'N/A') . "\n";
            echo "  Path Info: " . ($attrs['pathInfo'] ?? 'N/A') . "\n";
            echo "  Is Canonical: " . ($attrs['isCanonical'] ? 'YES' : 'NO') . "\n";
            echo "  Is Deleted: " . ($attrs['isDeleted'] ? 'YES' : 'NO') . "\n";
            echo "  Sales Channel: " . ($attrs['salesChannelId'] ?? 'N/A') . "\n";
            echo "  Language: " . ($attrs['languageId'] ?? 'N/A') . "\n";
            echo "  ---\n";
        }
    } else {
        echo "  NO SEO URL FOUND!\n";
    }
    echo "\n";
}
