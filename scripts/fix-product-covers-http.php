<?php
/**
 * Fix product covers using Shopware Admin API
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$baseUrl = 'http://localhost';
$clientId = 'administration';
$clientSecret = 'admin';

// Get OAuth token
echo "=== Getting OAuth Token ===\n";
$tokenUrl = "$baseUrl/api/oauth/token";
$tokenData = [
    'grant_type' => 'password',
    'client_id' => 'administration',
    'username' => 'admin',
    'password' => 'shopware'
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($tokenData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "Failed to get token. HTTP $httpCode\n";
    echo "Response: $response\n";
    exit(1);
}

$tokenResponse = json_decode($response, true);
$accessToken = $tokenResponse['access_token'];
echo "Token obtained successfully\n\n";

// Database connection for reading
$host = 'localhost';
$dbname = 'shopware';
$user = 'root';
$password = 'root';

$envFile = '/var/www/html/.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    if (preg_match('/DATABASE_URL=mysql:\/\/([^:]+):([^@]+)@([^\/:]+)(?::(\d+))?\/(\\w+)/', $envContent, $matches)) {
        $user = $matches[1];
        $password = $matches[2];
        $host = $matches[3];
        $dbname = $matches[5];
    }
}

$pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=$dbname;charset=utf8mb4", $user, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== FIXING PRODUCT COVERS VIA API ===\n\n";

// Get products and their media
$stmt = $pdo->query("
    SELECT
        p.product_number,
        LOWER(HEX(p.id)) as product_id,
        LOWER(HEX(pm.id)) as product_media_id
    FROM product p
    JOIN product_media pm ON p.id = pm.product_id
    WHERE p.product_number IN ('Basic-Kurs', 'Basic-Kurs-II', 'Basic-Kurs-III', 'Basic-Kurs-IV')
    ORDER BY p.product_number
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($products as $product) {
    echo "Processing: {$product['product_number']}\n";
    echo "  Product ID: {$product['product_id']}\n";
    echo "  Product Media ID: {$product['product_media_id']}\n";

    // Update product via API
    $updateUrl = "$baseUrl/api/product/{$product['product_id']}";
    $updateData = [
        'coverId' => $product['product_media_id']
    ];

    $ch = curl_init($updateUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 204 || $httpCode === 200) {
        echo "  SUCCESS: Cover updated\n\n";
    } else {
        echo "  ERROR: HTTP $httpCode\n";
        echo "  Response: $response\n\n";
    }
}

echo "=== DONE ===\n";
