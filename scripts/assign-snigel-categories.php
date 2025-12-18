<?php
/**
 * Assign Snigel Products to Single Categories
 *
 * Uses existing scraped data to assign each product to exactly ONE category
 * based on priority (most specific category wins).
 *
 * Usage:
 *   php assign-snigel-categories.php              # Dry run - preview changes
 *   php assign-snigel-categories.php --execute    # Actually make changes
 */

$baseUrl = 'https://ortak.ch/api';
$clientId = 'SWIAC3HJVHFJMHQYRWRUM1E1SG';
$clientSecret = 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg';

$dryRun = !in_array('--execute', $argv);

// Priority mapping: Lower number = higher priority (more specific)
// Products in multiple categories will be assigned to the highest priority category
$categoryPriority = [
    'Bags & Backpacks' => 1,
    'Vests & Chest Rigs' => 2,
    'Belts' => 3,
    'Holders & Pouches' => 4,
    'Medical Gear' => 5,
    'Slings & Holsters' => 6,
    'Ballistic Protection' => 7,
    'Leg Panels' => 8,
    'K9 Gear' => 9,
    'Sniper Gear' => 10,
    'Source Hydration' => 11,
    'Tactical Clothing' => 12,
    'Patches' => 13,
    'Covert Gear' => 14,
    'Police Gear' => 15,
    'Admin Gear' => 16,
    'Duty Gear' => 17,
    'HighVis' => 18,
    'Multicam' => 19,
    'Tactical Gear' => 20,
    'Miscellaneous' => 21,
];

// English to German category name mapping (leaf categories only)
$categoryMapping = [
    'Tactical Gear' => 'Taktische Ausrüstung',
    'Tactical Clothing' => 'Taktische Bekleidung',
    'Vests & Chest Rigs' => 'Westen & Chest Rigs',
    'Bags & Backpacks' => 'Taschen & Rucksäcke',
    'Belts' => 'Gürtel',
    'Ballistic Protection' => 'Ballistischer Schutz',
    'Slings & Holsters' => 'Tragegurte & Holster',
    'Medical Gear' => 'Medizinische Ausrüstung',
    'Police Gear' => 'Polizeiausrüstung',
    'Admin Gear' => 'Verwaltungsausrüstung',
    'Holders & Pouches' => 'Halter & Taschen',
    'Patches' => 'Patches',
    'K9 Gear' => 'K9 Ausrüstung',
    'Leg Panels' => 'Beinpaneele',
    'Duty Gear' => 'Dienstausrüstung',
    'Covert Gear' => 'Verdeckte Ausrüstung',
    'Sniper Gear' => 'Scharfschützen-Ausrüstung',
    'Source Hydration' => 'Source Hydration',
    'Miscellaneous' => 'Verschiedenes',
    'HighVis' => 'Warnschutz',
    'Multicam' => 'Multicam',
];

function getAccessToken($baseUrl, $clientId, $clientSecret) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/oauth/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['access_token'];
}

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response
    ];
}

