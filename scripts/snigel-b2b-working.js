/**
 * Snigel B2B Portal Scraper - Working version
 * Price format is "30,22 €" (European format, € after number)
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const config = {
    baseUrl: 'https://products.snigel.se',
    username: 'Raven Weapon AG',
    password: 'wVREVbRZfqT&Fba@f(^2UKOw',
    outputDir: path.join(__dirname, 'snigel-b2b-data'),
};

if (!fs.existsSync(config.outputDir)) {
    fs.mkdirSync(config.outputDir, { recursive: true });
}

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function main() {
    console.log('\n========================================================');
    console.log('       SNIGEL B2B PORTAL SCRAPER (Working)');
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
        await delay(2000);

        try {
            await page.click('button:has-text("Ok")', { timeout: 3000 });
        } catch (e) {}

        await page.fill('#username', config.username);
        await page.fill('#password', config.password);
        await page.click('button[name="login"]');
        await page.waitForLoadState('networkidle');
        await delay(3000);
        console.log('  Login successful!\n');

        // Step 2: Navigate to products
        console.log('Step 2: Loading products with EUR currency...');
        await page.goto(`${config.baseUrl}/product-category/all/?currency=EUR`);
        await page.waitForLoadState('networkidle');
        await delay(5000);

        try {
            await page.click('button:has-text("Ok")', { timeout: 2000 });
        } catch (e) {}

        // Scroll to load all products
        console.log('  Scrolling to load all products...\n');
        let lastCount = 0;
        let stableCount = 0;

        while (stableCount < 5) {
            await page.evaluate(() => window.scrollBy(0, 1000));
            await delay(1500);

            const count = await page.$$eval('.tmb.tmb-woocommerce', items => items.length);

            if (count === lastCount) {
                stableCount++;
            } else {
                stableCount = 0;
                lastCount = count;
                process.stdout.write(`  Found ${count} product items...   \r`);
            }
        }
        console.log(`\n  Total product items: ${lastCount}\n`);

        // Step 3: Extract product data
        console.log('Step 3: Extracting product data...');

        // Scroll back to top
        await page.evaluate(() => window.scrollTo(0, 0));
        await delay(2000);

        const products = await page.evaluate(() => {
            const results = [];
            const seen = new Set();

            // Each product is in a .tmb.tmb-woocommerce container
            const items = document.querySelectorAll('.tmb.tmb-woocommerce');

            items.forEach(item => {
                // Get product link
                const link = item.querySelector('a[href*="/product/"]');
                if (!link) return;

                const url = link.href;
                const slug = url.split('/product/')[1]?.replace(/\/$/, '');
                if (!slug || seen.has(slug)) return;
                seen.add(slug);

                const product = { url, slug };

                // Get name from image alt
                const img = item.querySelector('img');
                if (img && img.alt) {
                    product.name = img.alt.trim();
                }

                // Get prices - format is "30,22 €"
                const priceElements = item.querySelectorAll('.woocommerce-Price-amount bdi');
                const prices = [];

                priceElements.forEach(el => {
                    const text = el.textContent.trim();
                    // Match: "30,22 €" or "122,99 €"
                    const match = text.match(/([\d.,]+)\s*€/);
                    if (match) {
                        let priceStr = match[1];
                        // Convert "30,22" to 30.22
                        if (priceStr.includes(',')) {
                            priceStr = priceStr.replace('.', '').replace(',', '.');
                        }
                        const price = parseFloat(priceStr);
                        if (!isNaN(price) && price > 0 && !prices.includes(price)) {
                            prices.push(price);
                        }
                    }
                });

                // First unique price is B2B price (shown prominently)
                if (prices.length > 0) {
                    product.b2b_price_eur = prices[0];
                }

                // Check stock
                const classList = item.className || '';
                product.in_stock = !classList.includes('outofstock') &&
                    !item.innerText.toLowerCase().includes('out of stock');

                // Get image URL
                if (img && img.src) {
                    product.image_url = img.src;
                }

                if (product.name) {
                    results.push(product);
                }
            });

            return results;
        });

        console.log(`  Extracted ${products.length} products\n`);

        // Show sample
        if (products.length > 0) {
            console.log('  Sample products:');
            products.slice(0, 15).forEach(p => {
                const price = p.b2b_price_eur ? `€${p.b2b_price_eur.toFixed(2)}` : 'N/A';
                console.log(`    - ${(p.name || 'Unknown').substring(0, 40).padEnd(40)}: ${price}`);
            });
        }

        // Step 4: Merge with original data
        console.log('\nStep 4: Merging with original data...');
        const originalPath = path.join(__dirname, 'snigel-data', 'products.json');
        let originalData = [];

        if (fs.existsSync(originalPath)) {
            originalData = JSON.parse(fs.readFileSync(originalPath, 'utf8'));
            console.log(`  Found ${originalData.length} products in original data`);
        }

        const originalBySlug = {};
        originalData.forEach(p => {
            if (p.slug) originalBySlug[p.slug] = p;
        });

        const mergedProducts = products.map(p => {
            const original = originalBySlug[p.slug];
            if (original) {
                return {
                    ...p,
                    local_images: (original.local_images || []).filter(img =>
                        !img.includes('snigel_icon') && !img.includes('cropped-')
                    ),
                    colours: original.colours || [],
                    short_description: original.short_description || ''
                };
            }
            return p;
        });

        // Step 5: Update main merged products file
        console.log('\nStep 5: Updating main merged products file...');
        const mainMergedPath = path.join(__dirname, 'snigel-merged-products.json');

        if (fs.existsSync(mainMergedPath)) {
            const mainMerged = JSON.parse(fs.readFileSync(mainMergedPath, 'utf8'));

            let updated = 0;
            mergedProducts.forEach(p => {
                if (p.b2b_price_eur) {
                    const existing = mainMerged.find(m => m.slug === p.slug);
                    if (existing) {
                        existing.b2b_price_eur = p.b2b_price_eur;
                        // Calculate RRP as markup (typically 1.5x B2B price for retail)
                        if (!existing.rrp_eur) {
                            existing.rrp_eur = Math.round(p.b2b_price_eur * 1.5 * 100) / 100;
                        }
                        existing.has_b2b_price = true;
                        updated++;
                    }
                }
            });

            fs.writeFileSync(mainMergedPath, JSON.stringify(mainMerged, null, 2));
            console.log(`  Updated ${updated} products with B2B prices`);
        }

        // Save output
        const outputFile = path.join(config.outputDir, 'products-b2b-working.json');
        fs.writeFileSync(outputFile, JSON.stringify(mergedProducts, null, 2));

        // Stats
        const withPrices = mergedProducts.filter(p => p.b2b_price_eur).length;

        console.log('\n========================================================');
        console.log('                    COMPLETE');
        console.log('========================================================');
        console.log(`  Total products: ${mergedProducts.length}`);
        console.log(`  With B2B prices: ${withPrices}`);
        console.log(`  Output: ${outputFile}`);
        console.log('========================================================\n');

    } catch (error) {
        console.error('Error:', error.message);
        console.error(error.stack);
    } finally {
        await browser.close();
    }
}

main();
