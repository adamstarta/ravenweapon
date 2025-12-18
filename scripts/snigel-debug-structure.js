/**
 * Debug Snigel page structure
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const CONFIG = {
    baseUrl: 'https://products.snigel.se',
    username: 'Raven Weapon AG',
    password: 'wVREVbRZfqT&Fba@f(^2UKOw'
};

async function main() {
    console.log('Starting Snigel debug...\n');

    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        viewport: { width: 1920, height: 1080 }
    });
    const page = await context.newPage();
    page.setDefaultTimeout(60000);

    try {
        // Login
        console.log('Logging in...');
        await page.goto(`${CONFIG.baseUrl}/my-account/`);
        await page.waitForLoadState('networkidle');
        await new Promise(r => setTimeout(r, 2000));

        try {
            await page.click('button:has-text("Ok")', { timeout: 3000 });
        } catch (e) {}

        await page.fill('#username', CONFIG.username);
        await page.fill('#password', CONFIG.password);
        await page.click('button[name="login"]');
        await page.waitForLoadState('networkidle');
        await new Promise(r => setTimeout(r, 3000));
        console.log('Logged in!');

        // Go to products
        console.log('\nLoading products page...');
        await page.goto(`${CONFIG.baseUrl}/product-category/all/?currency=EUR`);
        await page.waitForLoadState('networkidle');
        await new Promise(r => setTimeout(r, 5000));

        try {
            await page.click('button:has-text("Ok")', { timeout: 2000 });
        } catch (e) {}

        // Scroll to load more
        console.log('Scrolling...');
        for (let i = 0; i < 10; i++) {
            await page.evaluate(() => window.scrollBy(0, 1000));
            await new Promise(r => setTimeout(r, 1500));
        }

        // Save HTML
        const html = await page.content();
        fs.writeFileSync(path.join(__dirname, 'snigel-page-debug.html'), html);
        console.log('\nSaved HTML to snigel-page-debug.html');

        // Test different selectors
        console.log('\n--- Testing selectors ---');

        const selectors = [
            '.tmb.tmb-woocommerce',
            '.tmb',
            '.product',
            '.product-item',
            'article.product',
            '[class*="product"]',
            '.woocommerce-loop-product',
            'li.product',
            '.products li',
            'ul.products > li'
        ];

        for (const sel of selectors) {
            const count = await page.$$eval(sel, items => items.length).catch(() => 0);
            console.log(`  "${sel}": ${count} items`);
        }

        // Get a sample product HTML
        console.log('\n--- Sample product structure ---');
        const sampleHtml = await page.evaluate(() => {
            const product = document.querySelector('.tmb') ||
                           document.querySelector('.product') ||
                           document.querySelector('[class*="product"]');
            return product ? product.outerHTML.substring(0, 2000) : 'No product found';
        });
        console.log(sampleHtml);

        // Try extracting with more flexible approach
        console.log('\n--- Trying flexible extraction ---');
        const products = await page.evaluate(() => {
            const results = [];

            // Find all links to products
            const productLinks = document.querySelectorAll('a[href*="/product/"]');
            const seen = new Set();

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

                // Try to get name from various places
                const img = container?.querySelector('img') || link.querySelector('img');
                if (img && img.alt) {
                    product.name = img.alt.trim();
                }

                // Try to get title
                const title = container?.querySelector('.t-entry-title') ||
                             container?.querySelector('h2') ||
                             container?.querySelector('[class*="title"]');
                if (title) {
                    product.name = title.textContent.trim();
                }

                // Try to get price
                const priceEl = container?.querySelector('.woocommerce-Price-amount bdi') ||
                               container?.querySelector('.woocommerce-Price-amount') ||
                               container?.querySelector('.price') ||
                               container?.querySelector('[class*="price"]');
                if (priceEl) {
                    const text = priceEl.textContent.trim();
                    const match = text.match(/([\d.,]+)\s*€?/);
                    if (match) {
                        let priceStr = match[1].replace('.', '').replace(',', '.');
                        product.price = parseFloat(priceStr);
                    }
                }

                if (product.name || product.price) {
                    results.push(product);
                }
            });

            return results;
        });

        console.log(`\nExtracted ${products.length} products with flexible approach`);
        if (products.length > 0) {
            console.log('\nSample products:');
            products.slice(0, 10).forEach(p => {
                console.log(`  - ${p.name || 'Unknown'}: ${p.price ? '€' + p.price.toFixed(2) : 'N/A'}`);
            });
        }

        // Keep browser open
        console.log('\n\nBrowser staying open for 30 seconds...');
        await new Promise(r => setTimeout(r, 30000));

    } catch (error) {
        console.error('Error:', error.message);
    } finally {
        await browser.close();
    }
}

main();
