<?php
/**
 * Snigel Product Scraper for Raven Weapon AG
 *
 * This script scrapes products from the Snigel B2B portal (products.snigel.se)
 * and saves them to JSON/CSV files for import into Shopware.
 *
 * Usage: php snigel-scraper.php
 *
 * The script will:
 * 1. Log in to the B2B portal
 * 2. Scrape all products with prices
 * 3. Download product images
 * 4. Save data to JSON and CSV files
 */

// Configuration
$config = [
    'base_url' => 'https://products.snigel.se',
    'login_url' => 'https://products.snigel.se/my-account/',
    'product_list_url' => 'https://products.snigel.se/product-category/all/',
    'output_dir' => __DIR__ . '/snigel-data',
    'images_dir' => __DIR__ . '/snigel-data/images',
    'json_output' => __DIR__ . '/snigel-data/products.json',
    'csv_output' => __DIR__ . '/snigel-data/products.csv',
    'cookie_file' => __DIR__ . '/snigel-data/cookies.txt',

    // B2B Login credentials
    'username' => 'Raven Weapon AG',
    'password' => 'wVREVbRZfqT&Fba@f(^2UKOw',

    // Currency to use (EUR, SEK, USD)
    'currency' => 'EUR',

    // Rate limiting (seconds between requests)
    'delay_between_requests' => 1,

    // Download images?
    'download_images' => true,

    // Max products to scrape (0 = unlimited)
    'max_products' => 0,
];

// Create output directories
if (!is_dir($config['output_dir'])) {
    mkdir($config['output_dir'], 0755, true);
    echo "Created output directory: {$config['output_dir']}\n";
}
if (!is_dir($config['images_dir'])) {
    mkdir($config['images_dir'], 0755, true);
    echo "Created images directory: {$config['images_dir']}\n";
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║         SNIGEL PRODUCT SCRAPER FOR RAVEN WEAPON AG         ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Initialize cURL session with cookie handling
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    CURLOPT_COOKIEJAR => $config['cookie_file'],
    CURLOPT_COOKIEFILE => $config['cookie_file'],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 60,
]);

/**
 * Fetch a page with rate limiting
 */
function fetchPage($url, $ch, $config, $postData = null) {
    curl_setopt($ch, CURLOPT_URL, $url);

    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    } else {
        curl_setopt($ch, CURLOPT_POST, false);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode !== 200) {
        echo "    Warning: HTTP $httpCode for $url\n";
    }

    sleep($config['delay_between_requests']);
    return $response;
}

/**
 * Login to Snigel B2B portal
 */
function login($ch, $config) {
    echo "Step 1: Logging in to Snigel B2B portal...\n";

    // First, get the login page to get any nonces/tokens
    $loginPage = fetchPage($config['login_url'], $ch, $config);

    // Extract nonce if present
    $nonce = '';
    if (preg_match('/name="woocommerce-login-nonce"\s+value="([^"]+)"/', $loginPage, $match)) {
        $nonce = $match[1];
    }

    // Prepare login data
    $postData = [
        'username' => $config['username'],
        'password' => $config['password'],
        'woocommerce-login-nonce' => $nonce,
        '_wp_http_referer' => '/my-account/',
        'login' => 'Log in',
        'rememberme' => 'forever',
    ];

    // Submit login
    $response = fetchPage($config['login_url'], $ch, $config, http_build_query($postData));

    // Check if login successful
    if (strpos($response, 'Log Out') !== false || strpos($response, 'Dashboard') !== false) {
        echo "  ✓ Login successful!\n\n";
        return true;
    } else {
        echo "  ✗ Login failed. Check credentials.\n\n";
        return false;
    }
}

/**
 * Set currency
 */
function setCurrency($ch, $config) {
    echo "Step 2: Setting currency to {$config['currency']}...\n";

    // WooCommerce currency switcher usually uses cookies or AJAX
    // We'll add currency parameter to URLs or set cookie
    $currencyUrl = $config['base_url'] . "/?currency={$config['currency']}";
    fetchPage($currencyUrl, $ch, $config);

    echo "  ✓ Currency set to {$config['currency']}\n\n";
}

/**
 * Extract product URLs from category page
 */
function extractProductUrls($html) {
    $urls = [];

    // Match product URLs
    preg_match_all('/href="(https:\/\/products\.snigel\.se\/product\/[^"]+)"/', $html, $matches);

    if (!empty($matches[1])) {
        $urls = array_unique($matches[1]);
    }

    return $urls;
}

