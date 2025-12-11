/**
 * Snigel B2B Portal - Save Full HTML after scrolling
 * Saves the complete HTML with all products loaded via infinite scroll
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
    console.log('       SNIGEL - SAVE FULL HTML WITH ALL PRODUCTS');
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

        // Step 2: Go to products page
        console.log('Step 2: Loading products...');
        await page.goto(`${config.baseUrl}/product-category/all/?currency=EUR`);
        await page.waitForLoadState('networkidle');
        await delay(5000);

        try {
            await page.click('button:has-text("Ok")', { timeout: 2000 });
        } catch (e) {}

        // Step 3: Scroll through entire page to load all products
        console.log('Step 3: Scrolling to load all products...\n');

        let lastHeight = 0;
        let stableCount = 0;
        let scrollNum = 0;

        while (stableCount < 8) {
            scrollNum++;
            const currentHeight = await page.evaluate(() => document.body.scrollHeight);

            // Scroll down
            await page.evaluate(() => window.scrollBy(0, 800));
            await delay(1500);

            if (currentHeight === lastHeight) {
                stableCount++;
            } else {
                stableCount = 0;
                lastHeight = currentHeight;
            }

            // Count products
            const productCount = await page.$$eval('.tmb.tmb-woocommerce', items => items.length);
            process.stdout.write(`  Scroll ${scrollNum}: ${productCount} products loaded   \r`);
        }

        console.log('\n');

        // Scroll back to top
        await page.evaluate(() => window.scrollTo(0, 0));
        await delay(2000);

        // Step 4: Save the full HTML
        console.log('Step 4: Saving full HTML...');
        const html = await page.content();
        const htmlPath = path.join(config.outputDir, 'all-products.html');
        fs.writeFileSync(htmlPath, html);
        console.log(`  Saved ${(html.length / 1024).toFixed(0)} KB to ${htmlPath}`);

        // Quick count of products in HTML
        const productMatches = html.match(/class="tmb tmb-woocommerce/g) || [];
        console.log(`  Found ${productMatches.length} product containers in HTML`);

        // Also save screenshot
        await page.screenshot({ path: path.join(config.outputDir, 'all-products.png'), fullPage: true });
        console.log('  Screenshot saved');

        console.log('\n========================================================');
        console.log('                    COMPLETE');
        console.log('========================================================');
        console.log('  Now run: node extract-from-html.js');
        console.log('========================================================\n');

    } catch (error) {
        console.error('Error:', error.message);
    } finally {
        await browser.close();
    }
}

main();
