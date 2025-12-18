/**
 * Debug script to test Snigel RRP scraping
 */
const { chromium } = require('playwright');

const CONFIG = {
    baseUrl: 'https://products.snigel.se',
    username: 'Raven Weapon AG',
    password: 'wVREVbRZfqT&Fba@f(^2UKOw'
};

async function debug() {
    console.log('Starting debug with HEADED browser...\n');

    const browser = await chromium.launch({
        headless: false,  // HEADED - so we can see
        slowMo: 500       // Slow down for visibility
    });

    const context = await browser.newContext({
        viewport: { width: 1280, height: 900 }
    });

    const page = await context.newPage();

    try {
        // Login
        console.log('1. Logging in...');
        await page.goto(`${CONFIG.baseUrl}/my-account/`);
        await page.waitForTimeout(2000);

        // Dismiss cookie if present
        try { await page.click('button:has-text("Ok")', { timeout: 2000 }); } catch(e) {}

        await page.fill('#username', CONFIG.username);
        await page.fill('#password', CONFIG.password);
        await page.click('button[name="login"]');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);
        console.log('   Logged in!\n');

        // Go to product list with EUR
        console.log('2. Setting EUR currency...');
        await page.goto(`${CONFIG.baseUrl}/product-category/all/?currency=EUR`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);
        try { await page.click('button:has-text("Ok")', { timeout: 2000 }); } catch(e) {}
        console.log('   EUR set!\n');

        // Test 3 specific products
        const testProducts = [
            'https://products.snigel.se/product/featherweight-stretcher-09/',
            'https://products.snigel.se/product/flight-suit-pilot-09/',
            'https://products.snigel.se/product/squeeze-ballistic-front-and-back-1-3-s2/'
        ];

        for (const url of testProducts) {
            console.log(`\n3. Testing: ${url}`);
            await page.goto(url + '?currency=EUR', { waitUntil: 'networkidle' });
            await page.waitForTimeout(2000);

            // Extract RRP
            const rrpData = await page.evaluate(() => {
                const pageText = document.body.innerText;

                // Find all RRP mentions
                const rrpMatches = pageText.match(/RRP\s+[\d\s.,]+\s*€/gi);

                // Find all EUR prices
                const priceEls = document.querySelectorAll('.woocommerce-Price-amount');
                const prices = [];
                priceEls.forEach(el => {
                    if (el.textContent.includes('€')) {
                        prices.push(el.textContent.trim());
                    }
                });

                // Check if page shows SEK or EUR
                const currencyCheck = pageText.includes('SEK') ? 'SEK visible' :
                                     pageText.includes('€') ? 'EUR visible' : 'Unknown currency';

                return { rrpMatches, prices, currencyCheck };
            });

            console.log('   RRP matches:', rrpData.rrpMatches);
            console.log('   EUR prices:', rrpData.prices.slice(0, 5));
            console.log('   Currency check:', rrpData.currencyCheck);
        }

        console.log('\n\n=== DEBUG COMPLETE ===');
        console.log('Browser will stay open for 30 seconds for manual inspection...');
        await page.waitForTimeout(30000);

    } catch (error) {
        console.error('Error:', error.message);
        await page.waitForTimeout(10000);
    } finally {
        await browser.close();
    }
}

debug();
