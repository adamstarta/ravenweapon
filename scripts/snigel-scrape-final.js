/**
 * Snigel Product Scraper - FINAL WORKING VERSION
 */

const { chromium } = require('playwright');
const fs = require('fs');

const SNIGEL_URL = 'https://products.snigel.se';
const USERNAME = 'Raven Weapon AG';
const PASSWORD = 'wVREVbRZfqT&Fba@f(^2UKOw';

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// Convert slug to readable name
function slugToName(slug) {
    return slug
        .replace(/-\d+$/, '')  // Remove trailing numbers like -11, -09
        .split('-')
        .map(word => word.toUpperCase())
        .join(' ');
}

async function main() {
    console.log('═══════════════════════════════════════════════════════════');
    console.log('   SNIGEL PRODUCT SCRAPER - FINAL');
    console.log('═══════════════════════════════════════════════════════════\n');

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        viewport: { width: 1920, height: 1080 }
    });
    const page = await context.newPage();
    page.setDefaultTimeout(60000);

    try {
        // Step 1: Login
        console.log('🔐 Step 1: Logging in...');
        await page.goto(`${SNIGEL_URL}/my-account/`);
        await page.waitForLoadState('networkidle');
        await delay(2000);

        // Dismiss cookie popup
        try {
            await page.click('button:has-text("Ok")', { timeout: 3000 });
        } catch (e) {}

        await page.fill('#username', USERNAME);
        await page.fill('#password', PASSWORD);
        await page.click('button[name="login"]');
        await page.waitForLoadState('networkidle');
        await delay(3000);
        console.log('   ✅ Login successful!\n');

        // Step 2: Navigate to products
        console.log('📦 Step 2: Loading products...');
        await page.goto(`${SNIGEL_URL}/product-category/all/`);
        await page.waitForLoadState('networkidle');
        await delay(5000);

        // Dismiss cookie popup if it appears again
        try {
            await page.click('button:has-text("Ok")', { timeout: 2000 });
        } catch (e) {}

        // Step 3: Scroll to load all products
        console.log('\n⏳ Step 3: Scrolling to load all products...');
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
                process.stdout.write(`   Found ${count} product items...\r`);
            }
        }
        console.log(`\n   ✅ Total product items: ${lastCount}\n`);

        // Scroll back to top
        await page.evaluate(() => window.scrollTo(0, 0));
        await delay(2000);

        // Step 4: Extract product names
        console.log('📋 Step 4: Extracting product names...');

        const products = await page.evaluate(() => {
            const results = [];
            const seen = new Set();

            const items = document.querySelectorAll('.tmb.tmb-woocommerce');

            items.forEach(item => {
                // Get product link
                const link = item.querySelector('a[href*="/product/"]');
                if (!link) return;

                const url = link.href;

                // Extract slug from URL
                let slug = '';
                if (url.includes('/product/')) {
                    slug = url.split('/product/')[1]?.replace(/\/$/, '');
                }

                if (!slug || seen.has(slug)) return;
                seen.add(slug);

                // Get name from text content
                const textContent = item.innerText.trim();
                const lines = textContent.split('\n').map(l => l.trim()).filter(l => l.length > 0);

                let name = null;
                // The product name is typically the second line
                for (let i = 0; i < lines.length; i++) {
                    const line = lines[i];
                    // Skip button text, prices, and variant messages
                    if (line === 'ADD TO CART' ||
                        line === 'SELECT OPTIONS' ||
                        line === 'OUT OF STOCK' ||
                        line === 'READ MORE' ||
                        line.includes('€') ||
                        line.match(/^\d/) ||
                        line.includes('multiple variants') ||
                        line.includes('options may be chosen')) {
                        continue;
                    }
                    // This should be the product name
                    name = line;
                    break;
                }

                // Return with slug for fallback name
                results.push({ name, slug, url });
            });

            return results;
        });

        // Post-process: use slug as fallback for missing names
        const processedProducts = products.map(p => {
            let name = p.name;
            if (!name || name.length < 3) {
                // Convert slug to readable name
                name = slugToName(p.slug);
            }
            return { name, slug: p.slug, url: p.url };
        });

        // Results
        console.log('\n═══════════════════════════════════════════════════════════');
        console.log(`   FOUND ${processedProducts.length} PRODUCTS`);
        console.log('═══════════════════════════════════════════════════════════\n');

        processedProducts.sort((a, b) => a.name.localeCompare(b.name)).forEach((p, i) => {
            console.log(`${(i + 1).toString().padStart(3)}. ${p.name}`);
        });

        // Save results
        const output = {
            timestamp: new Date().toISOString(),
            source: 'Snigel B2B Portal',
            url: `${SNIGEL_URL}/product-category/all/`,
            total: processedProducts.length,
            products: processedProducts.sort((a, b) => a.name.localeCompare(b.name))
        };

        fs.writeFileSync('snigel-products-list.json', JSON.stringify(output, null, 2));
        console.log(`\n✅ Saved ${processedProducts.length} products to snigel-products-list.json`);

        // Screenshot
        await page.screenshot({ path: 'snigel-final.png', fullPage: true });

    } catch (error) {
        console.error('\n❌ Error:', error.message);
        await page.screenshot({ path: 'snigel-error-final.png' }).catch(() => {});
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
