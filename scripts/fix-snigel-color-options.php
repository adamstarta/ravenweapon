<?php
/**
 * Fix Snigel product color options - add missing colors from scraped data
 * This script updates products that are missing color variants
 */

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=shopware', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Load scraped data
$jsonPath = __DIR__ . '/snigel-data/products-with-variants.json';
if (!file_exists($jsonPath)) {
    echo "ERROR: Cannot find scraped data file\n";
    exit(1);
}

$scrapedData = json_decode(file_get_contents($jsonPath), true);
echo "=== Fixing Snigel Color Options ===\n\n";

// Build scraped data lookup by normalized name
$scrapedLookup = [];
foreach ($scrapedData as $product) {
    if (!empty($product['colorOptions'])) {
        $name = $product['name'] ?? '';
        $normalized = normalizeProductName($name);
        $scrapedLookup[$normalized] = [
            'name' => $name,
            'colorOptions' => $product['colorOptions'],
            'hasColorVariants' => $product['hasColorVariants'] ?? false,
        ];
    }
}

function normalizeProductName($name) {
    // Remove version numbers, clean up for matching
    $name = strtolower(trim($name));
    $name = preg_replace('/\s*[-]?\s*\d+(\.\d+)?\s*$/', '', $name); // Remove trailing version
    $name = preg_replace('/\s+/', ' ', $name);
    return $name;
}

function isValidColorOption($colorOptions) {
    // Check if color options are actual colors (not sizes)
    $sizePatterns = [
        '/^size\s*\d/i',
        '/^(xs|s|m|l|xl|xxl|xxxl)$/i',
        '/^(small|medium|large|xlarge|xsmall|xxl)$/i',
        '/^\d+l$/i',  // 30L, 40L
        '/^\d+\.\d+$/i',  // 5.56, 7.62
        '/^[a-z]{2}\d+=/', // IJ3=, KL4=
        '/^(briefs|complete|coverall|vest)$/i',
        '/^\d{3}$/', // 241, 242
    ];

    foreach ($colorOptions as $opt) {
        $colorName = $opt['name'] ?? '';
        foreach ($sizePatterns as $pattern) {
            if (preg_match($pattern, $colorName)) {
                return false; // This is a size variant, not color
            }
        }
    }
    return true;
}

// Valid color names
$validColors = ['black', 'grey', 'gray', 'olive', 'multicam', 'coyote', 'khaki', 'white', 'navy', 'ranger green', 'highvis yellow', 'highvis', 'clear', 'various'];

function hasValidColors($colorOptions) {
    global $validColors;
    foreach ($colorOptions as $opt) {
        $colorName = strtolower($opt['name'] ?? '');
        foreach ($validColors as $valid) {
            if (strpos($colorName, $valid) !== false) {
                return true;
            }
        }
    }
    return false;
}

