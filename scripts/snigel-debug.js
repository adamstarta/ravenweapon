/**
 * Debug Snigel - Check page structure
 */

const { chromium } = require('playwright');
const fs = require('fs');

const SNIGEL_URL = 'https://products.snigel.se';
const USERNAME = 'Raven Weapon AG';
const PASSWORD = 'wVREVbRZfqT&Fba@f(^2UKOw';

async function main() {
    console.log('🔍 Debugging Snigel website structure...\n');

    const browser = await chromium.launch({ headless: false }); // Show browser
    const context = await browser.newContext();
    const page = await context.newPage();

    try {
        // Go to login page
        console.log('1. Going to Snigel...');
        await page.goto(SNIGEL_URL, { timeout: 30000 });
        await page.waitForTimeout(3000);

        // Screenshot login page
        await page.screenshot({ path: 'snigel-1-login.png', fullPage: true });
        console.log('   📸 Screenshot: snigel-1-login.png');

        // Get login form HTML
        const loginHtml = await page.evaluate(() => {
            const form = document.querySelector('form');
            return form ? form.outerHTML.substring(0, 2000) : 'No form found';
        });
        console.log('\n   Login form HTML:\n', loginHtml.substring(0, 500));

        // Try to login
        console.log('\n2. Attempting login...');

        // Try different input selectors
        const inputs = await page.evaluate(() => {
            const allInputs = document.querySelectorAll('input');
            return Array.from(allInputs).map(i => ({
                type: i.type,
                name: i.name,
                id: i.id,
                class: i.className
            }));
        });
        console.log('   Found inputs:', JSON.stringify(inputs, null, 2));

        // Fill login
        await page.fill('input[name="log"], input#user_login, input[type="text"]', USERNAME).catch(() => {});
        await page.fill('input[name="pwd"], input#user_pass, input[type="password"]', PASSWORD).catch(() => {});

        await page.screenshot({ path: 'snigel-2-filled.png', fullPage: true });
        console.log('   📸 Screenshot: snigel-2-filled.png');

        // Submit
        await page.click('input[type="submit"], button[type="submit"], #wp-submit').catch(() => {});
        await page.waitForTimeout(5000);

        await page.screenshot({ path: 'snigel-3-after-login.png', fullPage: true });
        console.log('   📸 Screenshot: snigel-3-after-login.png');

        // Go to all products
        console.log('\n3. Going to All Products...');
        await page.goto(`${SNIGEL_URL}/product-category/all/`, { timeout: 30000 });
        await page.waitForTimeout(5000);

        await page.screenshot({ path: 'snigel-4-products.png', fullPage: true });
        console.log('   📸 Screenshot: snigel-4-products.png');

        // Get product HTML structure
        const productHtml = await page.evaluate(() => {
            const products = document.querySelector('.products, .product-list, ul.products, .woocommerce');
            return products ? products.outerHTML.substring(0, 5000) : 'No products container found';
        });

        fs.writeFileSync('snigel-products-html.txt', productHtml);
        console.log('   📄 Saved HTML to: snigel-products-html.txt');

        // Try to find product names with various selectors
        const productNames = await page.evaluate(() => {
            const results = {};

            const selectors = [
                '.product h2',
                '.product-title',
                '.woocommerce-loop-product__title',
                'h2.woocommerce-loop-product__title',
                '.products .product h2',
                'li.product h2',
                '.product-name',
                '.product a h2',
                '.product-item-link',
                'h3.product-title',
                '.entry-title',
                'article h2',
                '.product-content h2',
                '.item-title'
            ];

            selectors.forEach(sel => {
                const elements = document.querySelectorAll(sel);
                if (elements.length > 0) {
                    results[sel] = Array.from(elements).slice(0, 5).map(e => e.textContent.trim());
                }
            });

            return results;
        });

        console.log('\n   Product names found by selector:');
        console.log(JSON.stringify(productNames, null, 2));

        // Count all visible products
        const productCount = await page.evaluate(() => {
            return document.querySelectorAll('.product, li.product, .product-item, article.product').length;
        });
        console.log(`\n   Total product elements found: ${productCount}`);

        console.log('\n✅ Debug complete! Check the screenshots and snigel-products-html.txt');
        console.log('   Press Ctrl+C to close browser...');

        // Keep browser open for inspection
        await page.waitForTimeout(60000);

    } catch (error) {
        console.error('Error:', error.message);
        await page.screenshot({ path: 'snigel-error.png', fullPage: true });
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