/**
 * Parse product details from product page
 */
function parseProduct($html, $url) {
    $product = [
        'url' => $url,
        'slug' => basename(rtrim(parse_url($url, PHP_URL_PATH), '/')),
        'name' => '',
        'article_no' => '',
        'ean' => '',
        'weight' => '',
        'weight_g' => 0,
        'dimensions' => '',
        'description' => '',
        'short_description' => '',
        'categories' => [],
        'colours' => [],
        'sizes' => [],
        'images' => [],
        'price' => '',
        'price_numeric' => 0,
        'currency' => 'EUR',
        'in_stock' => true,
    ];

    // Extract product name from h1
    if (preg_match('/<h1[^>]*class="[^"]*product_title[^"]*"[^>]*>([^<]+)<\/h1>/i', $html, $match)) {
        $product['name'] = trim(html_entity_decode($match[1]));
    } elseif (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $html, $match)) {
        $product['name'] = trim(html_entity_decode($match[1]));
    }

    // Extract price
    if (preg_match('/<ins[^>]*>.*?<span[^>]*class="[^"]*woocommerce-Price-amount[^"]*"[^>]*>([^<]+)<\/span>/s', $html, $match)) {
        $product['price'] = trim(strip_tags($match[1]));
    } elseif (preg_match('/<span[^>]*class="[^"]*woocommerce-Price-amount[^"]*"[^>]*>([^<]+)<\/span>/i', $html, $match)) {
        $product['price'] = trim(strip_tags($match[1]));
    }

    // Parse price to numeric
    if ($product['price']) {
        $priceClean = preg_replace('/[^0-9,.]/', '', $product['price']);
        $priceClean = str_replace(',', '.', $priceClean);
        // Handle European format (1.234,56 -> 1234.56)
        if (preg_match('/\.(\d{3})/', $priceClean)) {
            $priceClean = str_replace('.', '', $priceClean);
            $priceClean = preg_replace('/,/', '.', $priceClean);
        }
        $product['price_numeric'] = floatval($priceClean);
    }

    // Detect currency from page
    if (strpos($html, '€') !== false || strpos($html, 'EUR') !== false) {
        $product['currency'] = 'EUR';
    } elseif (strpos($html, 'SEK') !== false) {
        $product['currency'] = 'SEK';
    } elseif (strpos($html, '$') !== false || strpos($html, 'USD') !== false) {
        $product['currency'] = 'USD';
    }

    // Extract article number
    if (preg_match('/Article\s*no[:\s]*<\/[^>]+>\s*([^<]+)/i', $html, $match)) {
        $product['article_no'] = trim($match[1]);
    } elseif (preg_match('/Article\s*no[:\s]*([0-9\-A-Za-z]+)/i', $html, $match)) {
        $product['article_no'] = trim($match[1]);
    }

    // Extract EAN
    if (preg_match('/EAN[:\s]*<\/[^>]+>\s*"?([0-9]+)"?/i', $html, $match)) {
        $product['ean'] = trim($match[1]);
    } elseif (preg_match('/EAN[:\s]*"?([0-9]{8,14})"?/i', $html, $match)) {
        $product['ean'] = trim($match[1]);
    }

    // Extract weight
    if (preg_match('/Weight[:\s]*<\/[^>]+>\s*([^<]+)/i', $html, $match)) {
        $product['weight'] = trim($match[1]);
    } elseif (preg_match('/Weight[:\s]*([0-9]+\s*[gk]+)/i', $html, $match)) {
        $product['weight'] = trim($match[1]);
    }

    // Parse weight to grams
    if ($product['weight']) {
        if (preg_match('/([0-9.]+)\s*kg/i', $product['weight'], $wMatch)) {
            $product['weight_g'] = floatval($wMatch[1]) * 1000;
        } elseif (preg_match('/([0-9.]+)\s*g/i', $product['weight'], $wMatch)) {
            $product['weight_g'] = floatval($wMatch[1]);
        }
    }

    // Extract dimensions
    if (preg_match('/Dimensions[:\s]*<\/[^>]+>\s*([^<]+)/i', $html, $match)) {
        $product['dimensions'] = trim($match[1]);
    } elseif (preg_match('/Dimensions[:\s]*([0-9x\s]+\s*cm)/i', $html, $match)) {
        $product['dimensions'] = trim($match[1]);
    }

    // Extract short description (emphasis/italic text)
    if (preg_match('/<em[^>]*>([^<]{20,})<\/em>/i', $html, $match)) {
        $product['short_description'] = trim(html_entity_decode($match[1]));
    }

    // Extract description from list items in product description
    if (preg_match('/<div[^>]*class="[^"]*woocommerce-product-details__short-description[^"]*"[^>]*>(.*?)<\/div>/s', $html, $descMatch)) {
        $descHtml = $descMatch[1];
        preg_match_all('/<li[^>]*>([^<]+)<\/li>/i', $descHtml, $liMatches);
        if (!empty($liMatches[1])) {
            $product['description'] = implode("\n• ", array_map(function($item) {
                return trim(html_entity_decode($item));
            }, $liMatches[1]));
            if ($product['description']) {
                $product['description'] = '• ' . $product['description'];
            }
        }
    }

    // Extract categories
    if (preg_match('/Categories.*?<\/span>(.*?)<\/span>/s', $html, $match)) {
        preg_match_all('/<a[^>]*href="[^"]*product-category[^"]*"[^>]*>([^<]+)<\/a>/', $match[1], $catMatches);
        if (!empty($catMatches[1])) {
            $product['categories'] = array_map('trim', $catMatches[1]);
        }
    }

    // Extract colours from select options or links
    if (preg_match_all('/<option[^>]*value="([^"]+)"[^>]*>([^<]+)<\/option>/i', $html, $optMatches, PREG_SET_ORDER)) {
        foreach ($optMatches as $opt) {
            $value = trim($opt[2]);
            if ($value && $value !== 'Choose an option' && !is_numeric($value)) {
                $product['colours'][] = $value;
            }
        }
    }

    // Also check colour links
    if (preg_match('/Colour.*?<\/span>(.*?)<\/span>/s', $html, $match)) {
        preg_match_all('/<a[^>]*>([^<]+)<\/a>/', $match[1], $colMatches);
        if (!empty($colMatches[1])) {
            $product['colours'] = array_merge($product['colours'], array_map('trim', $colMatches[1]));
        }
    }
    $product['colours'] = array_unique($product['colours']);

    // Extract images
    preg_match_all('/href="(https:\/\/products\.snigel\.se\/wp-content\/uploads\/[^"]+\.(jpg|jpeg|png|webp))"/i', $html, $imgMatches);
    if (!empty($imgMatches[1])) {
        $product['images'] = array_values(array_unique($imgMatches[1]));
    }

    // Also check for product gallery images
    preg_match_all('/data-src="(https:\/\/products\.snigel\.se\/wp-content\/uploads\/[^"]+\.(jpg|jpeg|png|webp))"/i', $html, $imgMatches2);
    if (!empty($imgMatches2[1])) {
        $product['images'] = array_values(array_unique(array_merge($product['images'], $imgMatches2[1])));
    }

    return $product;
}

