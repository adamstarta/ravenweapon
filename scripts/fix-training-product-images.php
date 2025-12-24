<?php
/**
 * Fix training product images - link existing media to products
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
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

$pdo = null;
try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to database\n\n";
} catch (PDOException $e) {
    die("Could not connect: " . $e->getMessage() . "\n");
}

$versionId = '0fa91ce3e96a4bc2be4bd9ce752c3425';

// Map product numbers to media file patterns
$productMediaMap = [
    'Basic-Kurs' => 'training_basic_kurs_',
    'Basic-Kurs-II' => 'training_basic_kurs_ii_',
    'Basic-Kurs-III' => 'training_basic_kurs_iii_',
    'Basic-Kurs-IV' => 'training_basic_kurs_iv_'
];

echo "=== FIXING TRAINING PRODUCT IMAGES ===\n\n";

foreach ($productMediaMap as $productNumber => $mediaPattern) {
    echo "Processing: $productNumber\n";

    // Get product ID
    $stmt = $pdo->prepare("SELECT LOWER(HEX(id)) as id FROM product WHERE product_number = ?");
    $stmt->execute([$productNumber]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo "  ERROR: Product not found\n\n";
        continue;
    }

    $productId = $product['id'];
    echo "  Product ID: $productId\n";

    // Find matching media
    $stmt = $pdo->prepare("SELECT LOWER(HEX(id)) as id, file_name, path FROM media WHERE file_name LIKE ? LIMIT 1");
    $stmt->execute([$mediaPattern . '%']);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$media) {
        echo "  ERROR: Media not found for pattern: $mediaPattern\n\n";
        continue;
    }

    $mediaId = $media['id'];
    echo "  Media ID: $mediaId ({$media['file_name']})\n";

    // Check if product_media entry exists
    $stmt = $pdo->prepare("
        SELECT LOWER(HEX(id)) as id
        FROM product_media
        WHERE LOWER(HEX(product_id)) = ? AND LOWER(HEX(media_id)) = ?
    ");
    $stmt->execute([$productId, $mediaId]);
    $existingPm = $stmt->fetch(PDO::FETCH_ASSOC);

    $productMediaId = null;
    $now = (new DateTime())->format('Y-m-d H:i:s.v');

    if ($existingPm) {
        $productMediaId = $existingPm['id'];
        echo "  Product media exists: $productMediaId\n";
    } else {
        // Create product_media entry
        $productMediaId = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("
            INSERT INTO product_media (id, version_id, product_id, product_version_id, media_id, position, created_at)
            VALUES (UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), 1, ?)
        ");
        $stmt->execute([$productMediaId, $versionId, $productId, $versionId, $mediaId, $now]);
        echo "  Created product_media: $productMediaId\n";
    }

    // Set as product cover
    $stmt = $pdo->prepare("UPDATE product SET cover = UNHEX(?) WHERE LOWER(HEX(id)) = ?");
    $stmt->execute([$productMediaId, $productId]);
    echo "  Set as cover image\n\n";
}

echo "=== DONE ===\n";
echo "\nRun: bin/console cache:clear\n";
