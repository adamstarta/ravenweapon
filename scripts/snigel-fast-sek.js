/**
 * Super Fast SEK Price Scraper
 * NO LOGIN - gets public SEK prices
 * Blocks images/CSS for speed
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const https = require('https');

// SEK to CHF rate (from xe.com Dec 2025)
const SEK_TO_CHF = 0.0855;

function httpRequest(url) {
    return new Promise((resolve, reject) => {
        https.get(url, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try { resolve(JSON.parse(data)); }
                catch (e) { resolve([]); }
            });
        }).on('error', reject);
    });
}

async function main() {
    console.log('\n=== FAST SEK PRICE SCRAPER ===');
    console.log(`Formula: SEK × ${SEK_TO_CHF} = CHF\n`);

    // Get product list from API
    console.log('Getting products from API...');
    const products = [];
    for (let page = 1; page <= 3; page++) {
        const data = await httpRequest(`https://products.snigel.se/wp-json/wc/store/v1/products?per_page=100&page=${page}`);
        if (Array.isArray(data)) {
            data.forEach(p => products.push({ name: p.name, url: p.permalink }));
            if (data.length < 100) break;
        }
    }
    console.log(`Found ${products.length} products\n`);

    // Launch browser with blocking
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();

    // Block EVERYTHING except HTML
    await context.route('**/*', (route) => {
        const type = route.request().resourceType();
        if (type === 'document' || type === 'script' || type === 'xhr' || type === 'fetch') {
            route.continue();
        } else {
            route.abort();
        }
    });

    const page = await context.newPage();
    const results = [];
    let success = 0, fail = 0;

    console.log('Scraping SEK prices (no login)...');

    for (let i = 0; i < products.length; i++) {
        const p = products[i];
        try {
            await page.goto(p.url, { waitUntil: 'commit', timeout: 10000 });
            await page.waitForSelector('.woocommerce-Price-amount', { timeout: 5000 }).catch(() => {});

            const sek = await page.evaluate(() => {
                const els = document.querySelectorAll('.woocommerce-Price-amount');
                for (const el of els) {
                    if (el.closest('.related, .up-sells, .cross-sells')) continue;
                    const text = el.textContent.trim();
                    if (text.includes('kr')) {
                        const clean = text.replace(/[kr\s]/gi, '').replace(',', '.');
                        const num = parseFloat(clean);
                        if (num > 0) return num;
                    }
                }
                return null;
            });

            if (sek) {
                results.push({ name: p.name, sek, chf: Math.round(sek * SEK_TO_CHF * 100) / 100 });
                success++;
            } else {
                fail++;
            }
        } catch (e) {
            fail++;
        }

        if ((i + 1) % 10 === 0) {
            process.stdout.write(`[${i + 1}/${products.length}] ✓${success} ✗${fail}\r`);
        }
    }

    await browser.close();

    console.log(`\n\nDone! ${success} success, ${fail} failed`);

    // Save results
    const outPath = path.join(__dirname, 'snigel-comparison', `sek-prices-${Date.now()}.json`);
    fs.writeFileSync(outPath, JSON.stringify(results, null, 2));
    console.log(`Saved to: ${outPath}`);

    // Show sample
    console.log('\nSample prices:');
    results.slice(0, 5).forEach(r => {
        console.log(`  ${r.name}: ${r.sek} kr = CHF ${r.chf}`);
    });
}

main();
