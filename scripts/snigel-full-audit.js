/**
 * SNIGEL FULL AUDIT - All-in-One Script
 * 1. Scrapes all products from Snigel B2B portal
 * 2. Compares with Shopware store (ortak.ch)
 * 3. Shows what's missing
 *
 * Usage: node snigel-full-audit.js
 */

const { chromium } = require('playwright');
const fs = require('fs');
const https = require('https');

// Config
const SNIGEL_URL = 'https://products.snigel.se';
const USERNAME = 'Raven Weapon AG';
const PASSWORD = 'wVREVbRZfqT&Fba@f(^2UKOw';

// ═══════════════════════════════════════════════════════════
// SNIGEL SCRAPER
// ═══════════════════════════════════════════════════════════

async function scrapeSnigelProducts() {
    console.log('\n📦 STEP 1: Scraping Snigel B2B Portal...\n');

    const browser = await chromium.launch({
        headless: true,
        args: ['--disable-blink-features=AutomationControlled']
    });

    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    });

    const page = await context.newPage();
    let products = [];

    try {
        // Login
        console.log('   🔐 Logging in...');
        await page.goto(SNIGEL_URL, { timeout: 30000 });
        await page.waitForTimeout(2000);

        // Find and fill login form
        const usernameInput = await page.$('input[name="log"]') ||
                              await page.$('input[name="username"]') ||
                              await page.$('#user_login') ||
                              await page.$('input[type="text"]');

        const passwordInput = await page.$('input[name="pwd"]') ||
                              await page.$('input[name="password"]') ||
                              await page.$('#user_pass') ||
                              await page.$('input[type="password"]');

        if (usernameInput && passwordInput) {
            await usernameInput.fill(USERNAME);
            await passwordInput.fill(PASSWORD);

            const submitBtn = await page.$('input[type="submit"]') ||
                              await page.$('button[type="submit"]') ||
                              await page.$('#wp-submit');

            if (submitBtn) {
                await submitBtn.click();
                await page.waitForTimeout(3000);
            }
        }

        console.log('   ✅ Logged in\n');

        // Go to All Products
        console.log('   📄 Loading All Products page...');
        await page.goto(`${SNIGEL_URL}/product-category/all/`, {
            waitUntil: 'domcontentloaded',
            timeout: 30000
        });
        await page.waitForTimeout(3000);

        // Scroll to load all products
        console.log('   ⏳ Loading all products (scrolling)...');
        let lastHeight = 0;
        let scrollCount = 0;

        while (scrollCount < 30) {
            const currentHeight = await page.evaluate(() => document.body.scrollHeight);

            if (currentHeight === lastHeight) {
                // Try load more button
                const loadMore = await page.$('a.loadmore, button.load-more, .infinite-scroll-request');
                if (loadMore) {
                    await loadMore.click().catch(() => {});
                    await page.waitForTimeout(2000);
                } else {
                    break;
                }
            }

            lastHeight = currentHeight;
            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
            await page.waitForTimeout(1000);
            scrollCount++;
            process.stdout.write(`\r   ⏳ Scrolling... (${scrollCount})`);
        }

        console.log('\n');

        // Extract product names
        products = await page.evaluate(() => {
            const items = [];

            // Try multiple selectors
            const selectors = [
                '.products .product h2',
                '.woocommerce-loop-product__title',
                '.product-title',
                'h2.woocommerce-LoopProduct-link__title',
                '.product-name',
                'li.product .woocommerce-loop-product__link h2'
            ];

            for (const selector of selectors) {
                document.querySelectorAll(selector).forEach(el => {
                    const name = el.textContent.trim();
                    if (name && !items.includes(name)) {
                        items.push(name);
                    }
                });
            }

            return items;
        });

        console.log(`   ✅ Found ${products.length} products on Snigel\n`);

    } catch (error) {
        console.error('   ❌ Error:', error.message);
    } finally {
        await browser.close();
    }

    return products;
}

// ═══════════════════════════════════════════════════════════
// SHOPWARE FETCHER
// ═══════════════════════════════════════════════════════════

async function fetchShopwareProducts() {
    console.log('🛒 STEP 2: Fetching products from Shopware...\n');

    return new Promise((resolve) => {
        const postData = JSON.stringify({
            limit: 500,
            includes: {
                product: ['id', 'productNumber', 'name', 'translated']
            },
            filter: [{
                type: 'contains',
                field: 'productNumber',
                value: 'SN-'
            }]
        });

        const req = https.request({
            hostname: 'ortak.ch',
            port: 443,
            path: '/store-api/product',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'sw-access-key': 'SWSCVWNQN0FHSXLUM0NNZGNQBW',
                'Content-Length': Buffer.byteLength(postData)
            }
        }, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try {
                    const json = JSON.parse(data);
                    const products = (json.elements || []).map(p => ({
                        name: p.translated?.name || p.name,
                        sku: p.productNumber
                    }));
                    console.log(`   ✅ Found ${products.length} Snigel products in Shopware\n`);
                    resolve(products);
                } catch (e) {
                    console.log('   ⚠️  Could not fetch Shopware products');
                    resolve([]);
                }
            });
        });

        req.on('error', () => {
            console.log('   ⚠️  Could not connect to Shopware');
            resolve([]);
        });

        req.write(postData);
        req.end();
    });
}

