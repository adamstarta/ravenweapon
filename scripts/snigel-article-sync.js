/**
 * Snigel Article Number Sync Tool
 *
 * Scrapes article_no, weight, dimensions, and name from Snigel B2B portal
 * and syncs to Shopware (updates product_number, weight, dimensions)
 *
 * Usage:
 *   node snigel-article-sync.js --scrape              # Scrape all products from Snigel (slow pagination)
 *   node snigel-article-sync.js --scrape --use-urls   # Use existing URLs from merged products (FASTER!)
 *   node snigel-article-sync.js --scrape --resume     # Resume from last saved progress
 *   node snigel-article-sync.js --compare             # Compare with Shopware (no changes)
 *   node snigel-article-sync.js --update              # Apply changes to Shopware
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const https = require('https');

// ============================================================
// CONFIGURATION
// ============================================================
const CONFIG = {
    // Snigel B2B Portal
    snigel: {
        baseUrl: 'https://products.snigel.se',
        username: 'Raven Weapon AG',
        password: 'wVREVbRZfqT&Fba@f(^2UKOw'
    },

    // Shopware API
    shopware: {
        baseUrl: 'https://www.ravenweapon.ch',
        apiUrl: 'https://www.ravenweapon.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
    },

    // Output files
    outputDir: path.join(__dirname, 'snigel-article-data'),
    outputFiles: {
        products: 'snigel-articles.json',
        progress: 'scrape-progress.json',
        comparison: 'comparison-report.json',
        log: 'sync-log.json'
    },

    // Scraping settings
    requestDelay: 1500,
    pageTimeout: 30000,
    maxRetries: 3,
    saveEvery: 20  // Save progress every N products
};

// Create output directory
if (!fs.existsSync(CONFIG.outputDir)) {
    fs.mkdirSync(CONFIG.outputDir, { recursive: true });
}

// ============================================================
// HELPERS
// ============================================================

function delay(ms) {
    const jitter = 0.5 + Math.random();
    const randomMs = Math.floor(ms * jitter);
    return new Promise(resolve => setTimeout(resolve, randomMs));
}

function httpRequest(url, options = {}) {
    return new Promise((resolve, reject) => {
        const urlObj = new URL(url);
        const reqOptions = {
            hostname: urlObj.hostname,
            port: 443,
            path: urlObj.pathname + urlObj.search,
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...options.headers
            }
        };

        const req = https.request(reqOptions, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try {
                    resolve({ status: res.statusCode, data: JSON.parse(data) });
                } catch (e) {
                    resolve({ status: res.statusCode, data: data });
                }
            });
        });

        req.on('error', reject);
        if (options.body) req.write(JSON.stringify(options.body));
        req.end();
    });
}

/**
 * Parse weight string to grams
 * "500 g" → 500
 * "1.5 kg" → 1500
 */
function parseWeight(weightStr) {
    if (!weightStr || weightStr.trim() === '') return null;

    const clean = weightStr.toLowerCase().trim();

    // Match number and unit
    const match = clean.match(/([\d.,]+)\s*(kg|g|gram|grams)?/i);
    if (!match) return null;

    let value = parseFloat(match[1].replace(',', '.'));
    const unit = match[2] || 'g';

    if (unit === 'kg') {
        value = value * 1000;
    }

    return Math.round(value);
}

/**
 * Parse dimensions string to width, height, length in mm
 * "190 x 60 x 1 cm" → { width: 600, height: 10, length: 1900 }
 * "9 x 7 x 3 cm" → { width: 70, height: 30, length: 90 }
 */
