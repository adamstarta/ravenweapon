<?php
/**
 * Upload RAPAX product images to Shopware - Database version
 * Runs inside Docker container with direct database access
 */

// Get database connection
$host = '127.0.0.1';
$dbname = 'shopware';
$user = 'root';
$pass = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage() . "\n");
}

// API config
$apiUrl = 'http://localhost/api';
$apiUser = 'Micro the CEO';
$apiPass = '100%Ravenweapon...';

// Get access token
function getToken($apiUrl, $apiUser, $apiPass) {
    $ch = curl_init("$apiUrl/oauth/token");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => $apiUser,
            'password' => $apiPass,
        ]),
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function apiRequest($method, $url, $token, $data = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($response, true)];
}

function downloadImage($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return ($code === 200 && $data) ? ['data' => $data, 'type' => $type] : null;
}

function isRealImage($url) {
    $exclude = ['raven-logo.png', 'placeholder/', 'variant.png', 'product.png'];
    foreach ($exclude as $p) {
        if (stripos($url, $p) !== false) return false;
    }
    return true;
}

function generateUuid() {
    return sprintf('%04x%04x%04x%04x%04x%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
}

echo "=== RAPAX IMAGE UPLOAD (DATABASE VERSION) ===\n\n";

$token = getToken($apiUrl, $apiUser, $apiPass);
if (!$token) {
    die("Failed to get API token\n");
}
echo "Got API token\n\n";

// Load JSON
$jsonPath = '/tmp/rapax-products.json';
$jsonData = json_decode(file_get_contents($jsonPath), true);
if (!$jsonData) {
    die("Failed to load rapax-products.json\n");
}
echo "Loaded " . count($jsonData['products']) . " products from JSON\n\n";

// Get all RAPAX/CARACAL products from database
$stmt = $pdo->query("
    SELECT LOWER(HEX(p.id)) as id, pt.name,
           (SELECT COUNT(*) FROM product_media WHERE product_id = p.id) as media_count
    FROM product p
    JOIN product_translation pt ON p.id = pt.product_id
    WHERE pt.name LIKE '%RAPAX%' OR pt.name LIKE '%CARACAL%' OR pt.name LIKE '%Lynx%'
");
$shopwareProducts = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $shopwareProducts[strtolower($row['name'])] = $row;
}
echo "Found " . count($shopwareProducts) . " RAPAX/CARACAL products in database\n\n";

// Process each JSON product
$uploadCount = 0;
$skipCount = 0;
$errorCount = 0;

foreach ($jsonData['products'] as $jsonProduct) {
    $name = $jsonProduct['name'];
    $images = array_filter($jsonProduct['images'] ?? [], 'isRealImage');

    if (empty($images)) {
        echo "SKIP: $name - no real images\n";
        $skipCount++;
        continue;
    }

    // Find in Shopware
    $swProduct = $shopwareProducts[strtolower($name)] ?? null;
    if (!$swProduct) {
        echo "NOT FOUND: $name\n";
        $errorCount++;
        continue;
    }

    // Check if already has images
    if ($swProduct['media_count'] > 0) {
        echo "HAS IMAGES: $name ({$swProduct['media_count']} images)\n";
        $skipCount++;
        continue;
    }

    echo "\nUploading for: $name (ID: {$swProduct['id']})\n";
    echo "  Real images: " . count($images) . "\n";

    $position = 0;
    foreach ($images as $imageUrl) {
        $filename = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_FILENAME);
        $ext = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';

        echo "  Downloading: $filename.$ext... ";
        $imgData = downloadImage($imageUrl);
        if (!$imgData) {
            echo "FAILED\n";
            continue;
        }
        echo "OK\n";

        // Create media entry
        $mediaId = generateUuid();
        $result = apiRequest('POST', "$apiUrl/media", $token, ['id' => $mediaId]);
        if ($result['code'] >= 300) {
            echo "  Failed to create media: " . json_encode($result['body']) . "\n";
            continue;
        }

        // Upload file
        $uploadUrl = "$apiUrl/_action/media/$mediaId/upload?extension=$ext&fileName=" . urlencode($filename);
        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $imgData['data'],
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: ' . ($imgData['type'] ?: 'image/jpeg'),
            ],
        ]);
        $uploadResponse = curl_exec($ch);
        $uploadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($uploadCode >= 300) {
            echo "  Upload failed: $uploadCode\n";
            continue;
        }
        echo "  Uploaded: $filename.$ext\n";

        // Associate with product
        $productMediaId = generateUuid();
        $result = apiRequest('POST', "$apiUrl/product-media", $token, [
            'id' => $productMediaId,
            'productId' => $swProduct['id'],
            'mediaId' => $mediaId,
            'position' => $position,
        ]);

        if ($result['code'] >= 300) {
            echo "  Failed to associate: " . json_encode($result['body']) . "\n";
            continue;
        }

        // Set as cover if first
        if ($position === 0) {
            apiRequest('PATCH', "$apiUrl/product/{$swProduct['id']}", $token, [
                'coverId' => $productMediaId
            ]);
            echo "  Set as cover\n";
        }

        $position++;
        $uploadCount++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Uploaded: $uploadCount images\n";
echo "Skipped: $skipCount products\n";
echo "Errors: $errorCount products\n";