// ═══════════════════════════════════════════════════════════
// COMPARISON
// ═══════════════════════════════════════════════════════════

function compareProducts(snigelProducts, shopwareProducts) {
    console.log('🔍 STEP 3: Comparing products...\n');

    const normalize = (name) => name.toLowerCase().trim()
        .replace(/[®™©]/g, '')
        .replace(/\s+/g, ' ')
        .replace(/[^a-z0-9\s]/g, '');

    const shopwareNames = new Set(shopwareProducts.map(p => normalize(p.name)));

    const found = [];
    const missing = [];

    snigelProducts.forEach(name => {
        if (shopwareNames.has(normalize(name))) {
            found.push(name);
        } else {
            missing.push(name);
        }
    });

    return { found, missing };
}

// ═══════════════════════════════════════════════════════════
// MAIN
// ═══════════════════════════════════════════════════════════

async function main() {
    console.log('═══════════════════════════════════════════════════════════');
    console.log('   SNIGEL FULL PRODUCT AUDIT');
    console.log('   Scrape → Compare → Report');
    console.log('═══════════════════════════════════════════════════════════');

    const startTime = Date.now();

    // Step 1: Scrape Snigel
    const snigelProducts = await scrapeSnigelProducts();

    // Step 2: Fetch Shopware
    const shopwareProducts = await fetchShopwareProducts();

    // Step 3: Compare
    const { found, missing } = compareProducts(snigelProducts, shopwareProducts);

    // ═══════════════════════════════════════════════════════════
    // RESULTS
    // ═══════════════════════════════════════════════════════════

    console.log('═══════════════════════════════════════════════════════════');
    console.log('   AUDIT RESULTS');
    console.log('═══════════════════════════════════════════════════════════\n');

    console.log('┌─────────────────────────────────────────────────────────┐');
    console.log(`│  📦 Snigel B2B Portal:     ${snigelProducts.length.toString().padStart(4)} products              │`);
    console.log(`│  🛒 Shopware (ortak.ch):   ${shopwareProducts.length.toString().padStart(4)} products              │`);
    console.log('├─────────────────────────────────────────────────────────┤');
    console.log(`│  ✅ Matched:               ${found.length.toString().padStart(4)} products              │`);
    console.log(`│  ❌ Missing:               ${missing.length.toString().padStart(4)} products              │`);
    console.log('└─────────────────────────────────────────────────────────┘\n');

    if (snigelProducts.length > 0) {
        const matchPercent = ((found.length / snigelProducts.length) * 100).toFixed(1);
        console.log(`   Coverage: ${matchPercent}%\n`);
    }

    // List all products
    console.log('═══════════════════════════════════════════════════════════');
    console.log('   ALL SNIGEL PRODUCTS');
    console.log('═══════════════════════════════════════════════════════════\n');

    snigelProducts.sort().forEach((name, i) => {
        const status = found.includes(name) ? '✅' : '❌';
        console.log(`${(i + 1).toString().padStart(3)}. ${status} ${name}`);
    });

    // Missing products detail
    if (missing.length > 0) {
        console.log('\n═══════════════════════════════════════════════════════════');
        console.log('   ❌ MISSING PRODUCTS (Not in Shopware)');
        console.log('═══════════════════════════════════════════════════════════\n');

        missing.forEach((name, i) => {
            console.log(`${(i + 1).toString().padStart(3)}. ${name}`);
        });
    }

    // Save results
    const results = {
        timestamp: new Date().toISOString(),
        snigel: {
            total: snigelProducts.length,
            products: snigelProducts.sort()
        },
        shopware: {
            total: shopwareProducts.length,
            products: shopwareProducts.map(p => p.name).sort()
        },
        comparison: {
            matched: found.length,
            missing: missing.length,
            missingProducts: missing.sort()
        }
    };

    fs.writeFileSync('snigel-audit-results.json', JSON.stringify(results, null, 2));

    const duration = ((Date.now() - startTime) / 1000).toFixed(1);
    console.log(`\n✅ Results saved to snigel-audit-results.json`);
    console.log(`⏱️  Completed in ${duration}s\n`);
}

main().catch(console.error);