function parseDimensions(dimStr) {
    if (!dimStr || dimStr.trim() === '') return null;

    // Check for corrupted data (contains "Category" or similar)
    if (dimStr.includes('Category') || dimStr.includes('Categories') || dimStr.includes('\t')) {
        return null;
    }

    const clean = dimStr.toLowerCase().trim();

    // Match pattern: "190 x 60 x 1 cm" or "190x60x1cm"
    const match = clean.match(/([\d.,]+)\s*[x×]\s*([\d.,]+)\s*[x×]\s*([\d.,]+)\s*(cm|mm|m)?/i);
    if (!match) return null;

    let length = parseFloat(match[1].replace(',', '.'));
    let width = parseFloat(match[2].replace(',', '.'));
    let height = parseFloat(match[3].replace(',', '.'));
    const unit = match[4] || 'cm';

    // Convert to mm (Shopware uses mm)
    let multiplier = 10; // cm to mm
    if (unit === 'mm') multiplier = 1;
    if (unit === 'm') multiplier = 1000;

    return {
        length: Math.round(length * multiplier),
        width: Math.round(width * multiplier),
        height: Math.round(height * multiplier)
    };
}

// ============================================================
// SNIGEL SCRAPING
// ============================================================

/**
 * Login to Snigel B2B portal
 */
async function loginToSnigel(page) {
    console.log('  Logging in to Snigel B2B portal...');

    await page.goto(`${CONFIG.snigel.baseUrl}/my-account/`, {
        waitUntil: 'domcontentloaded'
    });
    await delay(2000);

    // Dismiss cookie popup if present
    try {
        await page.click('button:has-text("Ok")', { timeout: 3000 });
    } catch (e) {
        // No popup
    }

    // Fill login form
    await page.fill('#username', CONFIG.snigel.username);
    await page.fill('#password', CONFIG.snigel.password);
    await page.click('button[name="login"]');

    // Wait for login to complete
    await page.waitForURL('**/my-account/**', { timeout: 30000 });
    await delay(2000);

    console.log('  ✓ Login successful!');
}

/**
 * Navigate with retry
 */
async function gotoWithRetry(page, url, retries = 3) {
    for (let i = 0; i < retries; i++) {
        try {
            await page.goto(url, {
                waitUntil: 'domcontentloaded',
                timeout: 90000  // 90 second timeout
            });
            return true;
        } catch (error) {
            console.log(`      Retry ${i + 1}/${retries}...`);
            if (i === retries - 1) throw error;
            await delay(3000);
        }
    }
}

/**
 * Get all product URLs from Snigel
 */
