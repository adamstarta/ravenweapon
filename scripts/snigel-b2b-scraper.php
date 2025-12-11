<?php
/**
 * Snigel B2B Portal Scraper
 *
 * Scrapes products from products.snigel.se B2B portal
 * Gets EUR prices, article numbers, images, and full product details
 *
 * Usage: php snigel-b2b-scraper.php
 */

// Configuration
$config = [
    'base_url' => 'https://products.snigel.se',
    'username' => 'Raven Weapon AG',
    'password' => 'wVREVbRZfqT&Fba@f(^2UKOw',
    'output_dir' => __DIR__ . '/snigel-b2b-data',
    'images_dir' => __DIR__ . '/snigel-b2b-data/images',
    'currency' => 'EUR',
    'delay_between_requests' => 1, // seconds
];

echo "\n";
echo "========================================================\n";
echo "       SNIGEL B2B PORTAL SCRAPER\n";
echo "========================================================\n\n";

// Create output directories
if (!is_dir($config['output_dir'])) {
    mkdir($config['output_dir'], 0755, true);
    echo "Created output directory: {$config['output_dir']}\n";
}
if (!is_dir($config['images_dir'])) {
    mkdir($config['images_dir'], 0755, true);
    echo "Created images directory: {$config['images_dir']}\n";
}

// Initialize cURL session with cookie jar
$cookieFile = $config['output_dir'] . '/cookies.txt';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => true,
]);

/**
 * Make HTTP request
 */
function makeRequest($ch, $url, $postData = null) {
    curl_setopt($ch, CURLOPT_URL, $url);

    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    } else {
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        echo "cURL Error: " . curl_error($ch) . "\n";
        return null;
    }

    return ['code' => $httpCode, 'body' => $response];
}

/**
 * Download image
 */
function downloadImage($ch, $url, $savePath) {
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);

    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode === 200 && $imageData) {
        file_put_contents($savePath, $imageData);
        return true;
    }
    return false;
}

// Step 1: Get login page to get nonce
echo "Step 1: Fetching login page...\n";
$result = makeRequest($ch, $config['base_url'] . '/my-account/');
if (!$result) {
    die("Failed to fetch login page\n");
}

// Extract login nonce from form
preg_match('/name="woocommerce-login-nonce"\s+value="([^"]+)"/', $result['body'], $nonceMatch);
$loginNonce = $nonceMatch[1] ?? '';

// Step 2: Login
echo "Step 2: Logging in as {$config['username']}...\n";
$loginData = [
    'username' => $config['username'],
    'password' => $config['password'],
    'woocommerce-login-nonce' => $loginNonce,
    '_wp_http_referer' => '/my-account/',
    'login' => 'Log in',
    'rememberme' => 'forever',
];

$result = makeRequest($ch, $config['base_url'] . '/my-account/', $loginData);

// Check if logged in
if (strpos($result['body'], 'Log Out') !== false || strpos($result['body'], 'Dashboard') !== false) {
    echo "  Login successful!\n\n";
} else {
    echo "  Login might have failed. Continuing anyway...\n\n";
}

// Step 3: Set currency to EUR
echo "Step 3: Setting currency to EUR...\n";
// The currency is set via a cookie or AJAX call - we'll add the currency parameter to URLs
$currencyParam = '?currency=EUR';

// Step 4: Get all product URLs from shop page
echo "Step 4: Collecting product URLs...\n";
$allProductUrls = [];
$page = 1;
$maxPages = 50; // Safety limit

while ($page <= $maxPages) {
    $shopUrl = $config['base_url'] . "/shop/page/$page/" . $currencyParam;
    if ($page === 1) {
        $shopUrl = $config['base_url'] . "/shop/" . $currencyParam;
    }

    echo "  Fetching page $page... ";
    $result = makeRequest($ch, $shopUrl);

    if (!$result || $result['code'] !== 200) {
        echo "Done (no more pages)\n";
        break;
    }

    // Extract product URLs
    preg_match_all('/href="(https:\/\/products\.snigel\.se\/product\/[^"]+)"/', $result['body'], $matches);
    $pageUrls = array_unique($matches[1] ?? []);

    if (empty($pageUrls)) {
        echo "Done (no products found)\n";
        break;
    }

    $newUrls = array_diff($pageUrls, $allProductUrls);
    $allProductUrls = array_merge($allProductUrls, $newUrls);

    echo "found " . count($newUrls) . " products (total: " . count($allProductUrls) . ")\n";

    // Check if there's a next page
    if (strpos($result['body'], "/shop/page/" . ($page + 1)) === false) {
        break;
    }

    $page++;
    sleep($config['delay_between_requests']);
}

$allProductUrls = array_unique($allProductUrls);
echo "\n  Total unique products found: " . count($allProductUrls) . "\n\n";

// Step 5: Scrape each product
echo "Step 5: Scraping product details...\n";
$products = [];
$count = 0;
$total = count($allProductUrls);

