/**
 * Scrape prices for the 6 missing products directly from their product pages
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const config = {
    baseUrl: 'https://products.snigel.se',
    username: 'Raven Weapon AG',
    password: 'wVREVbRZfqT&Fba@f(^2UKOw',
};

const missingProducts = [
    { slug: '34x100-cm-dual-weapon-bag-11', name: '34×100 cm dual Weapon bag -11' },
    { slug: 'dual-magazine-pouch-15', name: 'Dual Magazine pouch -18' },
    { slug: 'flight-suit-other-09', name: 'Flight suit other -09' },
    { slug: 'tactical-coverall-bg-10f', name: 'Tactical coverall BG -10F' },
    { slug: 'flight-suit-pilot-09', name: 'Flight suit, Pilot -09' },
    { slug: 'fleece-jacket-1-0', name: 'Fleece jacket 1.0' },
];

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function main() {
    console.log('\n========================================================');
    console.log('       SCRAPE MISSING PRODUCT PRICES');
    console.log('========================================================\n');

    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    });
    const page = await context.newPage();
    page.setDefaultTimeout(30000);

    try {
        // Login first
        console.log('Logging in...');
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
        console.log('Login successful!\n');

        const results = [];

        for (const product of missingProducts) {
            const url = `${config.baseUrl}/product/${product.slug}/?currency=EUR`;
            console.log(`Checking: ${product.name}...`);

            try {
                await page.goto(url, { timeout: 20000 });
                await delay(3000);

                // Extract price from page
                const priceData = await page.evaluate(() => {
                    // Look for price elements
                    const priceEl = document.querySelector('.price bdi, .woocommerce-Price-amount bdi');
                    if (priceEl) {
                        const text = priceEl.textContent.trim();
                        const match = text.match(/([\d,.]+)\s*€/);
                        if (match) {
                            let price = match[1];
                            if (price.includes(',')) {
                                price = price.replace('.', '').replace(',', '.');
                            }
                            return { price: parseFloat(price), text };
                        }
                    }

                    // Also check for "out of stock" message
                    const stockEl = document.querySelector('.stock, .out-of-stock');
                    const isOutOfStock = stockEl ? stockEl.textContent.toLowerCase().includes('out of stock') : false;

                    return { price: null, isOutOfStock };
                });

                if (priceData.price) {
                    console.log(`  ✓ Found price: €${priceData.price}`);
                    results.push({
                        slug: product.slug,
                        name: product.name,
                        b2b_price_eur: priceData.price
                    });
                } else if (priceData.isOutOfStock) {
                    console.log(`  ✗ Out of stock`);
                } else {
                    console.log(`  ✗ No price found`);
                }
            } catch (error) {
                console.log(`  ✗ Error: ${error.message}`);
            }
        }

        console.log('\n========================================================');
        console.log('                    RESULTS');
        console.log('========================================================\n');

        if (results.length > 0) {
            console.log('Products with prices found:');
            results.forEach(p => {
                console.log(`  - ${p.name}: €${p.b2b_price_eur}`);
            });

            // Update the main merged file
            console.log('\nUpdating main merged products file...');
            const mergedPath = path.join(__dirname, 'snigel-merged-products.json');
            const mergedData = JSON.parse(fs.readFileSync(mergedPath, 'utf8'));

            let updated = 0;
            results.forEach(r => {
                const product = mergedData.find(p => p.slug === r.slug);
                if (product) {
                    product.b2b_price_eur = r.b2b_price_eur;
                    product.rrp_eur = Math.round(r.b2b_price_eur * 1.5 * 100) / 100;
                    product.has_b2b_price = true;
                    updated++;
                }
            });

            fs.writeFileSync(mergedPath, JSON.stringify(mergedData, null, 2));
            console.log(`Updated ${updated} products`);

            // Save results for price update
            fs.writeFileSync(
                path.join(__dirname, 'snigel-b2b-data', 'missing-products-prices.json'),
                JSON.stringify(results, null, 2)
            );
        } else {
            console.log('No prices found for any missing products.');
        }

    } catch (error) {
        console.error('Error:', error.message);
    } finally {
        await browser.close();
    }
}

main();
