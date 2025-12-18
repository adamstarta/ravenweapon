/**
 * Quick test to verify the price extraction fix
 */
const { chromium } = require('playwright');

const CONFIG = {
    baseUrl: 'https://products.snigel.se',
    username: 'Raven Weapon AG',
    password: 'wVREVbRZfqT&Fba@f(^2UKOw'
};

// Test products - with correct expected EUR ranges
const testProducts = [
    { name: 'Badge holder', url: 'https://products.snigel.se/product/badge-holder/', expectedRange: [15, 20] },
    { name: 'Squeeze Ballistic front and back', url: 'https://products.snigel.se/product/squeeze-ballistic-front-and-back-1-3-s2/', expectedRange: [1500, 2100] },
    { name: 'Flight suit pilot', url: 'https://products.snigel.se/product/flight-suit-pilot-09/', expectedRange: [1200, 1400] }
];

async function test() {
    console.log('Testing fixed price extraction...\n');

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    });

    // Block heavy resources
    await context.route('**/*', (route) => {
        const resourceType = route.request().resourceType();
        if (['image', 'stylesheet', 'font', 'media'].includes(resourceType)) {
            route.abort();
        } else {
            route.continue();
        }
    });

    const page = await context.newPage();

    try {
        // Login
        console.log('Logging in...');
        await page.goto(`${CONFIG.baseUrl}/my-account/`, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.waitForTimeout(1000);
        try { await page.click('button:has-text("Ok")', { timeout: 1500 }); } catch (e) {}
        await page.fill('#username', CONFIG.username);
        await page.fill('#password', CONFIG.password);
        await page.click('button[name="login"]');
        await page.waitForTimeout(2000);
        console.log('Logged in!\n');

        // Set EUR
        await page.goto(`${CONFIG.baseUrl}/product-category/all/?currency=EUR`, { waitUntil: 'commit', timeout: 15000 });
        await page.waitForTimeout(1000);
        try { await page.click('button:has-text("Ok")', { timeout: 1000 }); } catch (e) {}

        // Test each product
        let passed = 0, failed = 0;

        for (const product of testProducts) {
            console.log(`Testing: ${product.name}`);
            await page.goto(product.url + '?currency=EUR', { waitUntil: 'commit', timeout: 15000 });
            await page.waitForSelector('.woocommerce-Price-amount', { timeout: 3000 }).catch(() => {});

            const result = await page.evaluate(() => {
                function parseEurPrice(str) {
                    if (!str) return null;
                    let clean = str
                        .replace(/[€\s]/g, '')
                        .replace(/\./g, '')
                        .replace(',', '.');
                    const num = parseFloat(clean);
                    return isNaN(num) ? null : num;
                }

                // Priority 1: RRP pattern
                const pageText = document.body.innerText;
                const rrpMatch = pageText.match(/RRP\s+([\d\s.,]+)\s*€/i);
                if (rrpMatch) {
                    const price = parseEurPrice(rrpMatch[1]);
                    if (price > 0) return { price, source: 'RRP text' };
                }

                // Priority 2: FIRST EUR price NOT in related/upsells
                const allPrices = document.querySelectorAll('.woocommerce-Price-amount');
                for (const el of allPrices) {
                    if (el.closest('.related, .up-sells, .cross-sells')) continue;
                    const text = el.textContent.trim();
                    if (text.includes('€')) {
                        const price = parseEurPrice(text);
                        if (price && price > 0) return { price, source: 'first main price' };
                    }
                }

                return { price: null, source: 'not found' };
            });

            const inRange = result.price >= product.expectedRange[0] && result.price <= product.expectedRange[1];
            const status = inRange ? '✓' : '✗';

            console.log(`  ${status} Price: €${result.price?.toFixed(2) || 'null'} (${result.source})`);
            console.log(`    Expected: €${product.expectedRange[0]}-${product.expectedRange[1]}`);

            if (inRange) passed++;
            else failed++;
        }

        console.log(`\n${'='.repeat(50)}`);
        console.log(`Results: ${passed} passed, ${failed} failed`);
        if (failed === 0) {
            console.log('✓ FIX WORKS! Ready to re-run price sync.');
        } else {
            console.log('✗ Some products still failing - need more investigation');
        }

    } catch (error) {
        console.error('Error:', error.message);
    } finally {
        await browser.close();
    }
}

test();