async function getAllProductUrls(page) {
    console.log('\n  Fetching product list...');

    const allProducts = [];
    let pageNum = 1;
    let hasMore = true;
    let consecutiveErrors = 0;

    while (hasMore) {
        // Use product-category/all/ with pagination
        const url = pageNum === 1
            ? `${CONFIG.snigel.baseUrl}/product-category/all/`
            : `${CONFIG.snigel.baseUrl}/product-category/all/page/${pageNum}/`;
        console.log(`    Page ${pageNum}...`);

        try {
            await gotoWithRetry(page, url);
        } catch (error) {
            console.log(`      ✗ Failed to load page ${pageNum}: ${error.message.substring(0, 50)}`);
            consecutiveErrors++;
            if (consecutiveErrors >= 3) {
                console.log('      Too many errors, stopping pagination');
                hasMore = false;
                break;
            }
            pageNum++;
            continue;
        }
        consecutiveErrors = 0;
        await delay(1500);

        // Scroll down to load all products (lazy loading)
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await delay(1000);

        const products = await page.evaluate(() => {
            const items = [];
            const seenUrls = new Set();

            // Method 1: Find product containers with WooCommerce classes
            document.querySelectorAll('.product, .type-product, [class*="product-item"]').forEach(container => {
                // Find the product link
                const link = container.querySelector('a[href*="/product/"]');
                if (!link) return;

                const url = link.href;
                if (!url || seenUrls.has(url)) return;
                if (url.includes('add-to-cart') || url.includes('?add-to-cart')) return;

                // Find product name - try multiple selectors
                let name = '';
                const titleSelectors = [
                    '.woocommerce-loop-product__title',
                    '.woocommerce-loop-product__link',
                    'h2.woocommerce-loop-product__title',
                    'h3 a',
                    'h3',
                    'h2 a',
                    'h2',
                    '.product-title',
                    '.product-name',
                    'a[href*="/product/"]'
                ];

                for (const sel of titleSelectors) {
                    const el = container.querySelector(sel);
                    if (el && el.innerText.trim()) {
                        name = el.innerText.trim();
                        break;
                    }
                }

                if (url && name && !seenUrls.has(url)) {
                    seenUrls.add(url);
                    items.push({ url, name });
                }
            });

            // Method 2: Fallback - find all h2/h3 with product links
            if (items.length === 0) {
                document.querySelectorAll('h2 a[href*="/product/"], h3 a[href*="/product/"]').forEach(a => {
                    const url = a.href;
                    if (!url || seenUrls.has(url)) return;
                    if (url.includes('add-to-cart') || url.includes('?')) return;

                    const name = a.innerText.trim();
                    if (url && name) {
                        seenUrls.add(url);
                        items.push({ url, name });
                    }
                });
            }

            // Method 3: Last resort - any unique product links
            if (items.length === 0) {
                document.querySelectorAll('a[href*="/product/"]').forEach(a => {
                    const url = a.href;
                    if (!url || seenUrls.has(url)) return;
                    if (url.includes('add-to-cart') || url.includes('?')) return;

                    // Extract name from URL slug
                    const slug = url.split('/product/')[1]?.replace(/\/$/, '') || '';
                    const name = slug.split('/').pop()?.replace(/-/g, ' ').toUpperCase() || '';

                    if (url && name) {
                        seenUrls.add(url);
                        items.push({ url, name: `[FROM URL] ${name}` });
                    }
                });
            }

            return items;
        });

        console.log(`      Found ${products.length} products on this page`);

        if (products.length === 0) {
            hasMore = false;
        } else {
            // Add only new products (avoid duplicates)
            products.forEach(p => {
                if (!allProducts.find(existing => existing.url === p.url)) {
                    allProducts.push(p);
                }
            });
            pageNum++;
            await delay(500);
        }

        // Safety limit - max 20 pages
        if (pageNum > 20) {
            console.log('    Reached page limit (20)');
            hasMore = false;
        }
    }

    console.log(`  ✓ Found ${allProducts.length} total products\n`);
    return allProducts;
}

/**
 * Scrape single product details
 */
