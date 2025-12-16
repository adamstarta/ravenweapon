<?php
/**
 * Upload ALL remaining Snigel images to Shopware (Fixed Version)
 * Uses proper versioning for product_media
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = '127.0.0.1';
$dbname = 'shopware';
$username = 'root';
$password = 'root';

$imageDir = '/tmp/snigel-images/';
$jsonFile = '/tmp/products-with-variants.json';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to database.\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

// Load JSON data
if (!file_exists($jsonFile)) {
    die("JSON file not found: $jsonFile\n");
}
$products = json_decode(file_get_contents($jsonFile), true);
echo "Loaded " . count($products) . " products from JSON.\n";

// Get the live version ID
$stmt = $pdo->query("SELECT HEX(id) as id FROM `version` WHERE name = 'live' LIMIT 1");
$versionRow = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$versionRow) {
    // Try to get from product table directly
    $stmt = $pdo->query("SELECT DISTINCT HEX(version_id) as id FROM product LIMIT 1");
    $versionRow = $stmt->fetch(PDO::FETCH_ASSOC);
}
$liveVersionId = $versionRow['id'] ?? null;
echo "Live version ID: " . ($liveVersionId ?: 'NOT FOUND') . "\n";

if (!$liveVersionId) {
    die("Could not find live version ID\n");
}

// Get default folder ID for product media
$stmt = $pdo->query("SELECT HEX(id) as id FROM media_default_folder WHERE entity = 'product' LIMIT 1");
$defaultFolder = $stmt->fetch(PDO::FETCH_ASSOC);
$defaultFolderId = $defaultFolder['id'] ?? null;

$folderId = null;
if ($defaultFolderId) {
    $stmt = $pdo->prepare("SELECT HEX(id) as id FROM media_folder WHERE default_folder_id = UNHEX(?)");
    $stmt->execute([$defaultFolderId]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);
    $folderId = $folder['id'] ?? null;
}
echo "Media folder ID: " . ($folderId ?: 'default') . "\n\n";

// Stats
$uploaded = 0;
$skipped = 0;
$errors = 0;
$notFound = 0;

// Process each product
foreach ($products as $product) {
    $slug = $product['slug'] ?? '';
    $productNumber = 'SN-' . $slug;
    $productName = $product['name'] ?? 'Unknown';

    // Get local images (exclude icons)
    $localImages = array_filter($product['local_images'] ?? [], function($img) {
        return strpos($img, 'cropped-snigel_icon') === false &&
               (substr($img, -4) === '.jpg' || substr($img, -4) === '.png');
    });

    if (empty($localImages)) {
        continue;
    }

    // Find product in Shopware
    $stmt = $pdo->prepare("
        SELECT HEX(p.id) as id, HEX(p.version_id) as version_id
        FROM product p
        WHERE p.product_number = ?
        LIMIT 1
    ");
    $stmt->execute([$productNumber]);
    $shopwareProduct = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shopwareProduct) {
        $notFound++;
        continue;
    }

    $productId = $shopwareProduct['id'];
    $productVersionId = $shopwareProduct['version_id'];

    // Get existing media for this product
    $stmt = $pdo->prepare("
        SELECT m.file_name
        FROM product_media pm
        JOIN media m ON pm.media_id = m.id
        WHERE pm.product_id = UNHEX(?)
    ");
    $stmt->execute([$productId]);
    $existingMedia = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $position = count($existingMedia);
    $uploadedForProduct = 0;

    foreach ($localImages as $imageName) {
        $imagePath = $imageDir . $imageName;

        // Check if already exists
        $baseName = pathinfo($imageName, PATHINFO_FILENAME);
        $alreadyExists = false;
        foreach ($existingMedia as $existing) {
            if (stripos($existing, $baseName) !== false) {
                $alreadyExists = true;
                break;
            }
        }

        if ($alreadyExists) {
            $skipped++;
            continue;
        }

        if (!file_exists($imagePath)) {
            $errors++;
            continue;
        }

        // Create media entry
        $mediaId = bin2hex(random_bytes(16));
        $now = date('Y-m-d H:i:s.u');
        $mimeType = 'image/jpeg';
        $extension = pathinfo($imageName, PATHINFO_EXTENSION) ?: 'jpg';
        $fileSize = filesize($imagePath);

        try {
            // Insert media record
            $stmt = $pdo->prepare("
                INSERT INTO media (id, media_folder_id, mime_type, file_extension, file_size, file_name, created_at, updated_at)
                VALUES (UNHEX(?), " . ($folderId ? "UNHEX(?)" : "NULL") . ", ?, ?, ?, ?, ?, ?)
            ");

            $params = [$mediaId];
            if ($folderId) $params[] = $folderId;
            $params = array_merge($params, [$mimeType, $extension, $fileSize, $baseName, $now, $now]);
            $stmt->execute($params);

            // Copy file to Shopware media location
            $targetDir = "/var/www/html/public/media/" . substr($mediaId, 0, 2) . "/" . substr($mediaId, 2, 2) . "/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $targetPath = $targetDir . $baseName . "." . $extension;
            copy($imagePath, $targetPath);

            // Update media with path
            $relativePath = "media/" . substr($mediaId, 0, 2) . "/" . substr($mediaId, 2, 2) . "/" . $baseName . "." . $extension;
            $stmt = $pdo->prepare("UPDATE media SET path = ? WHERE id = UNHEX(?)");
            $stmt->execute([$relativePath, $mediaId]);

            // Link to product using the product's version_id
            $productMediaId = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("
                INSERT INTO product_media (id, version_id, product_id, product_version_id, media_id, position, created_at)
                VALUES (UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), ?, ?)
            ");
            $stmt->execute([$productMediaId, $productVersionId, $productId, $productVersionId, $mediaId, $position, $now]);

            $position++;
            $uploaded++;
            $uploadedForProduct++;

        } catch (PDOException $e) {
            echo "ERROR uploading $imageName: " . $e->getMessage() . "\n";
            $errors++;
        }
    }

    if ($uploadedForProduct > 0) {
        echo "UPLOADED: $productNumber - $uploadedForProduct new images (total: $position)\n";
    }
}

echo "\n=== Upload Complete ===\n";
echo "Uploaded: $uploaded\n";
echo "Skipped (existing): $skipped\n";
echo "Errors: $errors\n";
echo "Products not found: $notFound\n";

// Clear cache
echo "\nClearing cache...\n";
exec('cd /var/www/html && bin/console cache:clear 2>&1', $output);
echo implode("\n", $output) . "\n";
