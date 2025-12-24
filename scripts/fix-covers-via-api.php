<?php
/**
 * Fix product covers via Shopware Admin API
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection for reading
$host = '127.0.0.1';
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

echo "=== FIXING PRODUCT COVERS VIA ADMIN API ===\n\n";

// Get OAuth token
$baseUrl = 'http://localhost';
$tokenUrl = "$baseUrl/api/oauth/token";
$tokenData = [
    'grant_type' => 'password',
    'client_id' => 'administration',
    'username' => 'Micro the CEO',
    'password' => '100%Ravenweapon...'
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
    die("Failed to get token. HTTP $httpCode\n$response\n");
}

$tokenResponse = json_decode($response, true);
$accessToken = $tokenResponse['access_token'];
echo "Token obtained successfully\n\n";

// Get products and their media
$stmt = $pdo->query("
    SELECT
        p.product_number,
        LOWER(HEX(p.id)) as product_id,
        LOWER(HEX(pm.id)) as product_media_id,
        LOWER(HEX(pm.media_id)) as media_id,
        m.path as media_path
    FROM product p
    JOIN product_media pm ON p.id = pm.product_id
    JOIN media m ON pm.media_id = m.id
    WHERE p.product_number IN ('Basic-Kurs', 'Basic-Kurs-II', 'Basic-Kurs-III', 'Basic-Kurs-IV')
    ORDER BY p.product_number
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($products)) {
    echo "No products found with product_media. Let me check products directly...\n";

    // Check if media exists for these products
    $stmt = $pdo->query("
        SELECT
            p.product_number,
            LOWER(HEX(p.id)) as product_id,
            LOWER(HEX(p.cover)) as cover_id
        FROM product p
        WHERE p.product_number IN ('Basic-Kurs', 'Basic-Kurs-II', 'Basic-Kurs-III', 'Basic-Kurs-IV')
    ");
    $prods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($prods);
    exit(1);
}

foreach ($products as $product) {
    echo "Processing: {$product['product_number']}\n";
    echo "  Product ID: {$product['product_id']}\n";
    echo "  Product Media ID: {$product['product_media_id']}\n";
    echo "  Media path: {$product['media_path']}\n";

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
        echo "  SUCCESS: Cover updated via API\n\n";
    } else {
        echo "  ERROR: HTTP $httpCode\n";
        echo "  Response: $response\n\n";
    }
}

echo "=== DONE ===\n";
echo "\nRun: bin/console cache:clear\n";