async function scrapeProduct(page, productUrl) {
    const result = {
        url: productUrl,
        name: '',
        article_no: '',
        weight: '',
        weight_g: null,
        dimensions: '',
        dimensions_mm: null,
        debug: '',
        error: null
    };

    try {
        await gotoWithRetry(page, productUrl);
        await delay(1500);

        // Extract product data
        const data = await page.evaluate(() => {
            const result = {
                name: '',
                article_no: '',
                weight: '',
                dimensions: '',
                debug: ''  // For debugging extraction issues
            };

            // Product name from h1
            const titleEl = document.querySelector('h1.product_title, h1.entry-title, h1');
            if (titleEl) {
                result.name = titleEl.innerText.trim();
            }

            // Get full page text for pattern matching
            const pageText = document.body.innerText;

            // Article no patterns - try multiple formats:
            // "Article no: 13-00110-01-000"
            // "Article no.:13-00110B01-000"
            // "Art. no: 13-00110-01-000"
            // "Art.no: 13-00110-01-000"
            // "Artikelnr: 13-00110-01-000"
            const artPatterns = [
                /Article\s*no\.?[:\s]+([A-Za-z0-9][\w\-]*)/i,
                /Art\.?\s*no\.?[:\s]+([A-Za-z0-9][\w\-]*)/i,
                /Artikelnr\.?[:\s]+([A-Za-z0-9][\w\-]*)/i,
                /Art\.?[:\s]+([0-9]{2,3}-[0-9\-A-Za-z]+)/i,
                /SKU[:\s]+([A-Za-z0-9][\w\-]*)/i,
                /Product\s*code[:\s]+([A-Za-z0-9][\w\-]*)/i
            ];

            for (const pattern of artPatterns) {
                const match = pageText.match(pattern);
                if (match && match[1]) {
                    result.article_no = match[1].trim();
                    break;
                }
            }

            // Weight patterns - try multiple formats:
            // "Weight: 500 g" or "Weight:500g" or "Vikt: 500 g"
            const weightPatterns = [
                /Weight[:\s]*([\d.,]+)\s*(g|kg|gram)/i,
                /Vikt[:\s]*([\d.,]+)\s*(g|kg|gram)/i,
                /Net\s*weight[:\s]*([\d.,]+)\s*(g|kg)/i,
                /([\d.,]+)\s*(g|kg)\s*$/m  // Just number followed by g/kg at end of line
            ];

            for (const pattern of weightPatterns) {
                const match = pageText.match(pattern);
                if (match && match[1]) {
                    result.weight = match[1].trim() + ' ' + match[2];
                    break;
                }
            }

            // Dimensions patterns - try multiple formats:
            // "Dimensions: 190 x 60 x 1 cm"
            // "Size: 190x60x1cm"
            // "Mått: 190 x 60 x 1 cm"
            const dimPatterns = [
                /Dimensions?[:\s]*([\d.,]+\s*[x×]\s*[\d.,]+\s*[x×]\s*[\d.,]+)\s*(cm|mm|m)?/i,
                /Mått[:\s]*([\d.,]+\s*[x×]\s*[\d.,]+\s*[x×]\s*[\d.,]+)\s*(cm|mm|m)?/i,
                /Size[:\s]*([\d.,]+\s*[x×]\s*[\d.,]+\s*[x×]\s*[\d.,]+)\s*(cm|mm|m)?/i,
                /([\d.,]+)\s*[x×]\s*([\d.,]+)\s*[x×]\s*([\d.,]+)\s*(cm|mm)?/i  // Generic L x W x H
            ];

            for (const pattern of dimPatterns) {
                const match = pageText.match(pattern);
                if (match) {
                    if (match[3] && !match[1].includes('x')) {
                        // Generic pattern matched
                        result.dimensions = `${match[1]} x ${match[2]} x ${match[3]} ${match[4] || 'cm'}`;
                    } else {
                        result.dimensions = match[1].trim() + (match[2] ? ' ' + match[2] : ' cm');
                    }
                    break;
                }
            }

            // Fallback: look in specific elements (WooCommerce product meta)
            const metaElements = document.querySelectorAll('.product_meta span, .product-meta span, .woocommerce-product-details__short-description, .product-details');
            metaElements.forEach(el => {
                const text = el.innerText;

                if (!result.article_no) {
                    const artMatch = text.match(/(?:Article|Art|SKU)[.\s:]*([A-Za-z0-9][\w\-]+)/i);
                    if (artMatch) result.article_no = artMatch[1].trim();
                }
            });

            // Look in table rows (some sites use tables for product info)
            document.querySelectorAll('table tr, .product-info-row, .product-attribute').forEach(row => {
                const text = row.innerText.toLowerCase();
                const cells = row.querySelectorAll('td, th, span');

                if (text.includes('article') || text.includes('art.') || text.includes('sku')) {
                    cells.forEach(cell => {
                        const match = cell.innerText.match(/([0-9]{2,3}-[\w\-]+)/);
                        if (match && !result.article_no) {
                            result.article_no = match[1];
                        }
                    });
                }

                if (text.includes('weight') || text.includes('vikt')) {
                    cells.forEach(cell => {
                        const match = cell.innerText.match(/([\d.,]+)\s*(g|kg)/i);
                        if (match && !result.weight) {
                            result.weight = match[1] + ' ' + match[2];
                        }
                    });
                }

                if (text.includes('dimension') || text.includes('size') || text.includes('mått')) {
                    cells.forEach(cell => {
                        const match = cell.innerText.match(/([\d.,]+\s*[x×]\s*[\d.,]+\s*[x×]\s*[\d.,]+)/i);
                        if (match && !result.dimensions) {
                            result.dimensions = match[1] + ' cm';
                        }
                    });
                }
            });

            // Debug: capture a snippet of the page text around "Article"
            const artIndex = pageText.indexOf('Article');
            if (artIndex >= 0) {
                result.debug = pageText.substring(artIndex, artIndex + 100);
            } else {
                result.debug = pageText.substring(0, 200);
            }

            return result;
        });

        result.name = data.name;
        result.article_no = data.article_no;
        result.weight = data.weight;
        result.dimensions = data.dimensions;
        result.debug = data.debug || '';
        result.weight_g = parseWeight(data.weight);
        result.dimensions_mm = parseDimensions(data.dimensions);

    } catch (error) {
        result.error = error.message;
    }

    return result;
}

