/**
 * Snigel B2B Portal Scraper - V2
 * Uses image alt text and direct element inspection
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
    console.log('       SNIGEL B2B PORTAL SCRAPER V2');
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

            const count = await page.$$eval('a[href*="/product/"]', links => {
                return new Set(links.map(l => l.href)).size;
            });

            if (count === lastCount) {
                stableCount++;
            } else {
                stableCount = 0;
                lastCount = count;
                process.stdout.write(`  Found ${count} product links...   \r`);
            }
        }
        console.log(`\n  Total unique product links: ${lastCount}\n`);

        // Step 3: Extract product data using a different approach
        console.log('Step 3: Extracting product data...');

        // Scroll back to top
        await page.evaluate(() => window.scrollTo(0, 0));
        await delay(2000);

        // Get the page HTML and parse it
        const products = await page.evaluate(() => {
            const results = [];
            const seen = new Set();

            // Find all product containers - they have the class 'iso-item' or 'tmb'
            const containers = document.querySelectorAll('.iso-item, .tmb');

            containers.forEach(container => {
                // Get the product link
                const link = container.querySelector('a[href*="/product/"]');
                if (!link) return;

                const url = link.href;
                const slug = url.split('/product/')[1]?.replace(/\/$/, '');
                if (!slug || seen.has(slug)) return;
                seen.add(slug);

                const product = { url, slug };

                // Get name from image alt attribute (most reliable)
                const img = container.querySelector('img');
                if (img && img.alt) {
                    product.name = img.alt.trim();
                }

                // If no name from alt, try the title link
                if (!product.name) {
                    const titleLink = container.querySelector('a.t-entry-title, .t-entry-title a, a[rel="bookmark"]');
                    if (titleLink) {
                        product.name = titleLink.textContent.trim();
                    }
                }

                // Get prices - look for elements with € symbol
                // The layout shows prices like "€6,24 € 30,22 €" (B2B and RRP)
                const textContent = container.textContent;
                const pricePattern = /€\s*([\d]+[.,][\d]{2})/g;
                const prices = [];
                let match;

                while ((match = pricePattern.exec(textContent)) !== null) {
                    let priceStr = match[1];
                    // Convert European format to decimal
                    if (priceStr.includes(',')) {
                        priceStr = priceStr.replace(',', '.');
                    }
                    const price = parseFloat(priceStr);
                    if (!isNaN(price) && price > 0) {
                        prices.push(price);
                    }
                }

                // Usually first price is B2B (lower), second is RRP (higher)
                if (prices.length >= 2) {
                    // Sort to ensure B2B < RRP
                    const sorted = [...prices].sort((a, b) => a - b);
                    product.b2b_price_eur = sorted[0];
                    product.rrp_eur = sorted[sorted.length - 1];
                } else if (prices.length === 1) {
                    product.b2b_price_eur = prices[0];
                }

                // Check stock status
                const containerClasses = container.className || '';
                const innerText = container.innerText.toLowerCase();
                product.in_stock = !containerClasses.includes('out-of-stock') &&
                    !innerText.includes('out of stock');

                // Get image URL
                if (img && img.src) {
                    product.image_url = img.src;
                }

                if (product.name && product.name !== 'Add to cart' && product.name !== 'Select options') {
                    results.push(product);
                }
            });

            return results;
        });

        console.log(`  Extracted ${products.length} products with names\n`);

        // Show sample
        if (products.length > 0) {
            console.log('  Sample products:');
            products.slice(0, 10).forEach(p => {
                const b2b = p.b2b_price_eur ? `€${p.b2b_price_eur.toFixed(2)}` : 'N/A';
                const rrp = p.rrp_eur ? `(RRP: €${p.rrp_eur.toFixed(2)})` : '';
                console.log(`    - ${p.name.substring(0, 35).padEnd(35)}: ${b2b} ${rrp}`);
            });
        } else {
            // Debug: save page HTML
            const html = await page.content();
            fs.writeFileSync(path.join(config.outputDir, 'debug-page.html'), html);
            console.log('  No products extracted! Saved page HTML for debugging.');

            // Try alternative: find all images with product URLs
            console.log('\n  Trying alternative extraction...');
            const altProducts = await page.evaluate(() => {
                const results = [];
                const seen = new Set();

                // Find all images that link to products
                document.querySelectorAll('a[href*="/product/"] img').forEach(img => {
                    const link = img.closest('a[href*="/product/"]');
                    if (!link) return;

                    const url = link.href;
                    const slug = url.split('/product/')[1]?.replace(/\/$/, '');
                    if (!slug || seen.has(slug)) return;
                    seen.add(slug);

                    results.push({
                        url,
                        slug,
                        name: img.alt || slug.replace(/-/g, ' '),
                        image_url: img.src
                    });
                });

                return results;
            });

            console.log(`  Alternative extraction found ${altProducts.length} products`);
            if (altProducts.length > 0) {
                products.push(...altProducts);
            }
        }

        // Step 4: Merge with original data (for local images)
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

        // Update main merged file with new prices
        const mainMergedPath = path.join(__dirname, 'snigel-merged-products.json');
        if (fs.existsSync(mainMergedPath)) {
            const mainMerged = JSON.parse(fs.readFileSync(mainMergedPath, 'utf8'));

            let updated = 0;
            mergedProducts.forEach(p => {
                if (p.b2b_price_eur) {
                    const existing = mainMerged.find(m => m.slug === p.slug);
                    if (existing) {
                        existing.b2b_price_eur = p.b2b_price_eur;
                        existing.rrp_eur = p.rrp_eur || existing.rrp_eur;
                        existing.has_b2b_price = true;
                        updated++;
                    }
                }
            });

            fs.writeFileSync(mainMergedPath, JSON.stringify(mainMerged, null, 2));
            console.log(`  Updated ${updated} products with B2B prices in main merged file`);
        }

        // Save output
        const outputFile = path.join(config.outputDir, 'products-b2b-v2.json');
        fs.writeFileSync(outputFile, JSON.stringify(mergedProducts, null, 2));

        // Stats
        const withPrices = mergedProducts.filter(p => p.b2b_price_eur).length;
        const withRRP = mergedProducts.filter(p => p.rrp_eur).length;

        console.log('\n========================================================');
        console.log('                    COMPLETE');
        console.log('========================================================');
        console.log(`  Products: ${mergedProducts.length}`);
        console.log(`  With B2B prices: ${withPrices}`);
        console.log(`  With RRP prices: ${withRRP}`);
        console.log('========================================================\n');

    } catch (error) {
        console.error('Error:', error.message);
    } finally {
        await browser.close();
    }
}

main();
