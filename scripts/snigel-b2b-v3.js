/**
 * Snigel B2B Portal Scraper - V3
 * Debug version to understand price format
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
    console.log('       SNIGEL B2B PORTAL SCRAPER V3 (Debug Prices)');
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

        // Wait a bit more
        await delay(3000);

        // Debug: Get raw text from first product container
        console.log('\nStep 3: Debugging price extraction...\n');

        const debugInfo = await page.evaluate(() => {
            const results = {
                containerCount: 0,
                sampleContainers: [],
                priceElements: [],
                allPriceText: []
            };

            // Find containers
            const containers = document.querySelectorAll('.iso-item, .tmb, [class*="product"]');
            results.containerCount = containers.length;

            // Get first 3 containers' full text
            for (let i = 0; i < Math.min(3, containers.length); i++) {
                const c = containers[i];
                results.sampleContainers.push({
                    classes: c.className,
                    text: c.innerText.substring(0, 500),
                    html: c.innerHTML.substring(0, 1000)
                });
            }

            // Find any element with price-related class
            document.querySelectorAll('[class*="price"], .amount, bdi, .woocommerce-Price-amount').forEach(el => {
                results.priceElements.push({
                    tag: el.tagName,
                    classes: el.className,
                    text: el.textContent.trim()
                });
            });

            // Search entire page for € symbol
            const bodyText = document.body.innerText;
            const euroMatches = bodyText.match(/€[\s\d.,]+/g) || [];
            results.allPriceText = euroMatches.slice(0, 20);

            // Also look for currency amounts without €
            const numMatches = bodyText.match(/\d+[.,]\d{2}\s*€/g) || [];
            results.allPriceText.push(...numMatches.slice(0, 10));

            return results;
        });

        console.log('Container count:', debugInfo.containerCount);
        console.log('\nSample container text:');
        debugInfo.sampleContainers.forEach((c, i) => {
            console.log(`\n--- Container ${i + 1} (${c.classes.substring(0, 50)}) ---`);
            console.log(c.text.substring(0, 300));
        });

        console.log('\n\nPrice elements found:', debugInfo.priceElements.length);
        debugInfo.priceElements.slice(0, 10).forEach(p => {
            console.log(`  <${p.tag} class="${p.classes}">: "${p.text}"`);
        });

        console.log('\n\nEuro patterns found in page:', debugInfo.allPriceText.length);
        debugInfo.allPriceText.slice(0, 20).forEach(p => {
            console.log(`  "${p}"`);
        });

        // Save full page HTML for analysis
        const html = await page.content();
        fs.writeFileSync(path.join(config.outputDir, 'full-page.html'), html);
        console.log('\nFull page HTML saved to full-page.html');

        // Take screenshot
        await page.screenshot({ path: path.join(config.outputDir, 'v3-screenshot.png'), fullPage: false });
        console.log('Screenshot saved to v3-screenshot.png');

    } catch (error) {
        console.error('Error:', error.message);
    } finally {
        await browser.close();
    }
}

main();