/**
 * Main scrape function
 */
async function scrapeMode(resume = false, useExistingUrls = false) {
    console.log('\n' + '═'.repeat(60));
    console.log('  SNIGEL ARTICLE SYNC - SCRAPE MODE');
    console.log('═'.repeat(60));

    const progressPath = path.join(CONFIG.outputDir, CONFIG.outputFiles.progress);
    const outputPath = path.join(CONFIG.outputDir, CONFIG.outputFiles.products);
    const mergedProductsPath = path.join(__dirname, 'snigel-merged-products.json');

    let products = [];
    let startIndex = 0;
    let existingData = [];

    // Resume from progress if requested
    if (resume && fs.existsSync(progressPath)) {
        const progress = JSON.parse(fs.readFileSync(progressPath, 'utf8'));
        startIndex = progress.lastIndex || 0;
        existingData = progress.products || [];
        console.log(`\n  Resuming from product ${startIndex + 1}...`);
    }

    // Use existing URLs from merged products file if available
    let preloadedUrls = null;
    if (useExistingUrls && fs.existsSync(mergedProductsPath)) {
        const mergedProducts = JSON.parse(fs.readFileSync(mergedProductsPath, 'utf8'));
        preloadedUrls = mergedProducts.map(p => ({
            url: p.url,
            name: p.name
        }));
        console.log(`\n  Using ${preloadedUrls.length} URLs from snigel-merged-products.json`);
    }

    // Launch browser
    const browser = await chromium.launch({
        headless: false,
        args: ['--disable-blink-features=AutomationControlled']
    });

    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        viewport: { width: 1920, height: 1080 }
    });

    // SPEED OPTIMIZATION: Block images, fonts, media to load faster
    // Keep CSS/stylesheets enabled for proper page rendering
    await context.route('**/*', (route) => {
        const resourceType = route.request().resourceType();
        const url = route.request().url();

        // Block heavy resources (but keep CSS for proper rendering)
        if (['image', 'font', 'media'].includes(resourceType)) {
            return route.abort();
        }

        // Block tracking, analytics, and image files
        if (url.includes('google-analytics') ||
            url.includes('googletagmanager') ||
            url.includes('facebook') ||
            url.includes('analytics') ||
            url.includes('.jpg') ||
            url.includes('.jpeg') ||
            url.includes('.png') ||
            url.includes('.gif') ||
            url.includes('.webp') ||
            url.includes('.woff') ||
            url.includes('.woff2')) {
            return route.abort();
        }

        return route.continue();
    });

    const page = await context.newPage();
    page.setDefaultTimeout(60000); // Increase timeout to 60 seconds

    try {
        // Login
        await loginToSnigel(page);

        // Get all product URLs (use preloaded if available)
        let allProducts;
        if (preloadedUrls) {
            allProducts = preloadedUrls;
            console.log(`  ✓ Using ${allProducts.length} preloaded URLs (skipping pagination)\n`);
        } else {
            allProducts = await getAllProductUrls(page);
        }

        // Merge with existing data if resuming
        products = existingData;

        console.log('  Scraping products...\n');

        let scraped = 0;
        let errors = 0;

        for (let i = startIndex; i < allProducts.length; i++) {
            const product = allProducts[i];
            scraped++;

            console.log(`  [${i + 1}/${allProducts.length}] ${product.name.substring(0, 50)}`);

            const data = await scrapeProduct(page, product.url);

            if (data.error) {
                console.log(`    ✗ Error: ${data.error.substring(0, 50)}`);
                errors++;
            } else {
                console.log(`    → Article: ${data.article_no || '(empty)'}`);
                console.log(`    → Weight: ${data.weight || '(empty)'} → ${data.weight_g || 0}g`);
                console.log(`    → Dimensions: ${data.dimensions || '(empty)'}`);

                // Show debug info if article_no is empty (first 3 occurrences only)
                if (!data.article_no && errors < 3) {
                    console.log(`    [DEBUG] Page text: ${data.debug?.substring(0, 80) || 'N/A'}...`);
                }
            }

            // Add or update product
            const existingIndex = products.findIndex(p => p.url === data.url);
            if (existingIndex >= 0) {
                products[existingIndex] = data;
            } else {
                products.push(data);
            }

            // Save progress every N products
            if ((i + 1) % CONFIG.saveEvery === 0) {
                const progress = {
                    lastIndex: i + 1,
                    products: products,
                    timestamp: new Date().toISOString()
                };
                fs.writeFileSync(progressPath, JSON.stringify(progress, null, 2));
                console.log(`    💾 Progress saved (${i + 1} products)\n`);
            }

            await delay(CONFIG.requestDelay);
        }

        // Save final results
        fs.writeFileSync(outputPath, JSON.stringify(products, null, 2));

        // Clean up progress file
        if (fs.existsSync(progressPath)) {
            fs.unlinkSync(progressPath);
        }

        // Summary
        console.log('\n' + '═'.repeat(60));
        console.log('  SCRAPE COMPLETE');
        console.log('═'.repeat(60));
        console.log(`  Total products: ${products.length}`);
        console.log(`  With article_no: ${products.filter(p => p.article_no).length}`);
        console.log(`  With weight: ${products.filter(p => p.weight_g).length}`);
        console.log(`  With dimensions: ${products.filter(p => p.dimensions_mm).length}`);
        console.log(`  Errors: ${errors}`);
        console.log(`  Output: ${outputPath}`);
        console.log('═'.repeat(60));

    } catch (error) {
        console.error('\n  ✗ Error:', error.message);

        // Save progress on error
        const progress = {
            lastIndex: products.length,
            products: products,
            timestamp: new Date().toISOString(),
            error: error.message
        };
        fs.writeFileSync(progressPath, JSON.stringify(progress, null, 2));
        console.log(`  💾 Progress saved. Run with --resume to continue.`);

    } finally {
        await browser.close();
    }
}

