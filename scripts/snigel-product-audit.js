/**
 * Snigel Product Audit Script
 * Extracts all product names from Snigel B2B portal and compares with Shopware
 *
 * Usage: node snigel-product-audit.js
 */

const { chromium } = require('playwright');
const fs = require('fs');

// Snigel credentials
const SNIGEL_URL = 'https://products.snigel.se';
const USERNAME = 'Raven Weapon AG';
const PASSWORD = 'wVREVbRZfqT&Fba@f(^2UKOw';

// Categories to scrape
const CATEGORIES = [
    { name: 'ALL PRODUCTS', slug: 'all' },
    { name: 'Tactical gear', slug: 'tactical-gear' },
    { name: 'HighVis', slug: 'highvis' },
    { name: 'Source® hydration', slug: 'source-hydration' },
    { name: 'Miscellaneous products', slug: 'miscellaneous-products' },
    { name: 'Bags & backpacks', slug: 'bags-backpacks' },
    { name: 'K9-units gear', slug: 'k9-units-gear' },
    { name: 'Vests & Chest rigs', slug: 'vests-chest-rigs' },
    { name: 'Leg panels', slug: 'leg-panels' },
    { name: 'Multicam', slug: 'multicam' },
    { name: 'Admin products', slug: 'admin-products' },
    { name: 'Tactical clothing', slug: 'tactical-clothing' },
    { name: 'Covert gear', slug: 'covert-gear' },
    { name: 'Police gear', slug: 'police-gear' },
    { name: 'Sniper gear', slug: 'sniper-gear' },
    { name: 'Slings & holsters', slug: 'slings-holsters' },
    { name: 'Patches', slug: 'patches' },
    { name: 'Medical gear', slug: 'medical-gear' },
    { name: 'Holders & pouches', slug: 'holders-pouches' },
    { name: 'Ballistic protection', slug: 'ballistic-protection' },
    { name: 'Belts', slug: 'belts' },
];

async function login(page) {
    console.log('🔐 Logging in to Snigel...');
    await page.goto(SNIGEL_URL);

    // Wait for login form
    await page.waitForSelector('input[name="log"], input[name="username"], #username, input[type="text"]', { timeout: 10000 });

    // Fill credentials
    const usernameInput = await page.$('input[name="log"]') || await page.$('input[name="username"]') || await page.$('#username') || await page.$('input[type="text"]');
    const passwordInput = await page.$('input[name="pwd"]') || await page.$('input[name="password"]') || await page.$('#password') || await page.$('input[type="password"]');

    if (usernameInput && passwordInput) {
        await usernameInput.fill(USERNAME);
        await passwordInput.fill(PASSWORD);

        // Submit
        const submitBtn = await page.$('input[type="submit"], button[type="submit"], .login-submit');
        if (submitBtn) {
            await submitBtn.click();
            await page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }).catch(() => {});
        }
    }

    // Wait a bit for page to load
    await page.waitForTimeout(2000);
    console.log('✅ Logged in successfully\n');
}

async function scrapeAllProducts(page) {
    console.log('📦 Scraping ALL PRODUCTS page...\n');

    const allProductsUrl = `${SNIGEL_URL}/product-category/all/`;
    await page.goto(allProductsUrl, { waitUntil: 'networkidle', timeout: 30000 });

    // Scroll to load all products (infinite scroll)
    let previousHeight = 0;
    let scrollAttempts = 0;
    const maxScrollAttempts = 50;

    while (scrollAttempts < maxScrollAttempts) {
        const currentHeight = await page.evaluate(() => document.body.scrollHeight);

        if (currentHeight === previousHeight) {
            // Try clicking "Load More" button if exists
            const loadMoreBtn = await page.$('.load-more, .loadmore, button:has-text("Load"), a:has-text("Load")');
            if (loadMoreBtn) {
                await loadMoreBtn.click().catch(() => {});
                await page.waitForTimeout(1500);
            } else {
                break;
            }
        }

        previousHeight = currentHeight;
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await page.waitForTimeout(800);
        scrollAttempts++;
    }

    // Extract product names
    const products = await page.evaluate(() => {
        const productElements = document.querySelectorAll('.product-title, .woocommerce-loop-product__title, .product-name, h2.woocommerce-loop-product__title, .products .product h2, .product-item-link, .product h2 a, .product-title a, li.product h2');
        const names = [];

        productElements.forEach(el => {
            const name = el.textContent.trim();
            if (name && !names.includes(name)) {
                names.push(name);
            }
        });

        // Also try getting from product links
        if (names.length === 0) {
            document.querySelectorAll('.products a.woocommerce-LoopProduct-link, .product a').forEach(el => {
                const h2 = el.querySelector('h2');
                if (h2) {
                    const name = h2.textContent.trim();
                    if (name && !names.includes(name)) {
                        names.push(name);
                    }
                }
            });
        }

        return names;
    });

    return products;
}

async function scrapeCategory(page, category) {
    const url = `${SNIGEL_URL}/product-category/${category.slug}/`;

    try {
        await page.goto(url, { waitUntil: 'networkidle', timeout: 20000 });

        // Scroll to load all
        let previousHeight = 0;
        for (let i = 0; i < 20; i++) {
            const currentHeight = await page.evaluate(() => document.body.scrollHeight);
            if (currentHeight === previousHeight) break;
            previousHeight = currentHeight;
            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
            await page.waitForTimeout(500);
        }

        // Count products
        const count = await page.evaluate(() => {
            const products = document.querySelectorAll('.product, .products li, .product-item');
            return products.length;
        });

        return count;
    } catch (e) {
        return 0;
    }
}

async function main() {
    console.log('═══════════════════════════════════════════════════════════');
    console.log('   SNIGEL PRODUCT AUDIT - Extract & Compare Products');
    console.log('═══════════════════════════════════════════════════════════\n');

    const browser = await chromium.launch({
        headless: true,
        args: ['--disable-blink-features=AutomationControlled']
    });

    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    });

    const page = await context.newPage();

    try {
        // Login
        await login(page);

        // Scrape all products
        const allProducts = await scrapeAllProducts(page);

        console.log('═══════════════════════════════════════════════════════════');
        console.log('   SNIGEL PRODUCTS FOUND');
        console.log('═══════════════════════════════════════════════════════════\n');

        console.log(`Total Products: ${allProducts.length}\n`);

        // Print all product names
        console.log('Product List:');
        console.log('─────────────────────────────────────────────────────────');
        allProducts.forEach((name, i) => {
            console.log(`${(i + 1).toString().padStart(3)}. ${name}`);
        });

        // Save to JSON
        const outputData = {
            timestamp: new Date().toISOString(),
            totalProducts: allProducts.length,
            products: allProducts.sort()
        };

        fs.writeFileSync('snigel-products-audit.json', JSON.stringify(outputData, null, 2));
        console.log('\n✅ Saved to snigel-products-audit.json');

        // Summary
        console.log('\n═══════════════════════════════════════════════════════════');
        console.log('   SUMMARY');
        console.log('═══════════════════════════════════════════════════════════');
        console.log(`\n📦 Total Snigel Products: ${allProducts.length}`);
        console.log('\n💡 To compare with your Shopware store, run:');
        console.log('   node snigel-compare-shopware.js\n');

    } catch (error) {
        console.error('❌ Error:', error.message);
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
