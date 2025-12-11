/**
 * Snigel B2B Portal Scraper - Final working version
 * Extracts product data from the grid layout visible in screenshots
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
    console.log('       SNIGEL B2B PORTAL SCRAPER (Final)');
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

        // Accept cookies first
        try {
            await page.click('button:has-text("Ok")', { timeout: 3000 });
            await delay(500);
        } catch (e) {}

        await page.fill('#username', config.username);
        await page.fill('#password', config.password);
        await page.click('button[name="login"]');
        await page.waitForLoadState('networkidle');
        await delay(3000);
        console.log('  Login successful!\n');

        // Step 2: Navigate to product category
        console.log('Step 2: Loading products...');
        await page.goto(`${config.baseUrl}/product-category/all/?currency=EUR`);
        await page.waitForLoadState('networkidle');
        await delay(5000);

        // Accept cookies again
        try {
            await page.click('button:has-text("Ok")', { timeout: 2000 });
            await delay(500);
        } catch (e) {}

        // Wait for products to load
        console.log('  Waiting for products to load...');
        await delay(3000);

        // Scroll slowly to load all products (infinite scroll)
        console.log('  Scrolling to load all products...\n');
        let previousHeight = 0;
        let sameHeightCount = 0;
        let scrollCount = 0;

        while (sameHeightCount < 5) {
            scrollCount++;
            const currentHeight = await page.evaluate(() => document.body.scrollHeight);

            if (currentHeight === previousHeight) {
                sameHeightCount++;
            } else {
                sameHeightCount = 0;
                previousHeight = currentHeight;
            }

            await page.evaluate(() => window.scrollBy(0, 800));
            await delay(1500);

            // Count product links
            const linkCount = await page.$$eval('a[href*="/product/"]', links => {
                const unique = new Set(links.map(l => l.href));
                return unique.size;
            });
            process.stdout.write(`  Scroll ${scrollCount}: ${linkCount} unique product links found   \r`);
        }

        console.log('\n');

        // Step 3: Extract all product data
        console.log('Step 3: Extracting product data...');

        // First, scroll back to top
        await page.evaluate(() => window.scrollTo(0, 0));
        await delay(1000);

        const products = await page.evaluate(() => {
            const results = [];
            const seenSlugs = new Set();

            // Get all product links
            const allLinks = document.querySelectorAll('a[href*="/product/"]');

            allLinks.forEach(link => {
                const url = link.href;
                if (!url.includes('/product/')) return;

                const slug = url.split('/product/')[1]?.replace(/\/$/, '');
                if (!slug || seenSlugs.has(slug)) return;
                seenSlugs.add(slug);

                const product = { url, slug };

                // Find the container - look for common parent patterns
                let container = link.closest('.iso-item, .tmb, .t-inside, [class*="product"], li, article');
                if (!container) {
                    container = link.parentElement?.parentElement?.parentElement;
                }

                if (container) {
                    // Get product name - look for text content
                    const textElements = container.querySelectorAll('h2, h3, h4, .t-entry-title, [class*="title"], span, p');
                    for (const el of textElements) {
                        const text = el.textContent.trim();
                        // Skip if it's a price or very short
                        if (text && text.length > 3 && !text.includes('€') && !text.match(/^\d/)) {
                            product.name = text.split('\n')[0].trim();
                            break;
                        }
                    }

                    // Get prices - look for euro prices
                    const priceText = container.innerText;
                    const priceMatches = priceText.match(/€\s*([\d.,]+)/g) || [];

                    if (priceMatches.length >= 2) {
                        // Two prices: first is B2B, second is RRP
                        const parsePrice = (str) => {
                            const match = str.match(/€\s*([\d.,]+)/);
                            if (match) {
                                let price = match[1];
                                if (price.includes(',')) {
                                    price = price.replace(/\./g, '').replace(',', '.');
                                }
                                return parseFloat(price);
                            }
                            return null;
                        };

                        product.b2b_price_eur = parsePrice(priceMatches[0]);
                        product.rrp_eur = parsePrice(priceMatches[1]);
                    } else if (priceMatches.length === 1) {
                        const match = priceMatches[0].match(/€\s*([\d.,]+)/);
                        if (match) {
                            let price = match[1];
                            if (price.includes(',')) {
                                price = price.replace(/\./g, '').replace(',', '.');
                            }
                            product.b2b_price_eur = parseFloat(price);
                        }
                    }

                    // Get image
                    const img = container.querySelector('img[src*="wp-content"], img[src*="upload"]');
                    if (img) {
                        product.image = img.src;
                    }

                    // Check stock
                    const containerText = container.innerText.toLowerCase();
                    product.in_stock = !containerText.includes('out of stock');
                }

                // Try to get name from the link text if not found
                if (!product.name) {
                    const linkText = link.textContent.trim();
                    if (linkText && linkText.length > 3 && !linkText.includes('€')) {
                        product.name = linkText;
                    }
                }

                // Try to get name from link's img alt
                if (!product.name) {
                    const img = link.querySelector('img');
                    if (img && img.alt && img.alt.length > 3) {
                        product.name = img.alt;
                    }
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
            products.slice(0, 10).forEach(p => {
                const b2b = p.b2b_price_eur ? `€${p.b2b_price_eur.toFixed(2)}` : 'N/A';
                const rrp = p.rrp_eur ? `€${p.rrp_eur.toFixed(2)}` : '';
                console.log(`    - ${p.name}: ${b2b} ${rrp ? `(RRP: ${rrp})` : ''}`);
            });
        }

        // Step 4: Merge with existing data
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
                // Filter out favicon images
                const realImages = (original.images || []).filter(img =>
                    !img.includes('snigel_icon') &&
                    !img.includes('cropped-') &&
                    img.includes('wp-content/uploads')
                );

                const realLocalImages = (original.local_images || []).filter(img =>
                    !img.includes('snigel_icon') &&
                    !img.includes('cropped-')
                );

                return {
                    ...product,
                    images: realImages,
                    local_images: realLocalImages,
                    colours: original.colours || [],
                    short_description: original.short_description || ''
                };
            }
            return product;
        });

        // Also update the main merged products file
        const mainMergedPath = path.join(__dirname, 'snigel-merged-products.json');
        if (fs.existsSync(mainMergedPath)) {
            const mainMerged = JSON.parse(fs.readFileSync(mainMergedPath, 'utf8'));

            // Create lookup of new B2B prices by slug
            const newPrices = {};
            mergedProducts.forEach(p => {
                if (p.b2b_price_eur) {
                    newPrices[p.slug] = {
                        b2b_price_eur: p.b2b_price_eur,
                        rrp_eur: p.rrp_eur
                    };
                }
            });

            // Update prices in main merged file
            let updated = 0;
            mainMerged.forEach(p => {
                if (newPrices[p.slug]) {
                    p.b2b_price_eur = newPrices[p.slug].b2b_price_eur;
                    p.rrp_eur = newPrices[p.slug].rrp_eur || p.rrp_eur;
                    p.has_b2b_price = true;
                    updated++;
                }
            });

            fs.writeFileSync(mainMergedPath, JSON.stringify(mainMerged, null, 2));
            console.log(`  Updated ${updated} products in main merged file`);
        }

        // Save results
        const outputFile = path.join(config.outputDir, 'products-b2b-final.json');
        fs.writeFileSync(outputFile, JSON.stringify(mergedProducts, null, 2));

        // Statistics
        const withPrices = mergedProducts.filter(p => p.b2b_price_eur).length;
        const withRRP = mergedProducts.filter(p => p.rrp_eur).length;
        const withImages = mergedProducts.filter(p => p.local_images && p.local_images.length > 0).length;

        console.log('\n========================================================');
        console.log('                    SCRAPING COMPLETE');
        console.log('========================================================');
        console.log(`  Products scraped: ${mergedProducts.length}`);
        console.log(`  With B2B prices: ${withPrices}`);
        console.log(`  With RRP prices: ${withRRP}`);
        console.log(`  With local images: ${withImages}`);
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