// ============================================================
// COMPARE MODE
// ============================================================

async function compareMode() {
    console.log('\n' + '═'.repeat(60));
    console.log('  SNIGEL ARTICLE SYNC - COMPARE MODE');
    console.log('═'.repeat(60));

    const dataPath = path.join(CONFIG.outputDir, CONFIG.outputFiles.products);
    if (!fs.existsSync(dataPath)) {
        console.error('\n  ✗ No scraped data found. Run --scrape first.');
        process.exit(1);
    }

    const snigelProducts = JSON.parse(fs.readFileSync(dataPath, 'utf8'));
    console.log(`\n  Loaded ${snigelProducts.length} Snigel products`);

    // Get Shopware API token
    console.log('  Getting Shopware API token...');
    const tokenRes = await httpRequest(`${CONFIG.shopware.apiUrl}/oauth/token`, {
        method: 'POST',
        body: {
            grant_type: 'client_credentials',
            client_id: CONFIG.shopware.clientId,
            client_secret: CONFIG.shopware.clientSecret
        }
    });

    if (!tokenRes.data.access_token) {
        console.error('  ✗ Failed to get API token');
        process.exit(1);
    }
    const token = tokenRes.data.access_token;
    console.log('  ✓ Token obtained\n');

    // Get all Shopware products
    console.log('  Fetching Shopware products...');
    const shopwareRes = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: {
            limit: 500,
            filter: [
                { type: 'contains', field: 'productNumber', value: 'SN-' }
            ],
            includes: {
                product: ['id', 'productNumber', 'name', 'weight', 'width', 'height', 'length']
            }
        }
    });

    const shopwareProducts = shopwareRes.data.data || [];
    console.log(`  ✓ Found ${shopwareProducts.length} Snigel products in Shopware\n`);

    // Compare
    const comparison = [];
    let matched = 0;
    let unmatched = 0;
    let needsUpdate = 0;

    for (const snigel of snigelProducts) {
        if (!snigel.name) continue;

        // Find matching Shopware product by name
        const shopware = shopwareProducts.find(p => {
            const shopwareName = (p.name || '').toLowerCase().trim();
            const snigelName = (snigel.name || '').toLowerCase().trim();
            return shopwareName === snigelName ||
                   shopwareName.includes(snigelName) ||
                   snigelName.includes(shopwareName);
        });

        if (shopware) {
            matched++;

            const changes = [];

            // Check product number
            if (snigel.article_no && shopware.productNumber !== snigel.article_no) {
                changes.push({
                    field: 'productNumber',
                    from: shopware.productNumber,
                    to: snigel.article_no
                });
            }

            // Check weight
            if (snigel.weight_g && shopware.weight !== snigel.weight_g) {
                changes.push({
                    field: 'weight',
                    from: shopware.weight,
                    to: snigel.weight_g
                });
            }

            // Check dimensions
            if (snigel.dimensions_mm) {
                if (shopware.length !== snigel.dimensions_mm.length) {
                    changes.push({
                        field: 'length',
                        from: shopware.length,
                        to: snigel.dimensions_mm.length
                    });
                }
                if (shopware.width !== snigel.dimensions_mm.width) {
                    changes.push({
                        field: 'width',
                        from: shopware.width,
                        to: snigel.dimensions_mm.width
                    });
                }
                if (shopware.height !== snigel.dimensions_mm.height) {
                    changes.push({
                        field: 'height',
                        from: shopware.height,
                        to: snigel.dimensions_mm.height
                    });
                }
            }

            if (changes.length > 0) {
                needsUpdate++;
                comparison.push({
                    snigelName: snigel.name,
                    shopwareId: shopware.id,
                    shopwareName: shopware.name,
                    changes: changes
                });

                console.log(`  ${snigel.name}`);
                changes.forEach(c => {
                    console.log(`    ${c.field}: ${c.from || '(empty)'} → ${c.to}`);
                });
            }
        } else {
            unmatched++;
        }
    }

    // Save comparison report
    const reportPath = path.join(CONFIG.outputDir, CONFIG.outputFiles.comparison);
    fs.writeFileSync(reportPath, JSON.stringify(comparison, null, 2));

    // Summary
    console.log('\n' + '═'.repeat(60));
    console.log('  COMPARISON COMPLETE');
    console.log('═'.repeat(60));
    console.log(`  Matched: ${matched} products`);
    console.log(`  Unmatched: ${unmatched} products`);
    console.log(`  Needs update: ${needsUpdate} products`);
    console.log(`  Report: ${reportPath}`);
    console.log('\n  To apply changes, run: node snigel-article-sync.js --update');
    console.log('═'.repeat(60));
}

