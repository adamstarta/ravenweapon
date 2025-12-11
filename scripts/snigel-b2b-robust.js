/**
 * Snigel B2B Portal Scraper - Robust version
 * Waits properly for products to load and handles the WCPT product table
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

if (!fs.existsSync(config.outputDir)) {
    fs.mkdirSync(config.outputDir, { recursive: true });
}

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function main() {
    console.log('\n========================================================');
    console.log('       SNIGEL B2B PORTAL SCRAPER (Robust)');
    console.log('========================================================\n');

    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        viewport: { width: 1920, height: 1080 }
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

        // Accept cookies if present
        try {
            const cookieBtn = await page.$('button:has-text("Ok"), .cookie-consent-accept');
            if (cookieBtn) await cookieBtn.click();
        } catch (e) {}

        await page.click('button[name="login"]');
        await page.waitForLoadState('networkidle');
        await delay(3000);
        console.log('  Login successful!\n');

        // Step 2: Navigate to product category with EUR
        console.log('Step 2: Loading products...');
        await page.goto(`${config.baseUrl}/product-category/all/?currency=EUR`);
        await delay(5000); // Wait for initial load

        // Accept cookies again if needed
        try {
            const cookieBtn = await page.$('button:has-text("Ok")');
            if (cookieBtn) await cookieBtn.click();
            await delay(1000);
        } catch (e) {}

        // Wait for content to appear
        console.log('  Waiting for page content...');
        await page.waitForLoadState('domcontentloaded');
        await delay(3000);

        // Take a screenshot before scrolling
        await page.screenshot({ path: path.join(config.outputDir, 'before-scroll.png') });

        // Try to find any product containers
        console.log('\n  Checking page structure...');

        // List all major elements on page
        const bodyHTML = await page.evaluate(() => {
            const main = document.querySelector('main, #main, .main-content, .content');
            if (main) {
                return main.innerHTML.substring(0, 5000);
            }
            return document.body.innerHTML.substring(0, 5000);
        });

        console.log('  Page snippet:', bodyHTML.substring(0, 500) + '...');

        // Check for WooCommerce Product Table plugin (WCPT)
        const wcptTable = await page.$('.wcpt');
        if (wcptTable) {
            console.log('  Found WCPT product table!');
        }

        // Check for standard WooCommerce products
        const wcProducts = await page.$$('ul.products li, .products .product');
        console.log(`  Found ${wcProducts.length} WooCommerce product elements`);

        // Scroll down slowly to load content
        console.log('\n  Scrolling to load products...');
        for (let i = 0; i < 10; i++) {
            await page.evaluate(() => {
                window.scrollBy(0, 500);
            });
            await delay(1500);

            // Check if products appeared
            const count = await page.$$eval('a[href*="/product/"]', links => links.length);
            process.stdout.write(`    Scroll ${i + 1}/10 - Product links found: ${count}   \r`);

            if (count > 50) {
                console.log(`\n  Found ${count} product links!`);
                break;
            }
        }

        // Take screenshot after scrolling
        await page.screenshot({ path: path.join(config.outputDir, 'after-scroll.png'), fullPage: true });

        // Step 3: Extract products by finding all product links
        console.log('\n\nStep 3: Extracting products...');

        const products = await page.evaluate(() => {
            const results = [];
            const seenUrls = new Set();

            // Find all links to individual products
            const links = document.querySelectorAll('a[href*="/product/"]');

            links.forEach(link => {
                const url = link.href;
                if (seenUrls.has(url)) return;
                seenUrls.add(url);

                const product = { url };
                product.slug = url.split('/product/')[1]?.replace(/\/$/, '') || '';

                // Try to get name from link text or nearby elements
                let name = link.textContent.trim();
                if (!name || name.length < 3) {
                    // Look for adjacent title element
                    const parent = link.closest('li, article, div.product, tr');
                    if (parent) {
                        const titleEl = parent.querySelector('h1, h2, h3, h4, .title, [class*="title"], [class*="name"]');
                        if (titleEl) name = titleEl.textContent.trim();
                    }
                }

                // Look for image
                const img = link.querySelector('img');
                if (img) {
                    product.image = img.src;
                    if (!name && img.alt) name = img.alt;
                }

                // Look for price near the link
                const parent = link.closest('li, article, div, tr');
                if (parent) {
                    const priceEl = parent.querySelector('.price, .woocommerce-Price-amount, bdi, [class*="price"]');
                    if (priceEl) {
                        const priceText = priceEl.textContent;
                        // Try EUR format
                        let match = priceText.match(/€\s*([\d.,]+)/);
                        if (!match) {
                            // Try format with numbers
                            match = priceText.match(/([\d.,]+)\s*€/);
                        }
                        if (!match) {
                            // Just get any number
                            match = priceText.match(/([\d.,]+)/);
                        }
                        if (match) {
                            let price = match[1];
                            // Handle European format (1.234,56)
                            if (price.includes(',')) {
                                price = price.replace(/\./g, '').replace(',', '.');
                            }
                            product.b2b_price_eur = parseFloat(price);
                        }
                    }
                }

                if (name && name.length > 2) {
                    product.name = name;
                    results.push(product);
                }
            });

            return results;
        });

        console.log(`  Extracted ${products.length} products\n`);

        if (products.length > 0) {
            console.log('  Sample products:');
            products.slice(0, 10).forEach(p => {
                console.log(`    - ${p.name}: €${p.b2b_price_eur || 'N/A'}`);
            });
        }

        // Step 4: Merge with existing image data
        console.log('\nStep 4: Merging with existing data...');
        const originalDataPath = path.join(__dirname, 'snigel-data', 'products.json');
        let originalData = [];

        if (fs.existsSync(originalDataPath)) {
            originalData = JSON.parse(fs.readFileSync(originalDataPath, 'utf8'));
            console.log(`  Found ${originalData.length} products in original data`);
        }

        // Create lookup by slug
        const originalBySlug = {};
        originalData.forEach(p => {
            if (p.slug) originalBySlug[p.slug] = p;
        });

        // Merge data
        const mergedProducts = products.map(product => {
            const original = originalBySlug[product.slug];
            if (original) {
                return {
                    ...product,
                    images: original.images || [],
                    local_images: original.local_images || [],
                    colours: original.colours || [],
                    short_description: original.short_description || ''
                };
            }
            return product;
        });

        // Save results
        const outputFile = path.join(config.outputDir, 'products-b2b-list.json');
        fs.writeFileSync(outputFile, JSON.stringify(mergedProducts, null, 2));

        console.log('\n========================================================');
        console.log('                    SCRAPING COMPLETE');
        console.log('========================================================');
        console.log(`  Products scraped: ${mergedProducts.length}`);
        console.log(`  With prices: ${mergedProducts.filter(p => p.b2b_price_eur).length}`);
        console.log(`  Output file: ${outputFile}`);
        console.log('========================================================\n');

    } catch (error) {
        console.error('Error:', error.message);
        console.error(error.stack);
    } finally {
        await browser.close();
    }
}

main();