/**
 * Download an image
 */
function downloadImage($url, $dir) {
    $filename = basename(parse_url($url, PHP_URL_PATH));
    $filepath = $dir . '/' . $filename;

    if (file_exists($filepath)) {
        return $filename; // Already downloaded
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);

    $imageData = @file_get_contents($url, false, $context);
    if ($imageData) {
        file_put_contents($filepath, $imageData);
        return $filename;
    }

    return null;
}

// ============================================
// MAIN SCRAPING LOGIC
// ============================================

// Step 1: Login
if (!login($ch, $config)) {
    echo "Cannot proceed without login. Exiting.\n";
    exit(1);
}

// Step 2: Set currency
setCurrency($ch, $config);

// Step 3: Fetch all product URLs
echo "Step 3: Fetching product listings...\n";

$allProductUrls = [];
$page = 1;
$hasMore = true;

while ($hasMore) {
    $pageUrl = $config['product_list_url'];
    if ($page > 1) {
        $pageUrl .= 'page/' . $page . '/';
    }

    echo "  Fetching page $page: " . basename($pageUrl) . "\n";
    $html = fetchPage($pageUrl, $ch, $config);

    if (!$html) {
        echo "  Error fetching page $page\n";
        break;
    }

    $productUrls = extractProductUrls($html);

    if (empty($productUrls)) {
        $hasMore = false;
        echo "  No more products found.\n";
    } else {
        $newUrls = array_diff($productUrls, $allProductUrls);
        if (empty($newUrls)) {
            $hasMore = false;
            echo "  No new products on this page.\n";
        } else {
            $allProductUrls = array_merge($allProductUrls, $newUrls);
            echo "  Found " . count($newUrls) . " new products (total: " . count($allProductUrls) . ")\n";
            $page++;
        }
    }

    // Safety limit
    if ($page > 100) {
        echo "  Reached page limit.\n";
        break;
    }
}

