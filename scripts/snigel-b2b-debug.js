/**
 * Snigel B2B Portal Scraper - Debug version
 * Inspects the page structure to find the right selectors
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const config = {
    baseUrl: 'https://products.snigel.se',
    username: 'Raven Weapon AG',
    password: 'wVREVbRZfqT&Fba@f(^2UKOw',
    outputDir: path.join(__dirname, 'snigel-b2b-data'),
    currency: 'EUR',
};

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function main() {
    console.log('\n========================================================');
    console.log('       SNIGEL B2B PORTAL SCRAPER - DEBUG');
    console.log('========================================================\n');

    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    });
    const page = await context.newPage();
    page.setDefaultTimeout(60000);

    try {
        // Step 1: Login
        console.log('Step 1: Logging in...');
        await page.goto(`${config.baseUrl}/my-account/`);
        await page.waitForLoadState('networkidle');
        await page.fill('#username', config.username);
        await page.fill('#password', config.password);
        await page.click('button[name="login"]');
        await page.waitForLoadState('networkidle');
        await delay(2000);
        console.log('  Login successful!\n');

        // Step 2: Go to ALL PRODUCTS with EUR currency
        console.log('Step 2: Loading products...');
        await page.goto(`${config.baseUrl}/product-category/all/?currency=EUR`);
        await page.waitForLoadState('networkidle');
        await delay(3000);

        // Debug: Check page content
        console.log('\nStep 3: Analyzing page structure...\n');

        // Check various selectors
        const selectors = [
            '.product',
            '.products .product',
            'li.product',
            '.woocommerce-loop-product',
            '[class*="product"]',
            'article',
            '.product-item',
            '.product-card',
            'ul.products li',
            '.products-grid .item'
        ];

        for (const sel of selectors) {
            const count = await page.$$eval(sel, items => items.length).catch(() => 0);
            console.log(`  Selector "${sel}": ${count} elements`);
        }

        // Get page HTML structure
        console.log('\nChecking for any products...');
        const html = await page.content();

        // Look for product-related content
        const productMatches = html.match(/class="[^"]*product[^"]*"/g) || [];
        console.log(`\nFound ${productMatches.length} class names containing "product":`);
        const unique = [...new Set(productMatches)].slice(0, 10);
        unique.forEach(m => console.log('  ' + m));

        // Check for prices
        const priceMatches = html.match(/€\s*[\d,.]+/g) || [];
        console.log(`\nFound ${priceMatches.length} price elements (€):`);
        priceMatches.slice(0, 10).forEach(m => console.log('  ' + m));

        // Scroll and count products again
        console.log('\nScrolling to load more products...');
        for (let i = 0; i < 3; i++) {
            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
            await delay(2000);
        }

        // Re-check after scrolling
        console.log('\nAfter scrolling:');
        for (const sel of selectors.slice(0, 5)) {
            const count = await page.$$eval(sel, items => items.length).catch(() => 0);
            console.log(`  Selector "${sel}": ${count} elements`);
        }

        // Try to extract products with a broad selector
        console.log('\nTrying to extract product data with broad selector...');
        const products = await page.evaluate(() => {
            const results = [];

            // Try different possible product containers
            const containers = document.querySelectorAll('li.product, .product, [class*="type-product"]');

            containers.forEach(item => {
                const product = {};

                // Get any link
                const link = item.querySelector('a[href*="/product/"]');
                if (link) {
                    product.url = link.href;
                    product.slug = link.href.split('/product/')[1]?.replace(/\/$/, '') || '';
                }

                // Get any text that could be a name
                const headings = item.querySelectorAll('h1, h2, h3, h4, .title, [class*="title"], [class*="name"]');
                for (const h of headings) {
                    if (h.textContent.trim().length > 2) {
                        product.name = h.textContent.trim();
                        break;
                    }
                }

                // Get any price
                const priceEls = item.querySelectorAll('[class*="price"], bdi, .amount');
                for (const p of priceEls) {
                    const text = p.textContent;
                    const match = text.match(/€\s*([\d,.]+)/);
                    if (match) {
                        let price = match[1];
                        if (price.includes(',')) {
                            price = price.replace(/\./g, '').replace(',', '.');
                        }
                        product.b2b_price_eur = parseFloat(price);
                        break;
                    }
                }

                if (product.url && product.name) {
                    results.push(product);
                }
            });

            return results;
        });

        console.log(`\nExtracted ${products.length} products`);
        if (products.length > 0) {
            console.log('\nSample products:');
            products.slice(0, 5).forEach(p => {
                console.log(`  - ${p.name}: €${p.b2b_price_eur || 'N/A'}`);
            });
        }

        // Save screenshot for debugging
        const screenshotPath = path.join(config.outputDir, 'debug-screenshot.png');
        await page.screenshot({ path: screenshotPath, fullPage: false });
        console.log(`\nScreenshot saved to: ${screenshotPath}`);

    } catch (error) {
        console.error('Error:', error.message);
    } finally {
        await browser.close();
    }
}

main();
