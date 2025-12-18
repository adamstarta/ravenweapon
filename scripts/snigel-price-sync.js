/**
 * Snigel Price Sync Tool
 *
 * Compares Snigel RRP prices with ortak.ch and updates Shopware
 * Formula: RRP EUR × 0.9332 = Retail CHF (ECB rate)
 *
 * Usage:
 *   node snigel-price-sync.js --compare    # Generate comparison report
 *   node snigel-price-sync.js --update     # Update prices (after approval)
 *   node snigel-price-sync.js --dry-run    # Show what would be updated
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const https = require('https');

// ============================================================
// CONFIGURATION
// ============================================================
const CONFIG = {
    // Pricing formula: RRP EUR × 0.9332 = CHF (ECB rate 17 Dec 2025)
    eurToChf: 0.9332,      // EUR to CHF real exchange rate
    tolerance: 5,          // % difference to flag as mismatch

    // Snigel B2B Portal
    snigel: {
        baseUrl: 'https://products.snigel.se',
        username: 'Raven Weapon AG',
        password: 'wVREVbRZfqT&Fba@f(^2UKOw'
    },

    // Shopware
    shopware: {
        baseUrl: 'https://ortak.ch',
        apiUrl: 'https://ortak.ch/api',
        // Integration credentials (more reliable than admin password)
        clientId: 'SWIARAVEN03399CEA2C931269',
        clientSecret: 'RavenNavbarUpdate2025!',
        categoryUrl: 'https://ortak.ch/Ausruestung/'
    }
};

const OUTPUT_DIR = path.join(__dirname, 'snigel-comparison');
if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

// ============================================================
// HELPERS
// ============================================================
function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function calculateExpectedChf(rrpEur) {
    // RRP EUR × 1.07 = CHF (EUR to CHF)
    return Math.round(rrpEur * CONFIG.eurToChf * 100) / 100;
}

// Parse European number format: "1 333,58" → 1333.58
function parseEuropeanNumber(text) {
    if (!text) return null;
    // Remove currency symbols and trim
    let clean = text.replace(/[€$£]/g, '').trim();
    // Remove spaces (thousand separator in European format)
    clean = clean.replace(/\s/g, '');
    // Remove dots (thousand separator in some formats)
    clean = clean.replace(/\./g, '');
    // Replace comma with dot (decimal separator)
    clean = clean.replace(',', '.');
    const num = parseFloat(clean);
    return isNaN(num) ? null : num;
}

function normalizeName(name) {
    return name.toLowerCase()
        .replace(/[®™©×x]/g, ' ')
        .replace(/\s+/g, ' ')
        .replace(/[^a-z0-9\s.-]/g, '')
        .trim();
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

// ============================================================
// STEP 1: SCRAPE SNIGEL B2B PORTAL (RRP PRICES) - HYBRID MODE
// Uses API for product list + Browser for EUR RRP prices
// ============================================================
async function scrapeSnigel(browser) {
    console.log('\n' + '═'.repeat(60));
    console.log('  STEP 1: SCRAPING SNIGEL B2B PORTAL (EUR PRICES)');
    console.log('═'.repeat(60));

    // PHASE 1: Get all products from API (FAST - ~2 seconds)
    console.log('\n  [Phase 1] Getting product list from API...');
    const apiProducts = [];

    for (let page = 1; page <= 3; page++) {
        const url = `${CONFIG.snigel.baseUrl}/wp-json/wc/store/v1/products?per_page=100&page=${page}`;
        try {
            const response = await httpRequest(url);
            if (response.data && Array.isArray(response.data)) {
                response.data.forEach(p => {
                    apiProducts.push({
                        name: p.name,
                        slug: p.slug,
                        url: p.permalink
                    });
                });
                console.log(`    Page ${page}: +${response.data.length} products`);
                if (response.data.length < 100) break;
            }
        } catch (e) {
            console.log(`    Page ${page}: Error - ${e.message}`);
        }
    }
    console.log(`  ✓ Found ${apiProducts.length} products via API`);

    // PHASE 2: Browser scraping for SEK RRP prices (default currency)
    console.log('\n  [Phase 2] Scraping SEK RRP prices from browser...');

    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        viewport: { width: 1280, height: 720 }
    });

    const page = await context.newPage();
    page.setDefaultTimeout(30000);

    try {
        // Login first (need full page for login form)
        console.log('    Logging in...');
        await page.goto(`${CONFIG.snigel.baseUrl}/my-account/`);
        await delay(1500);
        try { await page.click('button:has-text("Ok")', { timeout: 1500 }); } catch (e) {}

        await page.fill('#username', CONFIG.snigel.username);
        await page.fill('#password', CONFIG.snigel.password);
        await page.click('button[name="login"]');
        await delay(1500);
        console.log('    ✓ Logged in!');

        // Visit product page to ensure session (EUR after login)
        await page.goto(`${CONFIG.snigel.baseUrl}/product-category/all/`);
        await delay(1000);
        try { await page.click('button:has-text("Ok")', { timeout: 1000 }); } catch (e) {}
        console.log('    ✓ Ready to scrape EUR prices');

        // NOW block heavy resources for faster product scraping
        await context.route('**/*', (route) => {
            const resourceType = route.request().resourceType();
            if (['image', 'stylesheet', 'font', 'media'].includes(resourceType)) {
                route.abort();
            } else {
                route.continue();
            }
        });
        console.log('    ✓ Resource blocking enabled for speed');

        // Scrape RRP from each product page - FAST MODE
        const products = [];
        let successCount = 0;
        let failCount = 0;

        for (let i = 0; i < apiProducts.length; i++) {
            const product = apiProducts[i];
            try {
                // EUR is default after login for B2B dealers - FAST MODE
                await page.goto(product.url, { waitUntil: 'commit', timeout: 15000 });
                await page.waitForSelector('.woocommerce-Price-amount', { timeout: 8000 }).catch(() => {});

                const rrpEur = await page.evaluate(() => {
                    function parseEurPrice(str) {
                        if (!str) return null;
                        // EUR format: "1 234,56 €" or "17,99 €"
                        let clean = str.replace(/[€\s]/g, '');  // Remove € and spaces
                        clean = clean.replace(/\./g, '');       // Remove dots (thousand sep)
                        clean = clean.replace(',', '.');        // Comma to decimal
                        const num = parseFloat(clean);
                        return isNaN(num) ? null : num;
                    }

                    // Priority 1: RRP text pattern "RRP 1 333,58 €"
                    const pageText = document.body.innerText;
                    const rrpMatch = pageText.match(/RRP\s+([\d\s.,]+)\s*€/i);
                    if (rrpMatch) {
                        const price = parseEurPrice(rrpMatch[1]);
                        if (price > 0) return price;
                    }

                    // Priority 2: FIRST EUR price (not in related)
                    const allPrices = document.querySelectorAll('.woocommerce-Price-amount');
                    for (const el of allPrices) {
                        if (el.closest('.related, .up-sells, .cross-sells')) continue;
                        const text = el.textContent.trim();
                        if (text.includes('€')) {
                            return parseEurPrice(text);
                        }
                    }

                    // Priority 3: Any EUR price on page
                    for (const el of allPrices) {
                        const text = el.textContent.trim();
                        if (text.includes('€')) {
                            return parseEurPrice(text);
                        }
                    }
                    return null;
                });

                if (rrpEur && rrpEur > 0) {
                    products.push({ name: product.name, slug: product.slug, url: product.url, rrpEur });
                    successCount++;
                } else {
                    failCount++;
                }
            } catch (e) {
                // Quick retry
                try {
                    await page.goto(product.url, { waitUntil: 'commit', timeout: 10000 });
                    await page.waitForSelector('.woocommerce-Price-amount', { timeout: 2000 }).catch(() => {});

                    const rrpEur = await page.evaluate(() => {
                        function parseEurPrice(str) {
                            if (!str) return null;
                            let clean = str.replace(/[€\s]/g, '').replace(/\./g, '').replace(',', '.');
                            const num = parseFloat(clean);
                            return isNaN(num) ? null : num;
                        }

                        const allPrices = document.querySelectorAll('.woocommerce-Price-amount');
                        for (const el of allPrices) {
                            if (el.closest('.related, .up-sells, .cross-sells')) continue;
                            const text = el.textContent.trim();
                            if (text.includes('€')) return parseEurPrice(text);
                        }
                        for (const el of allPrices) {
                            const text = el.textContent.trim();
                            if (text.includes('€')) return parseEurPrice(text);
                        }
                        return null;
                    });

                    if (rrpEur && rrpEur > 0) {
                        products.push({ name: product.name, slug: product.slug, url: product.url, rrpEur });
                        successCount++;
                    } else {
                        failCount++;
                    }
                } catch (e2) {
                    failCount++;
                }
            }

            // Progress every 10 products
            if ((i + 1) % 10 === 0 || i === apiProducts.length - 1) {
                process.stdout.write(`    [${i + 1}/${apiProducts.length}] ✓ ${successCount} prices | ✗ ${failCount} failed   \r`);
            }

            // Small delay between requests to avoid rate limiting
            await delay(200);
        }

        console.log(`\n    Final: ${successCount} success, ${failCount} failed`);
        console.log(`\n  ✓ Extracted ${products.length} products with RRP prices`);

        await context.close();
        return products;

    } catch (error) {
        console.error('  ✗ Snigel error:', error.message);
        await context.close();
        return [];
    }
}