function slugify($text) {
    $text = str_replace(['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'], ['ae', 'oe', 'ue', 'ss', 'ae', 'oe', 'ue'], $text);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

function selectBestCategory($categories, $categoryPriority) {
    $bestCategory = null;
    $bestPriority = PHP_INT_MAX;

    foreach ($categories as $cat) {
        $priority = $categoryPriority[$cat] ?? 999;
        if ($priority < $bestPriority) {
            $bestPriority = $priority;
            $bestCategory = $cat;
        }
    }

    return $bestCategory;
}

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║     ASSIGN SNIGEL PRODUCTS TO SINGLE CATEGORIES           ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
echo "║  Mode: " . ($dryRun ? "DRY RUN (preview only)        " : "EXECUTE (making changes)      ") . "             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

if ($dryRun) {
    echo "Run with --execute flag to actually make changes.\n\n";
}

// Load existing scraped data
$mappingFile = __DIR__ . '/snigel-data/product-categories-mapping.json';
if (!file_exists($mappingFile)) {
    echo "ERROR: Missing $mappingFile\n";
    echo "Run snigel-multi-category-scraper.js first.\n";
    exit(1);
}

$productCategoriesMapping = json_decode(file_get_contents($mappingFile), true);
echo "1. Loaded " . count($productCategoriesMapping) . " products from mapping file\n\n";

// Get API token
$token = getAccessToken($baseUrl, $clientId, $clientSecret);
echo "2. Got API token\n\n";

// Get sales channel
echo "3. Getting sales channel...\n";
$scResponse = apiRequest($baseUrl, $token, 'POST', '/search/sales-channel', ['limit' => 10]);
$salesChannelId = null;
$languageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';

if (isset($scResponse['body']['data'])) {
    foreach ($scResponse['body']['data'] as $sc) {
        $attrs = $sc['attributes'] ?? $sc;
        if (($attrs['typeId'] ?? '') === '8a243080f92e4c719546314b577cf82b') {
            $salesChannelId = $sc['id'];
            break;
        }
    }
}
echo "   Sales Channel: $salesChannelId\n\n";

// Get all Shopware categories and build lookup by name
echo "4. Fetching Shopware categories...\n";
$catResponse = apiRequest($baseUrl, $token, 'POST', '/search/category', ['limit' => 500]);
$shopwareCategories = [];

if (isset($catResponse['body']['data'])) {
    foreach ($catResponse['body']['data'] as $cat) {
        $attrs = $cat['attributes'] ?? $cat;
        $name = $attrs['translated']['name'] ?? $attrs['name'] ?? '';
        $breadcrumb = $attrs['translated']['breadcrumb'] ?? $attrs['breadcrumb'] ?? [];

        $shopwareCategories[$name] = [
            'id' => $cat['id'],
            'name' => $name,
            'breadcrumb' => $breadcrumb,
            'path' => implode(' > ', array_slice($breadcrumb, 1))
        ];
    }
}
echo "   Found " . count($shopwareCategories) . " categories\n\n";

// Verify all German category names exist in Shopware
echo "5. Verifying category mapping...\n";
$missingCategories = [];
foreach ($categoryMapping as $english => $german) {
    if (!isset($shopwareCategories[$german])) {
        $missingCategories[] = "$english → $german";
    }
}

if (!empty($missingCategories)) {
    echo "   WARNING: Some German categories not found in Shopware:\n";
    foreach ($missingCategories as $missing) {
        echo "   - $missing\n";
    }
    echo "\n";
}

// Get all Shopware products
echo "6. Fetching Shopware products...\n";
$productsResponse = apiRequest($baseUrl, $token, 'POST', '/search/product', [
    'limit' => 500,
    'associations' => ['categories' => []],
    'includes' => [
        'product' => ['id', 'name', 'productNumber', 'categories', 'translated'],
        'category' => ['id', 'name']
    ]
]);

$shopwareProducts = [];
if (isset($productsResponse['body']['data'])) {
    foreach ($productsResponse['body']['data'] as $prod) {
        $attrs = $prod['attributes'] ?? $prod;
        $name = $attrs['translated']['name'] ?? $attrs['name'] ?? '';

        // Get current categories
        $currentCats = [];
        if (isset($prod['relationships']['categories']['data'])) {
            foreach ($prod['relationships']['categories']['data'] as $catRef) {
                $currentCats[] = $catRef['id'];
            }
        }

        $shopwareProducts[$name] = [
            'id' => $prod['id'],
            'name' => $name,
            'productNumber' => $attrs['productNumber'] ?? '',
            'currentCategories' => $currentCats
        ];
    }
}
echo "   Found " . count($shopwareProducts) . " products in Shopware\n\n";

// Process each product
echo "7. Processing products...\n\n";

$updated = 0;
$skipped = 0;
$notFound = 0;
$errors = 0;
$changes = [];

foreach ($productCategoriesMapping as $productName => $snigelCategories) {
    // Select best category based on priority
    $bestEnglishCategory = selectBestCategory($snigelCategories, $categoryPriority);

    if (!$bestEnglishCategory) {
        echo "   SKIP [$productName]: No valid category\n";
        $skipped++;
        continue;
    }

    // Get German category name
    $germanCategoryName = $categoryMapping[$bestEnglishCategory] ?? null;
    if (!$germanCategoryName) {
        echo "   SKIP [$productName]: No German mapping for '$bestEnglishCategory'\n";
        $skipped++;
        continue;
    }

    // Get Shopware category ID
    $shopwareCat = $shopwareCategories[$germanCategoryName] ?? null;
    if (!$shopwareCat) {
        echo "   SKIP [$productName]: German category '$germanCategoryName' not found in Shopware\n";
        $skipped++;
        continue;
    }

    // Find product in Shopware (try exact match first, then fuzzy)
    $shopwareProduct = $shopwareProducts[$productName] ?? null;

    // Try without version suffix if not found
    if (!$shopwareProduct) {
        foreach ($shopwareProducts as $swName => $swProd) {
            // Normalize both names for comparison
            $normalizedSnigel = strtolower(preg_replace('/\s*-?\d+\.?\d*$/', '', $productName));
            $normalizedShopware = strtolower(preg_replace('/\s*-?\d+\.?\d*$/', '', $swName));

            if ($normalizedSnigel === $normalizedShopware ||
                strpos(strtolower($swName), strtolower($productName)) !== false ||
                strpos(strtolower($productName), strtolower($swName)) !== false) {
                $shopwareProduct = $swProd;
                break;
            }
        }
    }

    if (!$shopwareProduct) {
        $notFound++;
        continue; // Silent skip - product not in Shopware
    }

    // Check if already in correct single category
    $currentCats = $shopwareProduct['currentCategories'];
    if (count($currentCats) === 1 && $currentCats[0] === $shopwareCat['id']) {
        continue; // Already correct
    }

    $changes[] = [
        'productName' => $productName,
        'shopwareProduct' => $shopwareProduct,
        'snigelCategories' => $snigelCategories,
        'selectedCategory' => $bestEnglishCategory,
        'germanCategory' => $germanCategoryName,
        'shopwareCategoryId' => $shopwareCat['id'],
        'categoryPath' => $shopwareCat['path']
    ];

    echo "   [{$shopwareProduct['name']}]\n";
    echo "      Snigel categories: " . implode(', ', $snigelCategories) . "\n";
    echo "      Selected: $bestEnglishCategory → $germanCategoryName\n";
    echo "      Path: {$shopwareCat['path']}\n";

    if (!$dryRun) {
        // Step 1: Build sync payload to DELETE old categories and ADD new one
        $syncPayload = [];

        // Delete all existing category associations
        $currentCats = $shopwareProduct['currentCategories'];
        foreach ($currentCats as $oldCatId) {
            if ($oldCatId !== $shopwareCat['id']) {
                $syncPayload[] = [
                    'action' => 'delete',
                    'entity' => 'product_category',
                    'payload' => [
                        ['productId' => $shopwareProduct['id'], 'categoryId' => $oldCatId]
                    ]
                ];
            }
        }

        // Add new category association (upsert)
        $syncPayload[] = [
            'action' => 'upsert',
            'entity' => 'product_category',
            'payload' => [
                ['productId' => $shopwareProduct['id'], 'categoryId' => $shopwareCat['id']]
            ]
        ];

        // Execute sync
        $syncResponse = apiRequest($baseUrl, $token, 'POST', '/_action/sync', $syncPayload);

        if ($syncResponse['code'] === 200 || $syncResponse['code'] === 204) {
            // Update main category
            $mainCatResponse = apiRequest($baseUrl, $token, 'POST', '/search/main-category', [
                'filter' => [
                    ['type' => 'equals', 'field' => 'productId', 'value' => $shopwareProduct['id']],
                    ['type' => 'equals', 'field' => 'salesChannelId', 'value' => $salesChannelId]
                ]
            ]);

            $existingMainCat = $mainCatResponse['body']['data'][0]['id'] ?? null;

            if ($existingMainCat) {
                apiRequest($baseUrl, $token, 'PATCH', '/main-category/' . $existingMainCat, [
                    'categoryId' => $shopwareCat['id']
                ]);
            } else {
                apiRequest($baseUrl, $token, 'POST', '/main-category', [
                    'productId' => $shopwareProduct['id'],
                    'categoryId' => $shopwareCat['id'],
                    'salesChannelId' => $salesChannelId
                ]);
            }

            $updated++;
            echo "      ✓ Updated\n";
        } else {
            $errors++;
            $errorMsg = $syncResponse['body']['errors'][0]['detail'] ?? ($syncResponse['body']['message'] ?? 'Unknown error');
            echo "      ✗ ERROR: $errorMsg\n";
        }
    }

    echo "\n";
}

// Summary
echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║                        SUMMARY                             ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
echo "║  Products in mapping:     " . str_pad(count($productCategoriesMapping), 4) . "                            ║\n";
echo "║  Products in Shopware:    " . str_pad(count($shopwareProducts), 4) . "                            ║\n";
echo "║  Products to update:      " . str_pad(count($changes), 4) . "                            ║\n";
echo "║  Not found in Shopware:   " . str_pad($notFound, 4) . "                            ║\n";
echo "║  Skipped:                 " . str_pad($skipped, 4) . "                            ║\n";

if (!$dryRun) {
    echo "║  Successfully updated:    " . str_pad($updated, 4) . "                            ║\n";
    echo "║  Errors:                  " . str_pad($errors, 4) . "                            ║\n";
}

echo "╚════════════════════════════════════════════════════════════╝\n\n";

if ($dryRun) {
    echo "This was a DRY RUN. No changes were made.\n";
    echo "Run with --execute to apply changes.\n\n";
} else {
    echo "Run on server:\n";
    echo "  docker exec shopware-chf bin/console dal:refresh:index\n";
    echo "  docker exec shopware-chf bin/console cache:clear\n\n";
}

// Save changes log
$logFile = __DIR__ . '/category-assignment-log-' . date('Y-m-d-His') . '.json';
file_put_contents($logFile, json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'dryRun' => $dryRun,
    'changes' => $changes,
    'summary' => [
        'totalMapping' => count($productCategoriesMapping),
        'totalShopware' => count($shopwareProducts),
        'toUpdate' => count($changes),
        'notFound' => $notFound,
        'skipped' => $skipped,
        'updated' => $updated,
        'errors' => $errors
    ]
], JSON_PRETTY_PRINT));
echo "Log saved to: $logFile\n";
