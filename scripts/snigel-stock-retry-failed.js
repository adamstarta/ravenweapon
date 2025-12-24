/**
 * Snigel Stock Scraper - RETRY FAILED PRODUCTS
 * Reads errors from the main output and retries them
 *
 * Usage: node snigel-stock-retry-failed.js
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const config = {
    baseUrl: 'https://products.snigel.se',
    username: 'Raven Weapon AG',
    password: 'wVREVbRZfqT&Fba@f(^2UKOw',
    outputDir: path.join(__dirname, 'snigel-stock-data'),
};

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function randomDelay(min, max) {
    const ms = Math.floor(Math.random() * (max - min + 1)) + min;
    return new Promise(resolve => setTimeout(resolve, ms));
}

function log(msg) {
    console.log(`[${new Date().toISOString().substr(11, 8)}] ${msg}`);
}

function parseStock(stockText) {
    if (!stockText) return { stock: 999, status: 'no_info' };
    const text = stockText.toLowerCase().trim();
    if (text.includes('out of stock')) {
        return { stock: 0, status: 'out_of_stock' };
    }
    const match = text.match(/(\d+)\s*in stock/i);
    if (match) {
        return {
            stock: parseInt(match[1]),
            status: 'in_stock',
            canBackorder: text.includes('backordered')
        };
    }
    return { stock: 999, status: 'no_info' };
}

function generateCombinations(arrays) {
    if (arrays.length === 0) return [[]];
    const [first, ...rest] = arrays;
    const restCombos = generateCombinations(rest);
    const result = [];
    for (const item of first) {
        for (const combo of restCombos) {
            result.push([item, ...combo]);
        }
    }
    return result;
}

async function login(page) {
    log('Logging in to B2B portal...');
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
    log('Login successful!');
}

async function scrapeProductStock(page, url, retryCount = 0) {
    const maxRetries = 3;  // More retries for failed products

    try {
        await page.goto(url, { timeout: 45000, waitUntil: 'domcontentloaded' });  // Longer timeout
        await delay(2000);  // Longer delay
    } catch (error) {
        if (retryCount < maxRetries) {
            log(`  Retry ${retryCount + 1}/${maxRetries} for ${url.split('/product/')[1]}`);
            await delay(5000);  // Longer wait between retries
            return scrapeProductStock(page, url, retryCount + 1);
        }
        throw error;
    }

    const name = await page.$eval('h1', el => el.textContent.trim()).catch(() => 'Unknown');
    const slug = url.split('/product/')[1]?.replace(/\/$/, '') || '';
    const dropdowns = await page.$$('select[id^="pa_"], select[name^="attribute_"]');
    const variants = [];

    if (dropdowns.length === 0) {
        const stockText = await page.$eval('.stock', el => el.textContent).catch(() => null);
        const sku = await page.$eval('.sku', el => el.textContent.trim()).catch(() => '');
        const stockInfo = parseStock(stockText);
        variants.push({ sku, options: {}, ...stockInfo });
    } else if (dropdowns.length === 1) {
        const dropdown = dropdowns[0];
        const attrId = await dropdown.getAttribute('id') || 'option';
        const attrName = attrId.replace('pa_', '');
        const options = await dropdown.$$eval('option[value]:not([value=""])', opts =>
            opts.map(o => ({ value: o.value, label: o.textContent.trim() }))
        );

        for (const option of options) {
            await dropdown.selectOption(option.value);
            await delay(1000);
            const stockText = await page.$eval('.stock', el => el.textContent).catch(() => null);
            const sku = await page.$eval('.sku', el => el.textContent.trim()).catch(() => '');
            const stockInfo = parseStock(stockText);
            variants.push({ sku, options: { [attrName]: option.label }, ...stockInfo });
        }
    } else {
        const allOptions = [];
        for (const dropdown of dropdowns) {
            const attrId = await dropdown.getAttribute('id') || 'option';
            const attrName = attrId.replace('pa_', '');
            const options = await dropdown.$$eval('option[value]:not([value=""])', opts =>
                opts.map(o => ({ value: o.value, label: o.textContent.trim() }))
            );
            allOptions.push({ dropdown, attrName, options });
        }
        const combinations = generateCombinations(allOptions.map(a => a.options));

        for (const combo of combinations) {
            const optionsMap = {};
            for (let j = 0; j < allOptions.length; j++) {
                const { dropdown, attrName } = allOptions[j];
                const option = combo[j];
                await dropdown.selectOption(option.value);
                optionsMap[attrName] = option.label;
            }
            await delay(1000);
            const stockText = await page.$eval('.stock', el => el.textContent).catch(() => null);
            const sku = await page.$eval('.sku', el => el.textContent.trim()).catch(() => '');
            const stockInfo = parseStock(stockText);
            variants.push({ sku, options: optionsMap, ...stockInfo });
        }
    }

    return { name, slug, url, variantCount: variants.length, variants, scrapedAt: new Date().toISOString() };
}

async function main() {
    console.log('\n========================================');
    console.log('   RETRY FAILED PRODUCTS');
    console.log('========================================\n');

    // Find the latest stock file
    const files = fs.readdirSync(config.outputDir).filter(f => f.startsWith('stock-') && f.endsWith('.json'));
    if (files.length === 0) {
        log('ERROR: No stock file found! Run snigel-stock-scraper.js first.');
        return;
    }

    const latestFile = files.sort().pop();
    const stockFilePath = path.join(config.outputDir, latestFile);
    log(`Reading from: ${latestFile}`);

    const stockData = JSON.parse(fs.readFileSync(stockFilePath, 'utf8'));
    const failedUrls = stockData.errors.map(e => e.url);

    if (failedUrls.length === 0) {
        log('No failed products to retry!');
        return;
    }

    log(`Found ${failedUrls.length} failed products to retry\n`);

    const browser = await chromium.launch({
        headless: true,
        args: ['--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage']
    });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        viewport: { width: 1280, height: 720 }
    });
    const page = await context.newPage();
    page.setDefaultTimeout(45000);

    // Block images for speed
    await page.route('**/*', (route) => {
        const resourceType = route.request().resourceType();
        if (['image', 'font', 'stylesheet', 'media'].includes(resourceType)) {
            route.abort();
        } else {
            route.continue();
        }
    });

    const recovered = [];
    const stillFailed = [];

    try {
        await login(page);

        for (let i = 0; i < failedUrls.length; i++) {
            const url = failedUrls[i];
            const progressStr = `[${i + 1}/${failedUrls.length}]`;

            try {
                log(`${progressStr} Retrying: ${url.split('/product/')[1]}`);
                const product = await scrapeProductStock(page, url);
                recovered.push(product);

                const totalStock = product.variants.reduce((sum, v) => sum + (v.stock === 999 ? 0 : v.stock), 0);
                log(`${progressStr} SUCCESS: ${product.name.substring(0, 35)} | Stock: ${totalStock}`);

            } catch (error) {
                log(`${progressStr} STILL FAILED: ${url.split('/product/')[1]}`);
                stillFailed.push({ url, error: error.message });
            }

            // Longer delay between retries
            await randomDelay(3000, 5000);
        }

        // Update the stock file
        if (recovered.length > 0) {
            stockData.products.push(...recovered);
            stockData.totalProducts = stockData.products.length;
            stockData.totalVariants = stockData.products.reduce((sum, p) => sum + p.variants.length, 0);
        }
        stockData.errors = stillFailed;
        stockData.retryAt = new Date().toISOString();

        fs.writeFileSync(stockFilePath, JSON.stringify(stockData, null, 2));

        console.log('\n========================================');
        console.log('   RETRY COMPLETE');
        console.log('========================================');
        log(`Recovered: ${recovered.length} products`);
        log(`Still failed: ${stillFailed.length} products`);
        log(`Updated: ${latestFile}`);
        console.log('========================================\n');

    } catch (error) {
        log(`FATAL ERROR: ${error.message}`);
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
