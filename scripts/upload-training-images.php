<?php
/**
 * Upload training course images from S3 to Shopware
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

$languageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
$versionId = '0fa91ce3e96a4bc2be4bd9ce752c3425';

// Image URLs from Excel
$imageUrls = [
    'Basic-Kurs' => 'https://makaris-prod-public.s3.ewstorage.ch/45684/ChatGPT-Image-29.-Okt.-2025%2C-15_02_48.png',
    'Basic-Kurs-II' => 'https://makaris-prod-public.s3.ewstorage.ch/45685/ChatGPT-Image-29.-Okt.-2025%2C-15_06_14.png',
    'Basic-Kurs-III' => 'https://makaris-prod-public.s3.ewstorage.ch/45686/ChatGPT-Image-29.-Okt.-2025%2C-15_08_34.png',
    'Basic-Kurs-IV' => 'https://makaris-prod-public.s3.ewstorage.ch/45687/ChatGPT-Image-29.-Okt.-2025%2C-15_13_15.png'
];

echo "=== Uploading Training Course Images ===\n\n";

// Get media folder ID for products
$stmt = $pdo->query("SELECT LOWER(HEX(id)) as id FROM media_folder WHERE name = 'Product Media' LIMIT 1");
$folder = $stmt->fetch(PDO::FETCH_ASSOC);
$folderId = $folder ? $folder['id'] : null;
echo "Media folder ID: " . ($folderId ?? 'NULL') . "\n\n";

foreach ($imageUrls as $productNumber => $imageUrl) {
    echo "Processing: $productNumber\n";
    echo "  URL: $imageUrl\n";

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

    // Check if product already has a cover
    $stmt = $pdo->prepare("SELECT LOWER(HEX(cover)) as cover FROM product WHERE LOWER(HEX(id)) = ?");
    $stmt->execute([$productId]);
    $existingCover = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingCover && $existingCover['cover']) {
        echo "  SKIP: Already has cover image\n\n";
        continue;
    }

    // Download image with proper context
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);

    $imageData = @file_get_contents($imageUrl, false, $context);
    if (!$imageData) {
        echo "  ERROR: Could not download image\n\n";
        continue;
    }

    echo "  Downloaded: " . strlen($imageData) . " bytes\n";

    // Create media entry
    $mediaId = bin2hex(random_bytes(16));
    $now = (new DateTime())->format('Y-m-d H:i:s.v');
    $fileName = 'training_' . strtolower(str_replace(['-', ' '], '_', $productNumber)) . '_' . substr($mediaId, 0, 8);

    // Detect mime type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($imageData);
    $extension = 'png';
    if (strpos($mimeType, 'jpeg') !== false) {
        $extension = 'jpg';
    }

    // Insert media
    $sql = "INSERT INTO media (id, media_folder_id, mime_type, file_extension, file_size, uploaded_at, created_at)
            VALUES (UNHEX(?), " . ($folderId ? "UNHEX(?)" : "NULL") . ", ?, ?, ?, ?, ?)";
    $params = [$mediaId];
    if ($folderId) $params[] = $folderId;
    $params[] = $mimeType;
    $params[] = $extension;
    $params[] = strlen($imageData);
    $params[] = $now;
    $params[] = $now;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Insert media translation
    $stmt = $pdo->prepare("
        INSERT INTO media_translation (media_id, language_id, title, alt, created_at)
        VALUES (UNHEX(?), UNHEX(?), ?, ?, ?)
    ");
    $stmt->execute([$mediaId, $languageId, $productNumber, $productNumber . ' Kursbild', $now]);

    // Save file to filesystem
    $mediaBasePath = "/var/www/html/public/media";
    $hashPath = substr($mediaId, 0, 2) . '/' . substr($mediaId, 2, 2);
    $fullDir = "$mediaBasePath/$hashPath";

    if (!is_dir($fullDir)) {
        mkdir($fullDir, 0755, true);
    }

    $filePath = "$fullDir/{$fileName}.{$extension}";
    file_put_contents($filePath, $imageData);
    echo "  Saved: $filePath\n";

    // Update media with path info
    $relativePath = "media/$hashPath/{$fileName}.{$extension}";
    $stmt = $pdo->prepare("UPDATE media SET file_name = ?, path = ? WHERE LOWER(HEX(id)) = ?");
    $stmt->execute([$fileName, $relativePath, $mediaId]);

    // Create product_media entry
    $productMediaId = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("
        INSERT INTO product_media (id, version_id, product_id, product_version_id, media_id, position, created_at)
        VALUES (UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), 1, ?)
    ");
    $stmt->execute([$productMediaId, $versionId, $productId, $versionId, $mediaId, $now]);

    // Set as product cover
    $stmt = $pdo->prepare("UPDATE product SET cover = UNHEX(?) WHERE LOWER(HEX(id)) = ?");
    $stmt->execute([$productMediaId, $productId]);

    echo "  OK: Image uploaded and set as cover\n\n";
}

echo "=== Done ===\n";
echo "\nClear cache: bin/console cache:clear\n";
