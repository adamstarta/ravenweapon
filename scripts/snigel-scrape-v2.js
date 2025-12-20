/**
 * Snigel Product Scraper v2 - With longer timeouts
 */

const { chromium } = require('playwright');
const fs = require('fs');

const SNIGEL_URL = 'https://products.snigel.se';
const USERNAME = 'Raven Weapon AG';
const PASSWORD = 'wVREVbRZfqT&Fba@f(^2UKOw';

async function main() {
    console.log('═══════════════════════════════════════════════════════════');
    console.log('   SNIGEL PRODUCT SCRAPER v2');
    console.log('═══════════════════════════════════════════════════════════\n');

    const browser = await chromium.launch({
        headless: true,
        timeout: 60000
    });

    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    });

    context.setDefaultTimeout(60000);
    context.setDefaultNavigationTimeout(60000);

    const page = await context.newPage();

    try {
        // Step 1: Login
        console.log('🔐 Step 1: Logging in to Snigel...');
        await page.goto(SNIGEL_URL, { waitUntil: 'domcontentloaded', timeout: 60000 });

        // Wait for any input to appear
        await page.waitForSelector('input', { timeout: 30000 });

        // Fill login credentials
        const usernameField = page.locator('input[name="log"], input[name="username"], input#user_login, input[type="text"]').first();
        const passwordField = page.locator('input[name="pwd"], input[name="password"], input#user_pass, input[type="password"]').first();

        await usernameField.fill(USERNAME);
        await passwordField.fill(PASSWORD);

        // Click submit
        await page.locator('input[type="submit"], button[type="submit"], #wp-submit').first().click();

        // Wait for navigation after login
        await page.waitForTimeout(5000);
        console.log('   ✅ Login submitted\n');

        // Step 2: Go to All Products
        console.log('📦 Step 2: Loading All Products page...');
        await page.goto(`${SNIGEL_URL}/product-category/all/`, {
            waitUntil: 'domcontentloaded',
            timeout: 60000
        });

        // Wait for products to load
        await page.waitForTimeout(5000);

        // Scroll to load all products
        console.log('   ⏳ Scrolling to load all products...');
        let previousHeight = 0;
        let scrollCount = 0;

        while (scrollCount < 50) {
            const currentHeight = await page.evaluate(() => document.body.scrollHeight);

            if (currentHeight === previousHeight) {
                // Check for load more button
                const loadMoreExists = await page.locator('a.loadmore, .load-more-button, button:has-text("Load")').count();
                if (loadMoreExists > 0) {
                    await page.locator('a.loadmore, .load-more-button').first().click().catch(() => {});
                    await page.waitForTimeout(3000);
                } else {
                    break;
                }
            }

            previousHeight = currentHeight;
            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
            await page.waitForTimeout(1500);
            scrollCount++;

            if (scrollCount % 5 === 0) {
                console.log(`   ... scrolled ${scrollCount} times`);
            }
        }

        console.log(`   ✅ Finished scrolling (${scrollCount} times)\n`);

        // Step 3: Extract products
        console.log('📋 Step 3: Extracting product names...');

        // Take screenshot for debugging
        await page.screenshot({ path: 'snigel-products-page.png', fullPage: false });
        console.log('   📸 Screenshot saved: snigel-products-page.png');

        // Get page HTML for debugging
        const pageContent = await page.content();
        fs.writeFileSync('snigel-page-content.html', pageContent);
        console.log('   📄 HTML saved: snigel-page-content.html');

        // Try multiple selectors to find products
        const products = await page.evaluate(() => {
            const names = new Set();

            // WooCommerce product selectors
            const selectors = [
                'h2.woocommerce-loop-product__title',
                '.woocommerce-loop-product__title',
                '.product h2',
                '.products .product h2',
                'li.product h2',
                '.product-title',
                '.product-name',
                'h2.entry-title',
                '.product a h2',
                '.product-item h2',
                '.product-content h2',
                'article.product h2',
                '.product-grid-item h2',
                '.shop-product-title'
            ];

            for (const selector of selectors) {
                const elements = document.querySelectorAll(selector);
                elements.forEach(el => {
                    const text = el.textContent.trim();
                    if (text && text.length > 2) {
                        names.add(text);
                    }
                });
            }

            // Also try getting from links
            document.querySelectorAll('a[href*="/product/"]').forEach(a => {
                const h2 = a.querySelector('h2');
                if (h2) {
                    const text = h2.textContent.trim();
                    if (text && text.length > 2) {
                        names.add(text);
                    }
                }
            });

            return Array.from(names);
        });

        console.log(`\n   ✅ Found ${products.length} products\n`);

        // Results
        console.log('═══════════════════════════════════════════════════════════');
        console.log('   SNIGEL PRODUCTS');
        console.log('═══════════════════════════════════════════════════════════\n');

        if (products.length > 0) {
            products.sort().forEach((name, i) => {
                console.log(`${(i + 1).toString().padStart(3)}. ${name}`);
            });

            // Save to JSON
            const output = {
                timestamp: new Date().toISOString(),
                total: products.length,
                products: products.sort()
            };

            fs.writeFileSync('snigel-products-list.json', JSON.stringify(output, null, 2));
            console.log(`\n✅ Saved to snigel-products-list.json`);
        } else {
            console.log('   ⚠️ No products found. Check snigel-products-page.png');
            console.log('   The website structure may have changed.');
        }

    } catch (error) {
        console.error('\n❌ Error:', error.message);
        await page.screenshot({ path: 'snigel-error.png' }).catch(() => {});
        console.log('   📸 Error screenshot saved: snigel-error.png');
    } finally {
        await browser.close();
    }

    console.log('\n═══════════════════════════════════════════════════════════\n');
}

main().catch(console.error);
