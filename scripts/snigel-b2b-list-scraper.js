/**
 * Snigel B2B Portal Scraper - List-based approach
 * Extracts product data from the product listing page (infinite scroll)
 * Much faster than visiting each product page individually
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
    console.log('       SNIGEL B2B PORTAL LIST SCRAPER');
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
        console.log('Step 2: Loading products (infinite scroll)...');
        await page.goto(`${config.baseUrl}/product-category/all/?currency=EUR`);
        await page.waitForLoadState('networkidle');
        await delay(2000);

        // Keep scrolling until no new products
        let previousCount = 0;
        let noChangeCount = 0;

        while (noChangeCount < 5) {
            previousCount = await page.$$eval('.product', items => items.length);
            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
            await delay(2000);
            const currentCount = await page.$$eval('.product', items => items.length);

            if (currentCount === previousCount) {
                noChangeCount++;
            } else {
                noChangeCount = 0;
                process.stdout.write(`  Loaded ${currentCount} products...\r`);
            }
        }

        // Step 3: Extract all product data from the listing page
        console.log('\nStep 3: Extracting product data...');

        const products = await page.$$eval('.product', items => {
            return items.map(item => {
                const product = {};

                // Get product URL and slug
                const link = item.querySelector('a[href*="/product/"]');
                if (link) {
                    product.url = link.href;
                    product.slug = link.href.split('/product/')[1]?.replace(/\/$/, '') || '';
                }

                // Get product name
                const title = item.querySelector('.woocommerce-loop-product__title, h2, h3');
                if (title) {
                    product.name = title.textContent.trim();
                }

                // Get price - look for the B2B price (usually the main price shown)
                const priceEl = item.querySelector('.price bdi, .woocommerce-Price-amount bdi');
                if (priceEl) {
                    const priceText = priceEl.textContent.trim();
                    const match = priceText.match(/([0-9,.]+)/);
                    if (match) {
                        // Handle European number format (1.234,56)
                        let price = match[1];
                        if (price.includes(',')) {
                            price = price.replace(/\./g, '').replace(',', '.');
                        }
                        product.b2b_price_eur = parseFloat(price);
                    }
                }

                // Check if in stock
                const stockClass = item.className;
                product.in_stock = !stockClass.includes('outofstock');

                return product;
            }).filter(p => p.url && p.name);
        });

        console.log(`  Extracted ${products.length} products from listing\n`);

        // Step 4: Merge with existing data from original scraper (for images)
        console.log('Step 4: Merging with existing image data...');
        const originalDataPath = path.join(__dirname, 'snigel-data', 'products.json');
        let originalData = [];

        if (fs.existsSync(originalDataPath)) {
            originalData = JSON.parse(fs.readFileSync(originalDataPath, 'utf8'));
            console.log(`  Found ${originalData.length} products in original data\n`);
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
                // Get real product images (filter out favicon icons)
                const realImages = (original.images || []).filter(img =>
                    !img.includes('snigel_icon') &&
                    !img.includes('cropped-') &&
                    img.includes('wp-content/uploads')
                );

                return {
                    ...product,
                    images: realImages,
                    local_images: (original.local_images || []).filter(img =>
                        !img.includes('snigel_icon') &&
                        !img.includes('cropped-')
                    ),
                    colours: original.colours || [],
                    short_description: original.short_description || ''
                };
            }
            return product;
        });

        // Save merged data
        const outputFile = path.join(config.outputDir, 'products-b2b-merged.json');
        fs.writeFileSync(outputFile, JSON.stringify(mergedProducts, null, 2));

        console.log('========================================================');
        console.log('                    SCRAPING COMPLETE');
        console.log('========================================================');
        console.log(`  Products scraped: ${mergedProducts.length}`);
        console.log(`  Output file: ${outputFile}`);
        console.log('========================================================\n');

        // Statistics
        const withPrices = mergedProducts.filter(p => p.b2b_price_eur).length;
        const withImages = mergedProducts.filter(p => p.images && p.images.length > 0).length;
        const inStock = mergedProducts.filter(p => p.in_stock).length;

        console.log('Statistics:');
        console.log(`  - With B2B prices: ${withPrices}`);
        console.log(`  - With images: ${withImages}`);
        console.log(`  - In stock: ${inStock}`);
        console.log('');

    } catch (error) {
        console.error('Error:', error.message);
    } finally {
        await browser.close();
    }
}

main();
