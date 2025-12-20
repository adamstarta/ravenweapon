<?php
/**
 * Resize and crop the email logo to remove whitespace
 * Creates an email-optimized PNG from the 4000x4000 original
 */

$inputPath = '/var/www/html/custom/plugins/RavenTheme/src/Resources/public/assets/email-logo.png';
$outputPath = '/tmp/email-logo-optimized.png';
$finalPath = '/var/www/html/custom/plugins/RavenTheme/src/Resources/public/assets/email-logo-optimized.png';

echo "Loading original image...\n";
$src = imagecreatefrompng($inputPath);
if (!$src) {
    die("Failed to load image\n");
}

$srcWidth = imagesx($src);
$srcHeight = imagesy($src);
echo "Original size: {$srcWidth}x{$srcHeight}\n";

// Find the bounding box of non-transparent/non-white pixels
$minX = $srcWidth;
$minY = $srcHeight;
$maxX = 0;
$maxY = 0;

echo "Scanning for content boundaries (checking alpha + color)...\n";
imagealphablending($src, false);
imagesavealpha($src, true);

for ($y = 0; $y < $srcHeight; $y += 3) {
    for ($x = 0; $x < $srcWidth; $x += 3) {
        $rgba = imagecolorat($src, $x, $y);
        $a = ($rgba >> 24) & 0x7F; // 0 = opaque, 127 = transparent
        $r = ($rgba >> 16) & 0xFF;
        $g = ($rgba >> 8) & 0xFF;
        $b = $rgba & 0xFF;

        // Check if pixel has content (not fully transparent AND not pure white)
        $isTransparent = ($a > 120); // nearly transparent
        $isWhite = ($r > 250 && $g > 250 && $b > 250);

        if (!$isTransparent && !$isWhite) {
            if ($x < $minX) $minX = $x;
            if ($y < $minY) $minY = $y;
            if ($x > $maxX) $maxX = $x;
            if ($y > $maxY) $maxY = $y;
        }
    }
}

echo "Raw bounds: ({$minX}, {$minY}) to ({$maxX}, {$maxY})\n";

// Add padding
$padding = 60;
$minX = max(0, $minX - $padding);
$minY = max(0, $minY - $padding);
$maxX = min($srcWidth, $maxX + $padding);
$maxY = min($srcHeight, $maxY + $padding);

$cropWidth = $maxX - $minX;
$cropHeight = $maxY - $minY;
echo "Crop with padding: {$cropWidth}x{$cropHeight}\n";

// Target width for email (higher res for quality)
$targetWidth = 800;
$scale = $targetWidth / $cropWidth;
$targetHeight = (int)($cropHeight * $scale);

echo "Output size: {$targetWidth}x{$targetHeight}\n";

// Create output image with white background
$dst = imagecreatetruecolor($targetWidth, $targetHeight);
$white = imagecolorallocate($dst, 255, 255, 255);
imagefill($dst, 0, 0, $white);

// Copy and resize
imagecopyresampled(
    $dst, $src,
    0, 0,
    $minX, $minY,
    $targetWidth, $targetHeight,
    $cropWidth, $cropHeight
);

// Save to temp location
imagepng($dst, $outputPath, 9);

$fileSize = filesize($outputPath);
echo "Saved to: {$outputPath}\n";
echo "File size: " . number_format($fileSize) . " bytes (" . round($fileSize/1024, 1) . " KB)\n";

// Move to final location
copy($outputPath, $finalPath);
chmod($finalPath, 0644);
echo "Copied to: {$finalPath}\n";

imagedestroy($src);
imagedestroy($dst);

echo "\nDone!\n";