foreach ($allProductUrls as $productUrl) {
    $count++;
    echo "  [$count/$total] ";

    // Add currency parameter
    $url = $productUrl . (strpos($productUrl, '?') === false ? '?' : '&') . 'currency=EUR';

    $result = makeRequest($ch, $url);
    if (!$result || $result['code'] !== 200) {
        echo "FAILED\n";
        continue;
    }

    $html = $result['body'];
    $product = [
        'url' => $productUrl,
        'slug' => basename(parse_url($productUrl, PHP_URL_PATH)),
    ];

    // Extract product name
    if (preg_match('/<h1[^>]*class="[^"]*product_title[^"]*"[^>]*>([^<]+)<\/h1>/i', $html, $m)) {
        $product['name'] = trim(html_entity_decode($m[1]));
    } elseif (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $html, $m)) {
        $product['name'] = trim(html_entity_decode($m[1]));
    }

    // Extract article number
    if (preg_match('/Article no:\s*([A-Z0-9\-]+)/i', $html, $m)) {
        $product['article_no'] = trim($m[1]);
    }

    // Extract EAN
    if (preg_match('/EAN:\s*(\d+)/i', $html, $m)) {
        $product['ean'] = trim($m[1]);
    }

    // Extract weight
    if (preg_match('/Weight:\s*<\/[^>]+>\s*(\d+)\s*g/i', $html, $m)) {
        $product['weight_g'] = (int)$m[1];
    } elseif (preg_match('/Weight:[^<]*(\d+)\s*g/i', $html, $m)) {
        $product['weight_g'] = (int)$m[1];
    }

    // Extract RRP (Retail Price)
    if (preg_match('/RRP[^<]*<[^>]+>([0-9,\.]+)\s*€/i', $html, $m)) {
        $product['rrp_eur'] = (float)str_replace(',', '.', str_replace('.', '', $m[1]));
    }

    // Extract B2B price (Your price)
    if (preg_match('/Your price.*?(\d+[,\.]\d+)\s*€/is', $html, $m)) {
        $product['b2b_price_eur'] = (float)str_replace(',', '.', $m[1]);
    } elseif (preg_match('/1 and more.*?(\d+[,\.]\d+)\s*€/is', $html, $m)) {
        $product['b2b_price_eur'] = (float)str_replace(',', '.', $m[1]);
    }

    // Extract bulk price (10+)
    if (preg_match('/10 and more.*?(\d+[,\.]\d+)\s*€/is', $html, $m)) {
        $product['bulk_price_eur'] = (float)str_replace(',', '.', $m[1]);
    }

    // Extract stock
    if (preg_match('/(\d+)\s+in stock/i', $html, $m)) {
        $product['stock'] = (int)$m[1];
    }

    // Extract category
    if (preg_match('/Category[^<]*<a[^>]+href="[^"]+product-category\/all\/([^\/]+)/i', $html, $m)) {
        $product['category'] = str_replace('-', ' ', ucwords($m[1], '-'));
    }

    // Extract colour
    if (preg_match('/Colour[^<]*<a[^>]+>([^<]+)/i', $html, $m)) {
        $product['colour'] = trim($m[1]);
    }

    // Extract description
    if (preg_match('/<div[^>]*class="[^"]*woocommerce-product-details__short-description[^"]*"[^>]*>(.*?)<\/div>/is', $html, $m)) {
        $product['description'] = trim(strip_tags($m[1]));
    }

    // Extract images
    $product['images'] = [];
    preg_match_all('/href="(https:\/\/products\.snigel\.se\/wp-content\/uploads\/[^"]+\.(jpg|jpeg|png|webp))"/i', $html, $imgMatches);
    if (!empty($imgMatches[1])) {
        $product['images'] = array_unique($imgMatches[1]);
    }

    // Also check for image in main product gallery
    preg_match_all('/src="(https:\/\/products\.snigel\.se\/wp-content\/uploads\/[^"]+\.(jpg|jpeg|png|webp))"/i', $html, $srcMatches);
    if (!empty($srcMatches[1])) {
        $product['images'] = array_unique(array_merge($product['images'], $srcMatches[1]));
    }

    // Download images
    $product['local_images'] = [];
    foreach (array_slice($product['images'], 0, 5) as $i => $imageUrl) {
        $ext = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
        $filename = ($product['article_no'] ?? $product['slug']) . "_$i.$ext";
        $savePath = $config['images_dir'] . '/' . $filename;

        if (!file_exists($savePath)) {
            if (downloadImage($ch, $imageUrl, $savePath)) {
                $product['local_images'][] = $filename;
            }
        } else {
            $product['local_images'][] = $filename;
        }
    }

    $products[] = $product;

    $name = $product['name'] ?? 'Unknown';
    $price = $product['b2b_price_eur'] ?? 'N/A';
    $articleNo = $product['article_no'] ?? 'N/A';
    echo substr($name, 0, 40) . " - €$price - $articleNo\n";

    sleep($config['delay_between_requests']);
}

curl_close($ch);

// Save products to JSON
$jsonFile = $config['output_dir'] . '/products-b2b.json';
file_put_contents($jsonFile, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n";
echo "========================================================\n";
echo "                    SCRAPING COMPLETE\n";
echo "========================================================\n";
echo "  Products scraped: " . count($products) . "\n";
echo "  Output file: $jsonFile\n";
echo "  Images directory: {$config['images_dir']}\n";
echo "========================================================\n\n";

// Summary statistics
$withPrices = count(array_filter($products, fn($p) => isset($p['b2b_price_eur'])));
$withImages = count(array_filter($products, fn($p) => !empty($p['local_images'])));
$withArticleNo = count(array_filter($products, fn($p) => isset($p['article_no'])));

echo "Statistics:\n";
echo "  - With B2B prices: $withPrices\n";
echo "  - With images: $withImages\n";
echo "  - With article numbers: $withArticleNo\n";
echo "\n";
