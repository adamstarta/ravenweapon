/**
 * Snigel Product Scraper v4 - Longer timeouts for slow site
 */

const { chromium } = require('playwright');
const fs = require('fs');

const SNIGEL_URL = 'https://products.snigel.se';
const USERNAME = 'Raven Weapon AG';
const PASSWORD = 'wVREVbRZfqT&Fba@f(^2UKOw';

async function main() {
    console.log('═══════════════════════════════════════════════════════════');
    console.log('   SNIGEL PRODUCT SCRAPER v4 (Slow site fix)');
    console.log('═══════════════════════════════════════════════════════════\n');

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    });

    // Very long timeouts for slow site
    context.setDefaultTimeout(120000);
    context.setDefaultNavigationTimeout(120000);

    const page = await context.newPage();

    try {
        // Step 1: Go to site
        console.log('🌐 Step 1: Going to Snigel (may take a while)...');
        await page.goto(SNIGEL_URL, { waitUntil: 'commit', timeout: 120000 });
        await page.waitForTimeout(5000);
        console.log('   ✅ Site loaded');

        // Step 2: Click LOG IN
        console.log('\n🔐 Step 2: Clicking LOG IN...');
        await page.click('a:has-text("LOG IN"), a:has-text("Log in")');
        await page.waitForTimeout(5000);
        console.log('   ✅ Login page');

        // Step 3: Fill login
        console.log('\n📝 Step 3: Logging in as Raven Weapon...');
        await page.fill('input[name="log"], input[name="username"], input#user_login', USERNAME);
        await page.fill('input[name="pwd"], input[name="password"], input#user_pass', PASSWORD);
        await page.click('input[type="submit"], button[type="submit"], #wp-submit');
        await page.waitForTimeout(8000);
        console.log('   ✅ Logged in');

        // Step 4: Click PRODUCTS in menu
        console.log('\n📦 Step 4: Going to PRODUCTS...');
        await page.click('a:has-text("PRODUCTS")');
        await page.waitForTimeout(10000);

        // Or go directly to all products URL
        console.log('   Loading all products page...');
        await page.goto(`${SNIGEL_URL}/product-category/all/`, {
            waitUntil: 'commit',
            timeout: 120000
        });
        await page.waitForTimeout(10000);
        console.log('   ✅ Products page loaded');

        // Screenshot
        await page.screenshot({ path: 'snigel-products-v4.png' });
        console.log('   📸 Screenshot saved');

        // Step 5: Scroll to load products
        console.log('\n⏳ Step 5: Loading all products...');
        let lastHeight = 0;
        let attempts = 0;

        while (attempts < 60) {
            const height = await page.evaluate(() => document.body.scrollHeight);

            if (height === lastHeight) {
                // Try load more button
                try {
                    const loadMore = await page.$('a.loadmore');
                    if (loadMore) {
                        await loadMore.click();
                        await page.waitForTimeout(3000);
                    } else {
                        break;
                    }
                } catch (e) {
                    break;
                }
            }

            lastHeight = height;
            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
            await page.waitForTimeout(2000);
            attempts++;

            if (attempts % 5 === 0) {
                const count = await page.evaluate(() =>
                    document.querySelectorAll('li.product, .product-item').length
                );
                console.log(`   ... ${count} products loaded`);
            }
        }

        // Final screenshot
        await page.screenshot({ path: 'snigel-all-loaded.png', fullPage: true });

        // Step 6: Extract products
        console.log('\n📋 Step 6: Extracting product names...');

        const products = await page.evaluate(() => {
            const names = [];

            // Get all product titles
            document.querySelectorAll('.woocommerce-loop-product__title, h2.woocommerce-loop-product__title, .product h2, li.product h2').forEach(el => {
                const name = el.textContent.trim();
                if (name && !names.includes(name)) {
                    names.push(name);
                }
            });

            return names;
        });

        // Results
        console.log('\n═══════════════════════════════════════════════════════════');
        console.log(`   FOUND ${products.length} PRODUCTS`);
        console.log('═══════════════════════════════════════════════════════════\n');

        products.sort().forEach((name, i) => {
            console.log(`${(i + 1).toString().padStart(3)}. ${name}`);
        });

        // Save
        fs.writeFileSync('snigel-products-list.json', JSON.stringify({
            timestamp: new Date().toISOString(),
            total: products.length,
            products: products.sort()
        }, null, 2));

        console.log(`\n✅ Saved to snigel-products-list.json`);

    } catch (error) {
        console.error('\n❌ Error:', error.message);
        await page.screenshot({ path: 'snigel-error-v4.png' }).catch(() => {});

        // Save page HTML for debugging
        const html = await page.content().catch(() => '');
        if (html) {
            fs.writeFileSync('snigel-debug.html', html);
            console.log('   📄 Debug HTML saved');
        }
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
