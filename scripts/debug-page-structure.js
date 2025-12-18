/**
 * Debug page structure to understand price extraction
 */
const { chromium } = require('playwright');

const CONFIG = {
    baseUrl: 'https://products.snigel.se',
    username: 'Raven Weapon AG',
    password: 'wVREVbRZfqT&Fba@f(^2UKOw'
};

async function debug() {
    console.log('Debugging page structure...\n');

    const browser = await chromium.launch({ headless: true }); // headless for automation
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        viewport: { width: 1280, height: 900 }
    });

    const page = await context.newPage();

    try {
        // Login
        console.log('Logging in...');
        await page.goto(`${CONFIG.baseUrl}/my-account/`, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.waitForTimeout(1500);
        try { await page.click('button:has-text("Ok")', { timeout: 1500 }); } catch (e) {}
        await page.fill('#username', CONFIG.username);
        await page.fill('#password', CONFIG.password);
        await page.click('button[name="login"]');
        await page.waitForTimeout(3000);
        console.log('Logged in!\n');

        // Test product with default currency (SEK)
        const url = 'https://products.snigel.se/product/badge-holder/';
        console.log(`Loading: ${url}`);
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.waitForTimeout(3000);

        // Get page structure info
        const info = await page.evaluate(() => {
            const result = {
                pageText: '',
                rrpInText: null,
                summarySection: null,
                allPriceElements: []
            };

            // Check page text for RRP
            result.pageText = document.body.innerText.substring(0, 2000);
            const rrpMatch = result.pageText.match(/RRP[^€]*€[\d\s.,]+/gi);
            result.rrpInText = rrpMatch;

            // Check summary section
            const summary = document.querySelector('.summary.entry-summary');
            if (summary) {
                result.summarySection = {
                    exists: true,
                    innerHTML: summary.innerHTML.substring(0, 1000),
                    priceElements: []
                };
                const prices = summary.querySelectorAll('.woocommerce-Price-amount');
                prices.forEach(p => {
                    result.summarySection.priceElements.push({
                        text: p.textContent.trim(),
                        parent: p.parentElement?.className || 'no-parent'
                    });
                });
            }

            // All price elements on page
            const allPrices = document.querySelectorAll('.woocommerce-Price-amount');
            allPrices.forEach((p, i) => {
                const isInRelated = p.closest('.related, .up-sells, .cross-sells') !== null;
                result.allPriceElements.push({
                    index: i,
                    text: p.textContent.trim(),
                    inRelated: isInRelated,
                    parentClass: p.parentElement?.className || 'none'
                });
            });

            return result;
        });

        console.log('\n=== PAGE ANALYSIS ===\n');
        console.log('RRP in page text:', info.rrpInText);
        console.log('\nSummary section:', info.summarySection ? 'EXISTS' : 'NOT FOUND');
        if (info.summarySection) {
            console.log('Summary prices:', info.summarySection.priceElements);
        }
        console.log('\nAll price elements:');
        info.allPriceElements.forEach(p => {
            console.log(`  [${p.index}] ${p.text} ${p.inRelated ? '(IN RELATED)' : ''} parent: ${p.parentClass}`);
        });

        console.log('\n\nDebug complete.');

    } catch (error) {
        console.error('Error:', error.message);
    } finally {
        await browser.close();
    }
}

debug();
