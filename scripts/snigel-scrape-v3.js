/**
 * Snigel Product Scraper v3 - Fixed login flow
 */

const { chromium } = require('playwright');
const fs = require('fs');

const SNIGEL_URL = 'https://products.snigel.se';
const USERNAME = 'Raven Weapon AG';
const PASSWORD = 'wVREVbRZfqT&Fba@f(^2UKOw';

async function main() {
    console.log('═══════════════════════════════════════════════════════════');
    console.log('   SNIGEL PRODUCT SCRAPER v3');
    console.log('═══════════════════════════════════════════════════════════\n');

    const browser = await chromium.launch({
        headless: true,
        timeout: 90000
    });

    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    });

    context.setDefaultTimeout(60000);
    const page = await context.newPage();

    try {
        // Step 1: Go to Snigel homepage
        console.log('🌐 Step 1: Going to Snigel website...');
        await page.goto(SNIGEL_URL, { waitUntil: 'domcontentloaded', timeout: 60000 });
        await page.waitForTimeout(3000);
        console.log('   ✅ Page loaded');

        // Step 2: Click LOG IN button
        console.log('\n🔐 Step 2: Clicking LOG IN...');
        const loginLink = page.locator('a:has-text("LOG IN"), a:has-text("Log in"), a:has-text("Login"), a[href*="login"], a[href*="account"]').first();
        await loginLink.click();
        await page.waitForTimeout(3000);
        console.log('   ✅ Login page opened');

        // Screenshot login page
        await page.screenshot({ path: 'snigel-login-page.png' });
        console.log('   📸 Screenshot: snigel-login-page.png');

        // Step 3: Fill login form
        console.log('\n📝 Step 3: Filling credentials...');

        // Wait for login form
        await page.waitForSelector('input[type="text"], input[name="username"], input[name="log"], input#user_login', { timeout: 15000 });

        // Fill username
        const usernameInput = page.locator('input[name="log"], input[name="username"], input#user_login, input#username, form input[type="text"]').first();
        await usernameInput.fill(USERNAME);
        console.log('   ✅ Username entered');

        // Fill password
        const passwordInput = page.locator('input[name="pwd"], input[name="password"], input#user_pass, input#password, input[type="password"]').first();
        await passwordInput.fill(PASSWORD);
        console.log('   ✅ Password entered');

        // Screenshot before submit
        await page.screenshot({ path: 'snigel-before-submit.png' });

        // Step 4: Submit login
        console.log('\n🚀 Step 4: Submitting login...');
        const submitBtn = page.locator('input[type="submit"], button[type="submit"], button:has-text("Log"), #wp-submit').first();
        await submitBtn.click();
        await page.waitForTimeout(5000);
        console.log('   ✅ Login submitted');

        // Screenshot after login
        await page.screenshot({ path: 'snigel-after-login.png' });
        console.log('   📸 Screenshot: snigel-after-login.png');

        // Step 5: Go to All Products
        console.log('\n📦 Step 5: Loading All Products...');
        await page.goto(`${SNIGEL_URL}/product-category/all/`, {
            waitUntil: 'domcontentloaded',
            timeout: 60000
        });
        await page.waitForTimeout(5000);

        // Screenshot products page
        await page.screenshot({ path: 'snigel-products.png', fullPage: false });
        console.log('   📸 Screenshot: snigel-products.png');

        // Step 6: Scroll to load all products
        console.log('\n⏳ Step 6: Scrolling to load all products...');
        let previousHeight = 0;
        let scrollCount = 0;
        let noChangeCount = 0;

        while (scrollCount < 100 && noChangeCount < 5) {
            const currentHeight = await page.evaluate(() => document.body.scrollHeight);

            if (currentHeight === previousHeight) {
                noChangeCount++;
                // Try clicking load more
                const loadMore = page.locator('a.loadmore, .load-more, button:has-text("Load more"), a:has-text("Load more")');
                const loadMoreCount = await loadMore.count();
                if (loadMoreCount > 0) {
                    await loadMore.first().click().catch(() => {});
                    await page.waitForTimeout(2000);
                    noChangeCount = 0;
                }
            } else {
                noChangeCount = 0;
            }

            previousHeight = currentHeight;
            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
            await page.waitForTimeout(1000);
            scrollCount++;

            if (scrollCount % 10 === 0) {
                process.stdout.write(`\r   Scrolled ${scrollCount} times...`);
            }
        }
        console.log(`\n   ✅ Done scrolling (${scrollCount} times)`);

        // Final screenshot
        await page.screenshot({ path: 'snigel-all-products.png', fullPage: true });
        console.log('   📸 Full page screenshot: snigel-all-products.png');

        // Step 7: Extract products
        console.log('\n📋 Step 7: Extracting product names...');

        const products = await page.evaluate(() => {
            const names = new Set();

            // Try all possible selectors for WooCommerce
            const selectors = [
                '.woocommerce-loop-product__title',
                'h2.woocommerce-loop-product__title',
                '.product h2',
                '.products li.product h2',
                '.product-title',
                '.entry-title',
                'h2.product-title',
                '.product-name',
                'ul.products li h2',
                '.product-item-link',
                'a.woocommerce-LoopProduct-link h2'
            ];

            selectors.forEach(sel => {
                document.querySelectorAll(sel).forEach(el => {
                    const text = el.textContent.trim();
                    if (text && text.length > 2) {
                        names.add(text);
                    }
                });
            });

            // Backup: get text from product links
            document.querySelectorAll('.product a, a.woocommerce-LoopProduct-link').forEach(a => {
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

        // Count product containers
        const productCount = await page.evaluate(() => {
            return document.querySelectorAll('li.product, .product-item, article.product, .products > li').length;
        });

        console.log(`\n   Found ${products.length} unique product names`);
        console.log(`   Found ${productCount} product containers on page`);

        // Results
        console.log('\n═══════════════════════════════════════════════════════════');
        console.log('   SNIGEL PRODUCTS LIST');
        console.log('═══════════════════════════════════════════════════════════\n');

        if (products.length > 0) {
            products.sort().forEach((name, i) => {
                console.log(`${(i + 1).toString().padStart(3)}. ${name}`);
            });

            // Save results
            const output = {
                timestamp: new Date().toISOString(),
                total: products.length,
                products: products.sort()
            };

            fs.writeFileSync('snigel-products-list.json', JSON.stringify(output, null, 2));
            console.log(`\n✅ Saved ${products.length} products to snigel-products-list.json`);
        } else {
            console.log('   ⚠️ No products found!');
            console.log('   Check screenshots to debug:');
            console.log('   - snigel-login-page.png');
            console.log('   - snigel-after-login.png');
            console.log('   - snigel-products.png');
            console.log('   - snigel-all-products.png');

            // Save HTML for debugging
            const html = await page.content();
            fs.writeFileSync('snigel-debug.html', html);
            console.log('   - snigel-debug.html');
        }

    } catch (error) {
        console.error('\n❌ Error:', error.message);
        await page.screenshot({ path: 'snigel-error.png' }).catch(() => {});
    } finally {
        await browser.close();
    }

    console.log('\n═══════════════════════════════════════════════════════════\n');
}

main().catch(console.error);
