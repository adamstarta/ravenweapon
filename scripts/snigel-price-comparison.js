/**
 * Snigel Price Comparison Tool
 * Compares prices between:
 * 1. Snigel B2B Portal (source of truth)
 * 2. Local scraped data (snigel-merged-products.json)
 * 3. Shopware (ortak.ch) - actual store prices
 *
 * Generates detailed report of all mismatches
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const https = require('https');

// Configuration
const CONFIG = {
    snigel: {
        baseUrl: 'https://products.snigel.se',
        username: 'Raven Weapon AG',
        password: 'wVREVbRZfqT&Fba@f(^2UKOw'
    },
    shopware: {
        url: 'https://ortak.ch',
        username: 'admin',
        password: 'shopware',
        chfCurrencyId: '0191c12cf40d718a8a3439b74a6f083c'
    },
    // EUR to CHF conversion rate (adjust as needed)
    eurToChf: 0.93,
    // Markup from B2B to retail (e.g., 1.5 = 50% markup)
    retailMarkup: 1.5,
    // Price tolerance for comparison (in percentage)
    priceTolerance: 5
};

const OUTPUT_DIR = path.join(__dirname, 'snigel-comparison');
if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

// Helper: Delay
function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// Helper: HTTP Request
function httpRequest(url, options = {}) {
    return new Promise((resolve, reject) => {
        const urlObj = new URL(url);
        const reqOptions = {
            hostname: urlObj.hostname,
            port: 443,
            path: urlObj.pathname + urlObj.search,
            method: options.method || 'GET',
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
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

// Step 1: Scrape Snigel B2B Portal
async function scrapeSnigel() {
    console.log('\n' + '='.repeat(60));
    console.log('STEP 1: SCRAPING SNIGEL B2B PORTAL');
    console.log('='.repeat(60));

    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        viewport: { width: 1920, height: 1080 }
    });
    const page = await context.newPage();
    page.setDefaultTimeout(60000);

    try {
        // Login
        console.log('\nLogging in to Snigel B2B...');
        await page.goto(`${CONFIG.snigel.baseUrl}/my-account/`);
        await page.waitForLoadState('networkidle');
        await delay(2000);

        // Accept cookies if present
        try {
            await page.click('button:has-text("Ok")', { timeout: 3000 });
        } catch (e) {}

        await page.fill('#username', CONFIG.snigel.username);
        await page.fill('#password', CONFIG.snigel.password);
        await page.click('button[name="login"]');
        await page.waitForLoadState('networkidle');
        await delay(3000);
        console.log('Login successful!');

        // Navigate to all products with EUR
        console.log('\nLoading products (EUR currency)...');
        await page.goto(`${CONFIG.snigel.baseUrl}/product-category/all/?currency=EUR`);
        await page.waitForLoadState('networkidle');
        await delay(5000);

        try {
            await page.click('button:has-text("Ok")', { timeout: 2000 });
        } catch (e) {}

        // Scroll to load all products
        console.log('Scrolling to load all products...');
        let lastCount = 0;
        let stableCount = 0;

        while (stableCount < 8) {
            await page.evaluate(() => window.scrollBy(0, 2000));
            await delay(2000);

            // Count using product links
            const count = await page.$$eval('a[href*="/product/"]', links => {
                const seen = new Set();
                links.forEach(l => {
                    const slug = l.href.split('/product/')[1]?.replace(/\/$/, '');
                    if (slug) seen.add(slug);
                });
                return seen.size;
            });

            if (count === lastCount) {
                stableCount++;
            } else {
                stableCount = 0;
                lastCount = count;
                process.stdout.write(`  Found ${count} unique products...   \r`);
            }
        }
        console.log(`\nTotal unique products found: ${lastCount}`);

        // Extract products using flexible approach
        console.log('\nExtracting product data...');
        await page.evaluate(() => window.scrollTo(0, 0));
        await delay(2000);

        const products = await page.evaluate(() => {
            const results = [];
            const seen = new Set();

            // Find all links to products
            const productLinks = document.querySelectorAll('a[href*="/product/"]');

            productLinks.forEach(link => {
                const url = link.href;
                const slug = url.split('/product/')[1]?.replace(/\/$/, '');
                if (!slug || seen.has(slug)) return;
                seen.add(slug);

                // Get parent container
                const container = link.closest('.tmb') ||
                                 link.closest('.product') ||
                                 link.closest('li') ||
                                 link.parentElement?.parentElement;

                const product = { url, slug };

                // Get name from title element first, then image alt
                const title = container?.querySelector('.t-entry-title a') ||
                             container?.querySelector('.t-entry-title') ||
                             container?.querySelector('h3 a') ||
                             container?.querySelector('h2 a');
                if (title) {
                    product.name = title.textContent.trim();
                } else {
                    const img = container?.querySelector('img') || link.querySelector('img');
                    if (img && img.alt) {
                        product.name = img.alt.trim();
                    }
                }

                // Get price - look for price elements in container
                const priceEl = container?.querySelector('.woocommerce-Price-amount bdi') ||
                               container?.querySelector('.woocommerce-Price-amount') ||
                               container?.querySelector('.price ins .amount') ||
                               container?.querySelector('.price .amount');
                if (priceEl) {
                    const text = priceEl.textContent.trim();
                    // Match price like "30,22 €" or "30.22€" or "€30.22"
                    const match = text.match(/([\d.,]+)\s*€/) || text.match(/€\s*([\d.,]+)/);
                    if (match) {
                        let priceStr = match[1];
                        // European format: 1.234,56 -> 1234.56
                        if (priceStr.includes(',')) {
                            priceStr = priceStr.replace('.', '').replace(',', '.');
                        }
                        const price = parseFloat(priceStr);
                        if (!isNaN(price) && price > 0) {
                            product.b2b_price_eur = price;
                        }
                    }
                }

                // Stock status
                const classList = container?.className || '';
                product.in_stock = !classList.includes('outofstock');

                if (product.name) {
                    results.push(product);
                }
            });

            return results;
        });

        console.log(`Extracted ${products.length} products from Snigel B2B`);
        await browser.close();
        return products;

    } catch (error) {
        console.error('Error scraping Snigel:', error.message);
        await browser.close();
        return [];
    }
}

// Step 2: Get Shopware Token
async function getShopwareToken() {
    const response = await httpRequest(`${CONFIG.shopware.url}/api/oauth/token`, {
        method: 'POST',
        body: {
            grant_type: 'password',
            client_id: 'administration',
            username: CONFIG.shopware.username,
            password: CONFIG.shopware.password
        }
    });
    return response.data.access_token;
}

// Step 3: Get Shopware Snigel Products
async function getShopwareProducts(token) {
    console.log('\n' + '='.repeat(60));
    console.log('STEP 2: GETTING SHOPWARE PRODUCTS');
    console.log('='.repeat(60));

    const products = [];
    let page = 1;

    // First, find Snigel manufacturer ID
    console.log('\nFinding Snigel manufacturer...');
    const mfrResponse = await httpRequest(`${CONFIG.shopware.url}/api/search/product-manufacturer`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: {
            filter: [{ type: 'contains', field: 'name', value: 'Snigel' }]
        }
    });

    let snigelManufacturerId = null;
    if (mfrResponse.data.data && mfrResponse.data.data.length > 0) {
        snigelManufacturerId = mfrResponse.data.data[0].id;
        console.log(`Found Snigel manufacturer ID: ${snigelManufacturerId}`);
    }

    // Get all products (filter by manufacturer if found)
    console.log('\nFetching products from Shopware...');
    while (true) {
        const body = {
            limit: 500,
            page: page,
            associations: { prices: {} }
        };

        // Filter by manufacturer if we found it
        if (snigelManufacturerId) {
            body.filter = [{ type: 'equals', field: 'manufacturerId', value: snigelManufacturerId }];
        }

        const response = await httpRequest(`${CONFIG.shopware.url}/api/search/product`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body
        });

        if (!response.data.data || response.data.data.length === 0) break;

        for (const product of response.data.data) {
            const name = product.translated?.name || product.name || '';
            const productNumber = product.productNumber || '';

            // Get CHF price
            let priceChf = 0;
            if (product.price && Array.isArray(product.price)) {
                const chfPrice = product.price.find(p => p.currencyId === CONFIG.shopware.chfCurrencyId);
                priceChf = chfPrice?.gross || product.price[0]?.gross || 0;
            }

            products.push({
                id: product.id,
                productNumber,
                name,
                priceChf,
                active: product.active
            });
        }

        console.log(`Fetched ${products.length} products...`);
        if (response.data.data.length < 500) break;
        page++;
    }

    // If no manufacturer filter, filter by name
    if (!snigelManufacturerId) {
        const snigelProducts = products.filter(p =>
            p.name.toLowerCase().includes('snigel') ||
            p.productNumber.toLowerCase().includes('sn-')
        );
        console.log(`Filtered to ${snigelProducts.length} Snigel products by name`);
        return snigelProducts;
    }

    console.log(`Total Snigel products in Shopware: ${products.length}`);
    return products;
}

// Step 4: Load local scraped data
function loadLocalData() {
    console.log('\n' + '='.repeat(60));
    console.log('STEP 3: LOADING LOCAL SCRAPED DATA');
    console.log('='.repeat(60));

    const mergedPath = path.join(__dirname, 'snigel-merged-products.json');
    if (fs.existsSync(mergedPath)) {
        const data = JSON.parse(fs.readFileSync(mergedPath, 'utf8'));
        console.log(`Loaded ${data.length} products from snigel-merged-products.json`);
        return data;
    }

    console.log('No local data found');
    return [];
}

// Step 5: Compare and generate report
function compareAndReport(snigelLive, shopwareProducts, localData) {
    console.log('\n' + '='.repeat(60));
    console.log('STEP 4: COMPARING PRICES');
    console.log('='.repeat(60));

    const results = {
        matched: [],
        priceMismatch: [],
        missingInShopware: [],
        missingInSnigel: [],
        localVsLiveMismatch: []
    };

    // Create lookup maps
    const localBySlug = {};
    localData.forEach(p => {
        if (p.slug) localBySlug[p.slug] = p;
    });

    const shopwareByName = {};
    shopwareProducts.forEach(p => {
        const normalizedName = p.name.toLowerCase().trim();
        shopwareByName[normalizedName] = p;
    });

    // Normalize name for matching
    function normalizeName(name) {
        return name.toLowerCase()
            .replace(/[®™©-]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    // Find Shopware match
    function findShopwareMatch(snigelProduct) {
        const snigelName = normalizeName(snigelProduct.name);

        // Exact match
        for (const [key, product] of Object.entries(shopwareByName)) {
            if (normalizeName(key) === snigelName) {
                return product;
            }
        }

        // Partial match
        for (const [key, product] of Object.entries(shopwareByName)) {
            const swName = normalizeName(key);
            if (snigelName.includes(swName) || swName.includes(snigelName)) {
                return product;
            }
        }

        // Word-based match
        const snigelWords = snigelName.split(' ').filter(w => w.length > 2);
        for (const [key, product] of Object.entries(shopwareByName)) {
            const swWords = normalizeName(key).split(' ').filter(w => w.length > 2);
            const matching = snigelWords.filter(w => swWords.includes(w));
            if (matching.length >= Math.max(2, snigelWords.length * 0.6)) {
                return product;
            }
        }

        return null;
    }

    // Compare each Snigel product
    console.log('\nComparing products...\n');

    for (const snigel of snigelLive) {
        const local = localBySlug[snigel.slug];
        const shopware = findShopwareMatch(snigel);

        // Calculate expected CHF price from B2B EUR
        const expectedChf = snigel.b2b_price_eur
            ? Math.round(snigel.b2b_price_eur * CONFIG.retailMarkup / CONFIG.eurToChf * 100) / 100
            : null;

        // Check local vs live mismatch
        if (local && snigel.b2b_price_eur && local.b2b_price_eur) {
            const diff = Math.abs(snigel.b2b_price_eur - local.b2b_price_eur);
            const percentDiff = (diff / local.b2b_price_eur) * 100;

            if (percentDiff > CONFIG.priceTolerance) {
                results.localVsLiveMismatch.push({
                    name: snigel.name,
                    slug: snigel.slug,
                    liveB2bEur: snigel.b2b_price_eur,
                    localB2bEur: local.b2b_price_eur,
                    diff: snigel.b2b_price_eur - local.b2b_price_eur,
                    percentDiff: percentDiff.toFixed(1)
                });
            }
        }

        if (!shopware) {
            results.missingInShopware.push({
                name: snigel.name,
                slug: snigel.slug,
                b2bEur: snigel.b2b_price_eur,
                expectedChf
            });
            continue;
        }

        // Compare prices
        if (expectedChf && shopware.priceChf) {
            const priceDiff = Math.abs(shopware.priceChf - expectedChf);
            const percentDiff = (priceDiff / expectedChf) * 100;

            if (percentDiff > CONFIG.priceTolerance) {
                results.priceMismatch.push({
                    name: snigel.name,
                    slug: snigel.slug,
                    shopwareName: shopware.name,
                    b2bEur: snigel.b2b_price_eur,
                    expectedChf,
                    actualChf: shopware.priceChf,
                    diff: shopware.priceChf - expectedChf,
                    percentDiff: percentDiff.toFixed(1)
                });
            } else {
                results.matched.push({
                    name: snigel.name,
                    shopwareName: shopware.name,
                    b2bEur: snigel.b2b_price_eur,
                    expectedChf,
                    actualChf: shopware.priceChf
                });
            }
        }
    }

    // Check for products in Shopware but not in Snigel
    const snigelNames = new Set(snigelLive.map(p => normalizeName(p.name)));
    for (const sw of shopwareProducts) {
        const swName = normalizeName(sw.name);
        let found = false;
        for (const sn of snigelNames) {
            if (sn.includes(swName) || swName.includes(sn)) {
                found = true;
                break;
            }
        }
        if (!found) {
            results.missingInSnigel.push({
                name: sw.name,
                productNumber: sw.productNumber,
                priceChf: sw.priceChf
            });
        }
    }

    return results;
}

// Generate report
function generateReport(results) {
    console.log('\n' + '='.repeat(60));
    console.log('COMPARISON REPORT');
    console.log('='.repeat(60));

    console.log(`\nSUMMARY:`);
    console.log(`  Matched (prices OK):        ${results.matched.length}`);
    console.log(`  Price Mismatches:           ${results.priceMismatch.length}`);
    console.log(`  Missing in Shopware:        ${results.missingInShopware.length}`);
    console.log(`  Missing in Snigel (extra):  ${results.missingInSnigel.length}`);
    console.log(`  Local vs Live Mismatch:     ${results.localVsLiveMismatch.length}`);

    // Price Mismatches
    if (results.priceMismatch.length > 0) {
        console.log('\n' + '='.repeat(60));
        console.log('PRICE MISMATCHES (Shopware vs Expected)');
        console.log('='.repeat(60));
        console.log(`\nUsing: B2B EUR x ${CONFIG.retailMarkup} markup / ${CONFIG.eurToChf} EUR-CHF = Expected CHF`);
        console.log(`Tolerance: ${CONFIG.priceTolerance}%\n`);

        results.priceMismatch.sort((a, b) => Math.abs(b.diff) - Math.abs(a.diff));

        for (const item of results.priceMismatch) {
            const diffStr = item.diff > 0 ? `+${item.diff.toFixed(2)}` : item.diff.toFixed(2);
            console.log(`${item.name}`);
            console.log(`  B2B EUR: ${item.b2bEur?.toFixed(2) || 'N/A'} -> Expected CHF: ${item.expectedChf?.toFixed(2) || 'N/A'}`);
            console.log(`  Actual CHF: ${item.actualChf.toFixed(2)} | Diff: ${diffStr} CHF (${item.percentDiff}%)`);
            console.log('');
        }
    }

    // Local vs Live Mismatches
    if (results.localVsLiveMismatch.length > 0) {
        console.log('\n' + '='.repeat(60));
        console.log('LOCAL DATA vs LIVE SNIGEL PORTAL MISMATCHES');
        console.log('='.repeat(60));
        console.log('(Your scraped data differs from current Snigel portal prices)\n');

        results.localVsLiveMismatch.sort((a, b) => Math.abs(b.diff) - Math.abs(a.diff));

        for (const item of results.localVsLiveMismatch) {
            const diffStr = item.diff > 0 ? `+${item.diff.toFixed(2)}` : item.diff.toFixed(2);
            console.log(`${item.name}`);
            console.log(`  Local B2B EUR: ${item.localB2bEur.toFixed(2)}`);
            console.log(`  Live B2B EUR:  ${item.liveB2bEur.toFixed(2)} | Diff: ${diffStr} EUR (${item.percentDiff}%)`);
            console.log('');
        }
    }

    // Missing in Shopware
    if (results.missingInShopware.length > 0) {
        console.log('\n' + '='.repeat(60));
        console.log('MISSING IN SHOPWARE (exist in Snigel, not in store)');
        console.log('='.repeat(60) + '\n');

        for (const item of results.missingInShopware.slice(0, 30)) {
            console.log(`- ${item.name} (B2B: ${item.b2bEur?.toFixed(2) || 'N/A'} EUR)`);
        }
        if (results.missingInShopware.length > 30) {
            console.log(`... and ${results.missingInShopware.length - 30} more`);
        }
    }

    // Save full report to file
    const reportPath = path.join(OUTPUT_DIR, `comparison-report-${Date.now()}.json`);
    fs.writeFileSync(reportPath, JSON.stringify(results, null, 2));
    console.log(`\nFull report saved to: ${reportPath}`);

    // Save CSV for easy import
    const csvPath = path.join(OUTPUT_DIR, `price-mismatches-${Date.now()}.csv`);
    const csvLines = ['Name,Slug,B2B EUR,Expected CHF,Actual CHF,Difference,Percent'];
    for (const item of results.priceMismatch) {
        csvLines.push(`"${item.name}","${item.slug}",${item.b2bEur || ''},${item.expectedChf || ''},${item.actualChf},${item.diff.toFixed(2)},${item.percentDiff}%`);
    }
    fs.writeFileSync(csvPath, csvLines.join('\n'));
    console.log(`CSV report saved to: ${csvPath}`);
}

// Main
async function main() {
    console.log('\n' + '='.repeat(60));
    console.log('       SNIGEL PRICE COMPARISON TOOL');
    console.log('='.repeat(60));
    console.log(`\nSettings:`);
    console.log(`  EUR to CHF rate: ${CONFIG.eurToChf}`);
    console.log(`  Retail markup: ${CONFIG.retailMarkup}x`);
    console.log(`  Price tolerance: ${CONFIG.priceTolerance}%`);

    try {
        // Step 1: Scrape live Snigel prices
        const snigelLive = await scrapeSnigel();
        if (snigelLive.length === 0) {
            console.log('Failed to scrape Snigel. Exiting.');
            return;
        }

        // Step 2: Get Shopware products
        console.log('\nConnecting to Shopware API...');
        const token = await getShopwareToken();
        const shopwareProducts = await getShopwareProducts(token);

        // Step 3: Load local data
        const localData = loadLocalData();

        // Step 4: Compare
        const results = compareAndReport(snigelLive, shopwareProducts, localData);

        // Step 5: Generate report
        generateReport(results);

        console.log('\n' + '='.repeat(60));
        console.log('COMPARISON COMPLETE');
        console.log('='.repeat(60) + '\n');

    } catch (error) {
        console.error('\nError:', error.message);
        console.error(error.stack);
    }
}

main();