// ============================================================
// UPDATE MODE
// ============================================================

async function updateMode() {
    console.log('\n' + '═'.repeat(60));
    console.log('  SNIGEL ARTICLE SYNC - UPDATE MODE');
    console.log('═'.repeat(60));

    const reportPath = path.join(CONFIG.outputDir, CONFIG.outputFiles.comparison);
    if (!fs.existsSync(reportPath)) {
        console.error('\n  ✗ No comparison report found. Run --compare first.');
        process.exit(1);
    }

    const comparison = JSON.parse(fs.readFileSync(reportPath, 'utf8'));
    console.log(`\n  Loaded ${comparison.length} products to update`);

    // Get Shopware API token
    console.log('  Getting Shopware API token...');
    const tokenRes = await httpRequest(`${CONFIG.shopware.apiUrl}/oauth/token`, {
        method: 'POST',
        body: {
            grant_type: 'client_credentials',
            client_id: CONFIG.shopware.clientId,
            client_secret: CONFIG.shopware.clientSecret
        }
    });

    if (!tokenRes.data.access_token) {
        console.error('  ✗ Failed to get API token');
        process.exit(1);
    }
    const token = tokenRes.data.access_token;
    console.log('  ✓ Token obtained\n');

    // Update products
    const log = [];
    let updated = 0;
    let failed = 0;

    for (const item of comparison) {
        console.log(`  Updating: ${item.snigelName}`);

        // Build update payload
        const payload = {};
        for (const change of item.changes) {
            payload[change.field] = change.to;
        }

        const updateRes = await httpRequest(`${CONFIG.shopware.apiUrl}/product/${item.shopwareId}`, {
            method: 'PATCH',
            headers: { 'Authorization': `Bearer ${token}` },
            body: payload
        });

        if (updateRes.status === 204 || updateRes.status === 200) {
            console.log(`    ✓ Updated ${item.changes.length} fields`);
            updated++;

            log.push({
                timestamp: new Date().toISOString(),
                productId: item.shopwareId,
                name: item.snigelName,
                changes: item.changes,
                status: 'success'
            });
        } else {
            console.log(`    ✗ Failed: ${updateRes.status}`);
            failed++;

            log.push({
                timestamp: new Date().toISOString(),
                productId: item.shopwareId,
                name: item.snigelName,
                error: `API error: ${updateRes.status}`,
                status: 'failed'
            });
        }

        await delay(300);
    }

    // Save log
    const logPath = path.join(CONFIG.outputDir, CONFIG.outputFiles.log);
    fs.writeFileSync(logPath, JSON.stringify(log, null, 2));

    // Summary
    console.log('\n' + '═'.repeat(60));
    console.log('  UPDATE COMPLETE');
    console.log('═'.repeat(60));
    console.log(`  ✓ Updated: ${updated} products`);
    console.log(`  ✗ Failed: ${failed} products`);
    console.log(`  Log: ${logPath}`);
    console.log('═'.repeat(60));
}

// ============================================================
// MAIN
// ============================================================

async function main() {
    const args = process.argv.slice(2);

    let mode = '--scrape';
    let resume = false;
    let useExistingUrls = false;

    for (const arg of args) {
        if (arg === '--scrape' || arg === '--compare' || arg === '--update') {
            mode = arg;
        }
        if (arg === '--resume') {
            resume = true;
        }
        if (arg === '--use-urls') {
            useExistingUrls = true;
        }
    }

    console.log('\n' + '╔' + '═'.repeat(58) + '╗');
    console.log('║' + '  SNIGEL ARTICLE NUMBER SYNC TOOL'.padEnd(58) + '║');
    console.log('║' + '  Syncs: article_no, weight, dimensions'.padEnd(58) + '║');
    console.log('╚' + '═'.repeat(58) + '╝');

    try {
        if (mode === '--scrape') {
            await scrapeMode(resume, useExistingUrls);
        } else if (mode === '--compare') {
            await compareMode();
        } else if (mode === '--update') {
            await updateMode();
        }
    } catch (error) {
        console.error('\n  ✗ Fatal error:', error.message);
        console.error(error.stack);
        process.exit(1);
    }
}

main();