$allProductUrls = array_values(array_unique($allProductUrls));
echo "\n  Total unique products found: " . count($allProductUrls) . "\n\n";

// Apply max limit if set
if ($config['max_products'] > 0) {
    $allProductUrls = array_slice($allProductUrls, 0, $config['max_products']);
    echo "  Limited to {$config['max_products']} products for testing.\n\n";
}

// Step 4: Fetch each product's details
echo "Step 4: Fetching product details...\n";
$products = [];
$count = 0;
$total = count($allProductUrls);
$errors = [];

foreach ($allProductUrls as $productUrl) {
    $count++;
    $productSlug = basename(rtrim(parse_url($productUrl, PHP_URL_PATH), '/'));
    echo "  [$count/$total] $productSlug... ";

    $html = fetchPage($productUrl, $ch, $config);

    if ($html) {
        $product = parseProduct($html, $productUrl);
        $products[] = $product;

        echo "{$product['name']}";
        if ($product['price']) {
            echo " - {$product['price']}";
        }
        echo " (" . count($product['images']) . " images)\n";
    } else {
        echo "ERROR\n";
        $errors[] = $productUrl;
    }
}

echo "\n  Products scraped: " . count($products) . "\n";
if (!empty($errors)) {
    echo "  Errors: " . count($errors) . "\n";
}
echo "\n";

// Step 5: Download images
if ($config['download_images']) {
    echo "Step 5: Downloading product images...\n";
    $imageCount = 0;
    $imageTotal = 0;

    foreach ($products as $product) {
        $imageTotal += count($product['images']);
    }

    foreach ($products as &$product) {
        $localImages = [];
        foreach ($product['images'] as $imageUrl) {
            $imageCount++;
            echo "  [$imageCount/$imageTotal] Downloading " . basename($imageUrl) . "... ";

            $filename = downloadImage($imageUrl, $config['images_dir']);
            if ($filename) {
                $localImages[] = $filename;
                echo "OK\n";
            } else {
                echo "FAILED\n";
            }
        }
        $product['local_images'] = $localImages;
    }
    unset($product);

    echo "\n  Images downloaded to: {$config['images_dir']}\n\n";
} else {
    echo "Step 5: Skipping image download (disabled in config)\n\n";
}

// Step 6: Save to JSON
echo "Step 6: Saving to JSON...\n";
$jsonData = json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents($config['json_output'], $jsonData);
echo "  Saved to: {$config['json_output']}\n\n";

// Step 7: Save to CSV
echo "Step 7: Saving to CSV...\n";
$csvFile = fopen($config['csv_output'], 'w');

// UTF-8 BOM for Excel
fwrite($csvFile, "\xEF\xBB\xBF");

// Header row
fputcsv($csvFile, [
    'name',
    'slug',
    'article_no',
    'ean',
    'price',
    'price_numeric',
    'currency',
    'weight',
    'weight_g',
    'dimensions',
    'short_description',
    'description',
    'categories',
    'colours',
    'images',
    'url'
], ';');

foreach ($products as $product) {
    fputcsv($csvFile, [
        $product['name'],
        $product['slug'],
        $product['article_no'],
        $product['ean'],
        $product['price'],
        $product['price_numeric'],
        $product['currency'],
        $product['weight'],
        $product['weight_g'],
        $product['dimensions'],
        $product['short_description'],
        $product['description'],
        implode(', ', $product['categories']),
        implode(', ', $product['colours']),
        implode(', ', $product['images']),
        $product['url'],
    ], ';');
}

fclose($csvFile);
echo "  Saved to: {$config['csv_output']}\n\n";

// Cleanup
curl_close($ch);

// Summary
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                    SCRAPING COMPLETE                       ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
echo "║  Total products: " . str_pad(count($products), 40) . "║\n";
echo "║  JSON output:    " . str_pad(basename($config['json_output']), 40) . "║\n";
echo "║  CSV output:     " . str_pad(basename($config['csv_output']), 40) . "║\n";
echo "║  Images folder:  " . str_pad(basename($config['images_dir']), 40) . "║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "Next step: Run 'php shopware-import.php' to import products into Shopware.\n";