// Get all Shopware Snigel products
$stmt = $pdo->query("
    SELECT
        LOWER(HEX(p.id)) as product_id,
        p.id as product_id_bin,
        p.product_number,
        pt.name,
        pt.custom_fields,
        LOWER(HEX(pt.language_id)) as language_id
    FROM product p
    JOIN product_translation pt ON p.id = pt.product_id
    WHERE p.product_number LIKE 'SN-%'
    ORDER BY pt.name
");

$shopwareProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($shopwareProducts) . " Snigel products in Shopware\n\n";

// Prepare update statement
$updateStmt = $pdo->prepare("
    UPDATE product_translation
    SET custom_fields = :custom_fields
    WHERE product_id = :product_id AND language_id = :language_id
");

$fixed = 0;
$skipped = 0;
$sizeVariants = 0;

echo "=== Processing Products ===\n\n";

foreach ($shopwareProducts as $swProduct) {
    $swName = $swProduct['name'];
    $swNormalized = normalizeProductName($swName);
    $productNumber = $swProduct['product_number'];

    // Parse current custom fields
    $customFields = json_decode($swProduct['custom_fields'] ?? '{}', true) ?: [];
    $currentColorOptions = [];

    if (!empty($customFields['snigel_color_options'])) {
        $raw = $customFields['snigel_color_options'];
        if (is_string($raw)) {
            $currentColorOptions = json_decode($raw, true) ?: [];
        } else {
            $currentColorOptions = is_array($raw) ? $raw : [];
        }
    }

    // Find matching scraped product
    $matchedScraped = null;
    $matchScore = 0;

    foreach ($scrapedLookup as $scrapedNorm => $scraped) {
        // Try exact normalized match
        if ($scrapedNorm === $swNormalized) {
            $matchedScraped = $scraped;
            $matchScore = 100;
            break;
        }

        // Try partial match (one contains the other)
        if (strlen($scrapedNorm) > 5 && strlen($swNormalized) > 5) {
            if (strpos($swNormalized, $scrapedNorm) !== false || strpos($scrapedNorm, $swNormalized) !== false) {
                similar_text($swNormalized, $scrapedNorm, $percent);
                if ($percent > $matchScore && $percent > 60) {
                    $matchedScraped = $scraped;
                    $matchScore = $percent;
                }
            }
        }
    }

    if (!$matchedScraped) {
        continue; // No matching scraped product
    }

    $scrapedColorOptions = $matchedScraped['colorOptions'];

    // Check if scraped colors are actually sizes (skip these)
    if (!isValidColorOption($scrapedColorOptions) || !hasValidColors($scrapedColorOptions)) {
        $sizeVariants++;
        echo "[SKIP-SIZE] $swName - Colors are sizes: " . implode(', ', array_map(function($c) { return $c['name']; }, $scrapedColorOptions)) . "\n";
        continue;
    }

    // Get current color names
    $currentColors = array_map(function($c) { return strtolower($c['name'] ?? ''); }, $currentColorOptions);
    $scrapedColors = array_map(function($c) { return strtolower($c['name'] ?? ''); }, $scrapedColorOptions);

    sort($currentColors);
    sort($scrapedColors);

    // Check if we need to update
    if ($currentColors === $scrapedColors) {
        continue; // Already matches
    }

    // Check if scraped has more colors
    $missingColors = array_diff($scrapedColors, $currentColors);

    if (empty($missingColors)) {
        $skipped++;
        continue; // Shopware has all or more colors
    }

    echo "[FIX] $swName ($productNumber)\n";
    echo "  Current: " . implode(', ', $currentColors) . "\n";
    echo "  Scraped: " . implode(', ', $scrapedColors) . "\n";
    echo "  Missing: " . implode(', ', $missingColors) . "\n";

    // Merge color options - keep existing and add missing from scraped
    $newColorOptions = $currentColorOptions;
    foreach ($scrapedColorOptions as $scrapedOpt) {
        $scrapedColorName = strtolower($scrapedOpt['name'] ?? '');
        $exists = false;
        foreach ($currentColorOptions as $currentOpt) {
            if (strtolower($currentOpt['name'] ?? '') === $scrapedColorName) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $newColorOptions[] = $scrapedOpt;
        }
    }

    // Update custom fields
    $customFields['snigel_color_options'] = json_encode($newColorOptions);
    $customFields['snigel_has_colors'] = true;
    $customFields['snigel_has_color_variants'] = count($newColorOptions) > 1;

    // Save to database
    $updateStmt->execute([
        'custom_fields' => json_encode($customFields),
        'product_id' => $swProduct['product_id_bin'],
        'language_id' => hex2bin($swProduct['language_id']),
    ]);

    echo "  -> Updated with " . count($newColorOptions) . " colors\n\n";
    $fixed++;
}

echo "\n=== SUMMARY ===\n";
echo "Products fixed: $fixed\n";
echo "Products skipped (size variants): $sizeVariants\n";
echo "Products skipped (other): $skipped\n";
