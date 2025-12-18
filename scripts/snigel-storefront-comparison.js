/**
 * Snigel Price Comparison - Storefront Scraper
 * Compares:
 * 1. Snigel B2B Portal prices (EUR)
 * 2. ortak.ch storefront prices (CHF)
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const CONFIG = {
    snigel: {
        baseUrl: 'https://products.snigel.se',
        username: 'Raven Weapon AG',
        password: 'wVREVbRZfqT&Fba@f(^2UKOw'
    },
    shopware: {
        baseUrl: 'https://ortak.ch',
        categoryUrl: 'https://ortak.ch/Ausruestung/'
    },
    // EUR to CHF conversion rate
    eurToChf: 0.93,
    // Markup from B2B to retail
    retailMarkup: 1.5,
    // Price tolerance %
    priceTolerance: 10
};

const OUTPUT_DIR = path.join(__dirname, 'snigel-comparison');
if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// Normalize name for comparison
function normalizeName(name) {
    return name.toLowerCase()
        .replace(/[®™©×x]/g, '')
        .replace(/\s+/g, ' ')
        .replace(/[^a-z0-9\s.-]/g, '')
        .replace(/-+/g, '-')
        .trim();
}

// Scrape Snigel B2B Portal
async function scrapeSnigel(browser) {
    console.log('\n' + '='.repeat(60));
    console.log('STEP 1: SCRAPING SNIGEL B2B PORTAL');
    console.log('='.repeat(60));

    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        viewport: { width: 1920, height: 1080 }
    });
    const page = await context.newPage();
    page.setDefaultTimeout(60000);

    try {
        console.log('\nLogging in...');
        await page.goto(`${CONFIG.snigel.baseUrl}/my-account/`);
        await page.waitForLoadState('networkidle');
        await delay(2000);

        try { await page.click('button:has-text("Ok")', { timeout: 3000 }); } catch (e) {}

        await page.fill('#username', CONFIG.snigel.username);
        await page.fill('#password', CONFIG.snigel.password);
        await page.click('button[name="login"]');
        await page.waitForLoadState('networkidle');
        await delay(3000);
        console.log('Login successful!');

        console.log('\nLoading products (EUR)...');
        await page.goto(`${CONFIG.snigel.baseUrl}/product-category/all/?currency=EUR`);
        await page.waitForLoadState('networkidle');
        await delay(5000);

        try { await page.click('button:has-text("Ok")', { timeout: 2000 }); } catch (e) {}

        // Scroll to load all
        console.log('Scrolling to load all products...');
        let lastCount = 0, stableCount = 0;

        while (stableCount < 8) {
            await page.evaluate(() => window.scrollBy(0, 2000));
            await delay(2000);

            const count = await page.$$eval('a[href*="/product/"]', links => {
                const seen = new Set();
                links.forEach(l => {
                    const slug = l.href.split('/product/')[1]?.replace(/\/$/, '');
                    if (slug) seen.add(slug);
                });
                return seen.size;
            });

            if (count === lastCount) stableCount++;
            else {
                stableCount = 0;
                lastCount = count;
                process.stdout.write(`  Found ${count} products...   \r`);
            }
        }
        console.log(`\nTotal: ${lastCount} products`);

        // Extract
        console.log('\nExtracting...');
        await page.evaluate(() => window.scrollTo(0, 0));
        await delay(2000);

        const products = await page.evaluate(() => {
            const results = [];
            const seen = new Set();
            const productLinks = document.querySelectorAll('a[href*="/product/"]');

            productLinks.forEach(link => {
                const url = link.href;
                const slug = url.split('/product/')[1]?.replace(/\/$/, '');
                if (!slug || seen.has(slug)) return;
                seen.add(slug);

                const container = link.closest('.tmb') || link.closest('.product') || link.parentElement?.parentElement;
                const product = { url, slug };

                const title = container?.querySelector('.t-entry-title a') || container?.querySelector('h3 a');
                if (title) product.name = title.textContent.trim();
                else {
                    const img = container?.querySelector('img');
                    if (img?.alt) product.name = img.alt.trim();
                }

                const priceEl = container?.querySelector('.woocommerce-Price-amount bdi') ||
                               container?.querySelector('.woocommerce-Price-amount');
                if (priceEl) {
                    const text = priceEl.textContent.trim();
                    const match = text.match(/([\d.,]+)\s*€/) || text.match(/€\s*([\d.,]+)/);
                    if (match) {
                        let priceStr = match[1].replace('.', '').replace(',', '.');
                        product.b2b_price_eur = parseFloat(priceStr);
                    }
                }

                if (product.name) results.push(product);
            });

            return results;
        });

        console.log(`Extracted ${products.length} products from Snigel`);
        await context.close();
        return products;

    } catch (error) {
        console.error('Snigel error:', error.message);
        await context.close();
        return [];
    }
}

// Scrape ortak.ch storefront
async function scrapeStorefront(browser) {
    console.log('\n' + '='.repeat(60));
    console.log('STEP 2: SCRAPING ORTAK.CH STOREFRONT');
    console.log('='.repeat(60));

    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        viewport: { width: 1920, height: 1080 }
    });
    const page = await context.newPage();
    page.setDefaultTimeout(60000);

    const products = [];

    try {
        // Scrape all pages of Ausrüstung
        let pageNum = 1;
        const maxPages = 15;

        while (pageNum <= maxPages) {
            const url = pageNum === 1
                ? CONFIG.shopware.categoryUrl
                : `${CONFIG.shopware.categoryUrl}?p=${pageNum}`;

            console.log(`\nLoading page ${pageNum}...`);
            await page.goto(url, { waitUntil: 'networkidle' });
            await delay(2000);

            // Extract products from page
            const pageProducts = await page.evaluate(() => {
                const results = [];
                // Find all product cards
                const cards = document.querySelectorAll('[class*="product-box"], [class*="cms-listing-col"]');

                cards.forEach(card => {
                    const nameEl = card.querySelector('a[class*="product-name"], [class*="product-title"] a, .product-name');
                    const priceEl = card.querySelector('[class*="product-price"]');

                    if (!nameEl) return;

                    const name = nameEl.textContent.trim();
                    let price = 0;

                    if (priceEl) {
                        const priceText = priceEl.textContent.trim();
                        // Match "CHF 123.45" or "123.45" or "123,45"
                        const match = priceText.match(/CHF\s*([\d',\.]+)/) ||
                                     priceText.match(/([\d',\.]+)/);
                        if (match) {
                            let priceStr = match[1]
                                .replace(/'/g, '')  // Swiss thousand separator
                                .replace(',', '.');  // Decimal
                            price = parseFloat(priceStr);
                        }
                    }

                    if (name && price > 0) {
                        results.push({ name, priceChf: price });
                    }
                });

                return results;
            });

            console.log(`  Found ${pageProducts.length} products on page ${pageNum}`);
            products.push(...pageProducts);

            // Check if there's a next page
            const hasNext = await page.$('a[title="Next page"], .pagination-next:not(.disabled)');
            if (!hasNext || pageProducts.length === 0) break;

            pageNum++;
        }

        console.log(`\nTotal storefront products: ${products.length}`);
        await context.close();
        return products;

    } catch (error) {
        console.error('Storefront error:', error.message);
        await context.close();
        return products;
    }
}

// Compare prices
function compare(snigelProducts, storefrontProducts) {
    console.log('\n' + '='.repeat(60));
    console.log('STEP 3: COMPARING PRICES');
    console.log('='.repeat(60));

    const results = {
        matched: [],
        priceMismatch: [],
        missingInStore: [],
        extraInStore: []
    };

    // Create lookup by normalized name
    const storeByName = {};
    storefrontProducts.forEach(p => {
        const key = normalizeName(p.name);
        storeByName[key] = p;
    });

    // Compare each Snigel product
    for (const snigel of snigelProducts) {
        if (!snigel.b2b_price_eur) continue;

        const snigelNorm = normalizeName(snigel.name);

        // Find match - try exact, then partial
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

        // Calculate expected CHF price
        const expectedChf = Math.round(snigel.b2b_price_eur * CONFIG.retailMarkup / CONFIG.eurToChf * 100) / 100;

        if (!storeProduct) {
            results.missingInStore.push({
                name: snigel.name,
                b2bEur: snigel.b2b_price_eur,
                expectedChf
            });
            continue;
        }

        // Compare prices
        const diff = storeProduct.priceChf - expectedChf;
        const percentDiff = Math.abs(diff / expectedChf) * 100;

        if (percentDiff > CONFIG.priceTolerance) {
            results.priceMismatch.push({
                name: snigel.name,
                storeName: storeProduct.name,
                b2bEur: snigel.b2b_price_eur,
                expectedChf,
                actualChf: storeProduct.priceChf,
                diff,
                percentDiff: percentDiff.toFixed(1)
            });
        } else {
            results.matched.push({
                name: snigel.name,
                expectedChf,
                actualChf: storeProduct.priceChf
            });
        }
    }

    return results;
}

// Generate report
function generateReport(results) {
    console.log('\n' + '='.repeat(60));
    console.log('PRICE COMPARISON REPORT');
    console.log('='.repeat(60));

    console.log(`\nFormula: B2B EUR × ${CONFIG.retailMarkup} markup ÷ ${CONFIG.eurToChf} = Expected CHF`);
    console.log(`Tolerance: ${CONFIG.priceTolerance}%`);

    console.log(`\n--- SUMMARY ---`);
    console.log(`Prices OK:           ${results.matched.length}`);
    console.log(`PRICE MISMATCHES:    ${results.priceMismatch.length}`);
    console.log(`Missing in store:    ${results.missingInStore.length}`);

    if (results.priceMismatch.length > 0) {
        console.log('\n' + '='.repeat(60));
        console.log('PRICE MISMATCHES - NEED FIXING!');
        console.log('='.repeat(60));

        // Sort by absolute difference
        results.priceMismatch.sort((a, b) => Math.abs(b.diff) - Math.abs(a.diff));

        for (const item of results.priceMismatch) {
            const diffStr = item.diff > 0 ? `+${item.diff.toFixed(2)}` : item.diff.toFixed(2);
            const status = Math.abs(item.diff) > 100 ? '!!!' : item.diff > 0 ? 'HIGH' : 'LOW';

            console.log(`\n[${status}] ${item.name}`);
            console.log(`    B2B EUR: €${item.b2bEur.toFixed(2)}`);
            console.log(`    Expected CHF: ${item.expectedChf.toFixed(2)}`);
            console.log(`    Actual CHF:   ${item.actualChf.toFixed(2)}`);
            console.log(`    Difference:   ${diffStr} CHF (${item.percentDiff}%)`);
        }
    }

    // Save to files
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);

    const reportPath = path.join(OUTPUT_DIR, `comparison-${timestamp}.json`);
    fs.writeFileSync(reportPath, JSON.stringify(results, null, 2));
    console.log(`\nFull report: ${reportPath}`);

    // CSV for easy import
    const csvLines = ['Product,B2B EUR,Expected CHF,Actual CHF,Difference,Percent,Status'];
    for (const item of results.priceMismatch) {
        const status = Math.abs(item.diff) > 100 ? 'CRITICAL' : item.diff > 0 ? 'TOO_HIGH' : 'TOO_LOW';
        csvLines.push(`"${item.name}",${item.b2bEur},${item.expectedChf.toFixed(2)},${item.actualChf.toFixed(2)},${item.diff.toFixed(2)},${item.percentDiff}%,${status}`);
    }
    const csvPath = path.join(OUTPUT_DIR, `mismatches-${timestamp}.csv`);
    fs.writeFileSync(csvPath, csvLines.join('\n'));
    console.log(`CSV report: ${csvPath}`);
}

// Main
async function main() {
    console.log('\n' + '='.repeat(60));
    console.log('       SNIGEL PRICE COMPARISON TOOL');
    console.log('='.repeat(60));

    const browser = await chromium.launch({ headless: false });

    try {
        // Step 1: Scrape Snigel
        const snigelProducts = await scrapeSnigel(browser);
        if (snigelProducts.length === 0) {
            console.log('Failed to scrape Snigel');
            return;
        }

        // Step 2: Scrape storefront
        const storefrontProducts = await scrapeStorefront(browser);
        if (storefrontProducts.length === 0) {
            console.log('Failed to scrape storefront');
            return;
        }

        // Step 3: Compare
        const results = compare(snigelProducts, storefrontProducts);

        // Step 4: Report
        generateReport(results);

        console.log('\n' + '='.repeat(60));
        console.log('COMPARISON COMPLETE');
        console.log('='.repeat(60) + '\n');

    } catch (error) {
        console.error('Error:', error.message);
    } finally {
        await browser.close();
    }
}

main();