// ============================================================
// STEP 2: SCRAPE ORTAK.CH STOREFRONT
// ============================================================
async function scrapeStorefront(browser) {
    console.log('\n' + '═'.repeat(60));
    console.log('  STEP 2: SCRAPING ORTAK.CH STOREFRONT');
    console.log('═'.repeat(60));

    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        viewport: { width: 1920, height: 1080 }
    });
    const page = await context.newPage();
    page.setDefaultTimeout(120000);

    const products = [];
    const seen = new Set();

    try {
        let pageNum = 1;
        const maxPages = 20;
        let emptyPageCount = 0;

        while (pageNum <= maxPages && emptyPageCount < 3) {
            const url = pageNum === 1
                ? CONFIG.shopware.categoryUrl
                : `${CONFIG.shopware.categoryUrl}?p=${pageNum}`;

            console.log(`\n  Loading page ${pageNum}...`);

            try {
                await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
                await delay(4000);
            } catch (e) {
                console.log(`    Retry page ${pageNum}...`);
                await page.goto(url, { waitUntil: 'load', timeout: 90000 });
                await delay(5000);
            }

            // Scroll to load lazy images
            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
            await delay(2000);
            await page.evaluate(() => window.scrollTo(0, 0));
            await delay(1000);

            // Extract products - improved method
            const pageProducts = await page.evaluate(() => {
                const results = [];
                const localSeen = new Set();

                // Method 1: Find product boxes/cards directly
                const productBoxes = document.querySelectorAll('.product-box, .cms-listing-col, [class*="product-box"]');

                productBoxes.forEach(box => {
                    // Get name from image alt, title link, or product-name class
                    let name = '';
                    const img = box.querySelector('img');
                    const nameLink = box.querySelector('.product-name, [class*="product-name"] a, a[title]');

                    if (nameLink) {
                        name = nameLink.textContent?.trim() || nameLink.getAttribute('title') || '';
                    }
                    if (!name && img) {
                        name = img.alt || '';
                    }

                    if (!name || localSeen.has(name)) return;
                    localSeen.add(name);

                    // Get price - look in multiple places
                    let price = 0;
                    const priceText = box.textContent || '';
                    const priceMatches = priceText.match(/CHF\s*([\d',\.]+)/g);

                    if (priceMatches && priceMatches.length > 0) {
                        // Take the first CHF price found (usually the main price)
                        const match = priceMatches[0].match(/CHF\s*([\d',\.]+)/);
                        if (match) {
                            price = parseFloat(match[1].replace(/'/g, '').replace(',', '.'));
                        }
                    }

                    // Get URL
                    const link = box.querySelector('a[href*="/"]');
                    const url = link ? link.href : '';

                    if (name && price > 0) {
                        results.push({ name, priceChf: price, url });
                    }
                });

                // Method 2: Fallback - find product links with images
                if (results.length < 5) {
                    const main = document.querySelector('main');
                    if (main) {
                        const productLinks = main.querySelectorAll('a[href*="/ausruestung/"]');

                        productLinks.forEach(link => {
                            const img = link.querySelector('img');
                            if (!img) return;

                            const name = img.alt || link.textContent.trim();
                            if (!name || localSeen.has(name)) return;
                            localSeen.add(name);

                            // Look for price in parent container
                            const parent = link.closest('[class*="product"]') || link.parentElement?.parentElement?.parentElement;
                            let price = 0;

                            if (parent) {
                                const priceMatches = parent.textContent.match(/CHF\s*([\d',\.]+)/g);
                                if (priceMatches && priceMatches.length > 0) {
                                    const match = priceMatches[0].match(/CHF\s*([\d',\.]+)/);
                                    if (match) {
                                        price = parseFloat(match[1].replace(/'/g, '').replace(',', '.'));
                                    }
                                }
                            }

                            if (name && price > 0) {
                                results.push({ name, priceChf: price, url: link.href });
                            }
                        });
                    }
                }

                return results;
            });

            // Add unique products
            let newCount = 0;
            for (const p of pageProducts) {
                if (!seen.has(p.name)) {
                    seen.add(p.name);
                    products.push(p);
                    newCount++;
                }
            }

            console.log(`    Found ${newCount} new products (total: ${products.length})`);

            if (newCount === 0) {
                emptyPageCount++;
            } else {
                emptyPageCount = 0;
            }

            pageNum++;
        }

        console.log(`\n  ✓ Total storefront products: ${products.length}`);
        await context.close();
        return products;

    } catch (error) {
        console.error('  ✗ Storefront error:', error.message);
        await context.close();
        return products;
    }
}

// ============================================================
// STEP 3: COMPARE PRICES
// ============================================================
function comparePrices(snigelProducts, storefrontProducts) {
    console.log('\n' + '═'.repeat(60));
    console.log('  STEP 3: COMPARING PRICES');
    console.log('═'.repeat(60));
    console.log(`\n  Formula: RRP EUR × ${CONFIG.eurToChf} = Expected CHF`);
    console.log(`  Tolerance: ${CONFIG.tolerance}%`);

    const results = {
        matched: [],
        mismatched: [],
        missing: []
    };

    // Create lookup by normalized name
    const storeByName = {};
    storefrontProducts.forEach(p => {
        storeByName[normalizeName(p.name)] = p;
    });

    // Compare each Snigel product
    for (const snigel of snigelProducts) {
        const expectedChf = calculateExpectedChf(snigel.rrpEur);
        const snigelNorm = normalizeName(snigel.name);

        // Find match
        let storeProduct = storeByName[snigelNorm];

        if (!storeProduct) {
            // Try partial match
            for (const [key, prod] of Object.entries(storeByName)) {
                if (key.includes(snigelNorm) || snigelNorm.includes(key)) {
                    storeProduct = prod;
                    break;
                }
                // Word match
                const snigelWords = snigelNorm.split(' ').filter(w => w.length > 2);
                const storeWords = key.split(' ').filter(w => w.length > 2);
                const matching = snigelWords.filter(w => storeWords.some(sw => sw.includes(w) || w.includes(sw)));
                if (matching.length >= Math.max(2, snigelWords.length * 0.5)) {
                    storeProduct = prod;
                    break;
                }
            }
        }

        if (!storeProduct) {
            results.missing.push({
                name: snigel.name,
                rrpEur: snigel.rrpEur,
                expectedChf
            });
            continue;
        }

        // Calculate difference
        const diff = storeProduct.priceChf - expectedChf;
        const percentDiff = (diff / expectedChf) * 100;

        const item = {
            name: snigel.name,
            storeName: storeProduct.name,
            productId: storeProduct.productId,
            rrpEur: snigel.rrpEur,
            expectedChf,
            actualChf: storeProduct.priceChf,
            diff,
            percentDiff: percentDiff.toFixed(1)
        };

        if (Math.abs(percentDiff) > CONFIG.tolerance) {
            // Determine severity
            if (Math.abs(percentDiff) > 50) item.status = 'CRITICAL';
            else if (Math.abs(percentDiff) > 20) item.status = 'HIGH';
            else item.status = 'MEDIUM';

            results.mismatched.push(item);
        } else {
            results.matched.push(item);
        }
    }

    // Sort mismatched by severity
    results.mismatched.sort((a, b) => Math.abs(b.diff) - Math.abs(a.diff));

    return results;
}

// ============================================================
// STEP 4: GENERATE REPORT
// ============================================================
function generateReport(results) {
    console.log('\n' + '═'.repeat(60));
    console.log('  PRICE COMPARISON REPORT');
    console.log('═'.repeat(60));

    console.log('\n  ┌─────────────────────────────────────┐');
    console.log('  │           SUMMARY                   │');
    console.log('  ├─────────────────────────────────────┤');
    console.log(`  │  ✓ Prices OK:        ${String(results.matched.length).padStart(3)}           │`);
    console.log(`  │  ✗ MISMATCHED:       ${String(results.mismatched.length).padStart(3)}           │`);
    console.log(`  │  ? Missing in store: ${String(results.missing.length).padStart(3)}           │`);
    console.log('  └─────────────────────────────────────┘');

    if (results.mismatched.length > 0) {
        console.log('\n' + '═'.repeat(60));
        console.log('  PRICE MISMATCHES - NEED FIXING!');
        console.log('═'.repeat(60));

        for (const item of results.mismatched) {
            const diffStr = item.diff > 0 ? `+${item.diff.toFixed(2)}` : item.diff.toFixed(2);
            const arrow = item.diff > 0 ? '↑' : '↓';

            console.log(`\n  ╔${'═'.repeat(56)}╗`);
            console.log(`  ║ [${item.status}] ${item.name.substring(0, 45).padEnd(45)} ║`);
            console.log(`  ╠${'═'.repeat(56)}╣`);
            console.log(`  ║  RRP EUR:      €${item.rrpEur.toFixed(2).padEnd(38)}║`);
            console.log(`  ║  Expected CHF: CHF ${item.expectedChf.toFixed(2).padEnd(35)}║`);
            console.log(`  ║  Current CHF:  CHF ${item.actualChf.toFixed(2).padEnd(35)}║`);
            console.log(`  ║  Difference:   ${diffStr} CHF (${item.percentDiff}%) ${arrow}`.padEnd(58) + '║');
            console.log(`  ╚${'═'.repeat(56)}╝`);
        }
    }

    // Save reports
    const timestamp = new Date().toISOString().slice(0, 19).replace(/[:.]/g, '-');

    // JSON report
    const jsonPath = path.join(OUTPUT_DIR, `report-${timestamp}.json`);
    fs.writeFileSync(jsonPath, JSON.stringify(results, null, 2));

    // CSV report
    const csvLines = ['Product,RRP EUR,Expected CHF,Current CHF,Difference,Percent,Status,Product ID'];
    for (const item of results.mismatched) {
        csvLines.push(`"${item.name}",${item.rrpEur},${item.expectedChf.toFixed(2)},${item.actualChf.toFixed(2)},${item.diff.toFixed(2)},${item.percentDiff}%,${item.status},${item.productId || ''}`);
    }
    const csvPath = path.join(OUTPUT_DIR, `mismatches-${timestamp}.csv`);
    fs.writeFileSync(csvPath, csvLines.join('\n'));

    // Backup for update
    const backupPath = path.join(OUTPUT_DIR, 'pending-updates.json');
    fs.writeFileSync(backupPath, JSON.stringify(results.mismatched, null, 2));

    console.log('\n  ┌─────────────────────────────────────┐');
    console.log('  │           FILES SAVED               │');
    console.log('  ├─────────────────────────────────────┤');
    console.log(`  │  JSON: report-${timestamp}.json`);
    console.log(`  │  CSV:  mismatches-${timestamp}.csv`);
    console.log('  └─────────────────────────────────────┘');

    return results;
}

// ============================================================
// STEP 5: UPDATE SHOPWARE PRICES
// ============================================================
async function updateShopwarePrices(dryRun = true) {
    console.log('\n' + '═'.repeat(60));
    console.log(dryRun ? '  DRY RUN - SHOWING WHAT WOULD BE UPDATED' : '  UPDATING SHOPWARE PRICES');
    console.log('═'.repeat(60));

    // Load pending updates
    const pendingPath = path.join(OUTPUT_DIR, 'pending-updates.json');
    if (!fs.existsSync(pendingPath)) {
        console.log('\n  ✗ No pending updates found. Run --compare first.');
        return;
    }

    const updates = JSON.parse(fs.readFileSync(pendingPath, 'utf8'));
    console.log(`\n  Found ${updates.length} products to update`);

    if (dryRun) {
        console.log('\n  DRY RUN - No changes will be made:\n');
        for (const item of updates) {
            console.log(`  ${item.name}`);
            console.log(`    CHF ${item.actualChf.toFixed(2)} → CHF ${item.expectedChf.toFixed(2)}`);
            console.log('');
        }
        console.log('  To apply changes, run: node snigel-price-sync.js --update');
        return;
    }

    // Get Shopware token using integration credentials
    console.log('\n  Getting Shopware API token...');
    const tokenRes = await httpRequest(`${CONFIG.shopware.apiUrl}/oauth/token`, {
        method: 'POST',
        body: {
            grant_type: 'client_credentials',
            client_id: CONFIG.shopware.clientId,
            client_secret: CONFIG.shopware.clientSecret
        }
    });

    if (!tokenRes.data.access_token) {
        console.log('  ✗ Failed to get API token');
        console.log('  Response:', JSON.stringify(tokenRes.data).substring(0, 500));
        return;
    }
    const token = tokenRes.data.access_token;
    console.log('  ✓ Token obtained');

    // CHF currency ID for ortak.ch
    const CHF_CURRENCY_ID = '0191c12cf40d718a8a3439b74a6f083c';

    // Backup old prices
    const backupData = [];

    // Update each product
    let updated = 0, failed = 0, notFound = 0;

    for (const item of updates) {
        const searchName = item.storeName || item.name;

        // Search for product by name
        const searchRes = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: {
                limit: 5,
                filter: [
                    { type: 'contains', field: 'name', value: searchName }
                ]
            }
        });

        if (!searchRes.data.data || searchRes.data.data.length === 0) {
            // Try partial search with key words
            const words = searchName.split(' ').filter(w => w.length > 3).slice(0, 3);
            let found = null;

            for (const word of words) {
                const retryRes = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}` },
                    body: {
                        limit: 10,
                        filter: [
                            { type: 'contains', field: 'name', value: word }
                        ]
                    }
                });

                if (retryRes.data.data) {
                    // Find exact or close match
                    found = retryRes.data.data.find(p =>
                        p.name.toLowerCase().includes(searchName.toLowerCase()) ||
                        searchName.toLowerCase().includes(p.name.toLowerCase())
                    );
                    if (found) break;
                }
            }

            if (!found) {
                console.log(`  ✗ ${searchName} - Not found in Shopware`);
                notFound++;
                continue;
            }

            searchRes.data.data = [found];
        }

        // Find exact match or best match
        const product = searchRes.data.data.find(p =>
            p.name.toLowerCase() === searchName.toLowerCase()
        ) || searchRes.data.data[0];

        const productId = product.id;

        // Backup old price
        backupData.push({
            productId: productId,
            name: item.name,
            oldPrice: item.actualChf,
            newPrice: item.expectedChf
        });

        // Update price
        console.log(`  Updating: ${item.name}`);

        const updateRes = await httpRequest(`${CONFIG.shopware.apiUrl}/product/${productId}`, {
            method: 'PATCH',
            headers: { 'Authorization': `Bearer ${token}` },
            body: {
                price: [{
                    currencyId: CHF_CURRENCY_ID,
                    gross: item.expectedChf,
                    net: Math.round(item.expectedChf / 1.081 * 100) / 100, // 8.1% Swiss VAT
                    linked: false
                }]
            }
        });

        if (updateRes.status === 204 || updateRes.status === 200) {
            console.log(`    ✓ CHF ${item.actualChf.toFixed(2)} → CHF ${item.expectedChf.toFixed(2)}`);
            updated++;
        } else {
            console.log(`    ✗ Failed: ${updateRes.status} - ${JSON.stringify(updateRes.data).substring(0, 200)}`);
            failed++;
        }

        await delay(300); // Rate limiting
    }

    // Save backup
    const backupPath = path.join(OUTPUT_DIR, `backup-${Date.now()}.json`);
    fs.writeFileSync(backupPath, JSON.stringify(backupData, null, 2));

    console.log('\n  ┌─────────────────────────────────────┐');
    console.log('  │           UPDATE COMPLETE           │');
    console.log('  ├─────────────────────────────────────┤');
    console.log(`  │  ✓ Updated:   ${String(updated).padStart(3)} products         │`);
    console.log(`  │  ✗ Failed:    ${String(failed).padStart(3)} products         │`);
    console.log(`  │  ? Not found: ${String(notFound).padStart(3)} products         │`);
    console.log(`  │  Backup saved to backup-*.json      │`);
    console.log('  └─────────────────────────────────────┘');
}

// ============================================================
// MAIN
// ============================================================
async function main() {
    const args = process.argv.slice(2);
    const mode = args[0] || '--compare';

    console.log('\n' + '╔' + '═'.repeat(58) + '╗');
    console.log('║' + '       SNIGEL PRICE SYNC TOOL'.padEnd(58) + '║');
    console.log('║' + `       Formula: RRP EUR × ${CONFIG.eurToChf} = CHF`.padEnd(58) + '║');
    console.log('╚' + '═'.repeat(58) + '╝');

    if (mode === '--update') {
        await updateShopwarePrices(false);
        return;
    }

    if (mode === '--dry-run') {
        await updateShopwarePrices(true);
        return;
    }

    // Default: --compare
    const browser = await chromium.launch({ headless: true }); // HEADLESS for speed

    try {
        // Step 1: Scrape Snigel
        const snigelProducts = await scrapeSnigel(browser);
        if (snigelProducts.length === 0) {
            console.log('\n  ✗ Failed to scrape Snigel. Exiting.');
            return;
        }

        // Step 2: Scrape storefront
        const storefrontProducts = await scrapeStorefront(browser);
        if (storefrontProducts.length === 0) {
            console.log('\n  ✗ Failed to scrape storefront. Exiting.');
            return;
        }

        // Step 3: Compare
        const results = comparePrices(snigelProducts, storefrontProducts);

        // Step 4: Generate report
        generateReport(results);

        console.log('\n' + '═'.repeat(60));
        console.log('  NEXT STEPS');
        console.log('═'.repeat(60));
        console.log('\n  1. Review the CSV file in snigel-comparison/');
        console.log('  2. If prices look correct, run:');
        console.log('     node snigel-price-sync.js --dry-run');
        console.log('  3. To apply changes:');
        console.log('     node snigel-price-sync.js --update');
        console.log('');

    } catch (error) {
        console.error('\n  ✗ Error:', error.message);
    } finally {
        await browser.close();
    }
}

main();
