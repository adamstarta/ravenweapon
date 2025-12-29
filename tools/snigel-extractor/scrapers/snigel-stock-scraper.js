/**
 * Snigel B2B Stock Scraper
 * Scrapes stock levels for all product variants from B2B portal
 *
 * Usage: node snigel-stock-scraper.js
 * Output: snigel-stock-data/stock-YYYY-MM-DD.json
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const config = {
    baseUrl: 'https://products.snigel.se',
    username: 'Raven Weapon AG',
    password: 'wVREVbRZfqT&Fba@f(^2UKOw',
    outputDir: path.join(__dirname, 'snigel-stock-data'),
    currency: 'EUR'
};

// Create output directory
if (!fs.existsSync(config.outputDir)) {
    fs.mkdirSync(config.outputDir, { recursive: true });
}

const progressFile = path.join(config.outputDir, 'progress.json');

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// Random delay to avoid rate limiting (looks more human)
function randomDelay(min, max) {
    const ms = Math.floor(Math.random() * (max - min + 1)) + min;
    return new Promise(resolve => setTimeout(resolve, ms));
}

function log(msg) {
    console.log(`[${new Date().toISOString().substr(11, 8)}] ${msg}`);
}

function saveProgress(results, errors, currentIndex, productUrls) {
    fs.writeFileSync(progressFile, JSON.stringify({
        results,
        errors,
        currentIndex,
        productUrls,
        savedAt: new Date().toISOString()
    }, null, 2));
}

function loadProgress() {
    if (fs.existsSync(progressFile)) {
        return JSON.parse(fs.readFileSync(progressFile, 'utf8'));
    }
    return null;
}

function clearProgress() {
    if (fs.existsSync(progressFile)) {
        fs.unlinkSync(progressFile);
    }
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

    // Dismiss cookie popup if present
    try {
        await page.click('button:has-text("Ok")', { timeout: 3000 });
    } catch (e) {}

    // Fill login form
    await page.fill('#username', config.username);
    await page.fill('#password', config.password);
    await page.click('button[name="login"]');
    await page.waitForLoadState('networkidle');
    await delay(3000);

    // Verify login success
    const logoutLink = await page.$('a:has-text("LOG OUT")');
    if (!logoutLink) {
        throw new Error('Login failed - logout link not found');
    }

    log('Login successful!');
}

async function collectProductUrls(page) {
    log('Collecting product URLs...');

    await page.goto(`${config.baseUrl}/product-category/all/?currency=${config.currency}`);
    await page.waitForLoadState('networkidle');
    await delay(3000);

    // Dismiss cookie popup
    try {
        await page.click('button:has-text("Ok")', { timeout: 2000 });
    } catch (e) {}

    // Scroll to load all products (infinite scroll)
    let lastCount = 0;
    let stableCount = 0;

    while (stableCount < 5) {
        await page.evaluate(() => window.scrollBy(0, 1000));
        await delay(1500);

        const count = await page.$$eval('.tmb.tmb-woocommerce', items => items.length);

        if (count === lastCount) {
            stableCount++;
        } else {
            stableCount = 0;
            lastCount = count;
            process.stdout.write(`\r  Found ${count} products...`);
        }
    }
    console.log('');

    // Extract unique product URLs
    const urls = await page.evaluate(() => {
        const links = document.querySelectorAll('.tmb.tmb-woocommerce a[href*="/product/"]');
        const uniqueUrls = new Set();
        links.forEach(link => uniqueUrls.add(link.href));
        return Array.from(uniqueUrls);
    });

    log(`Collected ${urls.length} product URLs`);
    return urls;
}

async function scrapeProductStock(page, url, retryCount = 0) {
    const maxRetries = 2;

    try {
        await page.goto(url, { timeout: 20000, waitUntil: 'domcontentloaded' });
        await delay(800);  // Faster - no images to load
    } catch (error) {
        if (retryCount < maxRetries) {
            log(`  Retry ${retryCount + 1}/${maxRetries} for ${url.split('/product/')[1]}`);
            await delay(1500);
            return scrapeProductStock(page, url, retryCount + 1);
        }
        throw error;
    }

    // Get product name
    const name = await page.$eval('h1', el => el.textContent.trim()).catch(() => 'Unknown');
    const slug = url.split('/product/')[1]?.replace(/\/$/, '') || '';

    // Find all variant dropdowns (Color, Size, etc.)
    const dropdowns = await page.$$('select[id^="pa_"], select[name^="attribute_"]');

    const variants = [];

    if (dropdowns.length === 0) {
        // SIMPLE PRODUCT - no variants
        const stockText = await page.$eval('.stock', el => el.textContent).catch(() => null);
        const sku = await page.$eval('.sku', el => el.textContent.trim()).catch(() => '');
        const stockInfo = parseStock(stockText);

        variants.push({
            sku,
            options: {},
            ...stockInfo
        });
    } else if (dropdowns.length === 1) {
        // SINGLE DROPDOWN - iterate through options
        const dropdown = dropdowns[0];
        const attrId = await dropdown.getAttribute('id') || 'option';
        const attrName = attrId.replace('pa_', '');

        const options = await dropdown.$$eval('option[value]:not([value=""])', opts =>
            opts.map(o => ({ value: o.value, label: o.textContent.trim() }))
        );

        for (const option of options) {
            await dropdown.selectOption(option.value);
            await delay(600);  // Faster - no images

            const stockText = await page.$eval('.stock', el => el.textContent).catch(() => null);
            const sku = await page.$eval('.sku', el => el.textContent.trim()).catch(() => '');
            const stockInfo = parseStock(stockText);

            variants.push({
                sku,
                options: { [attrName]: option.label },
                ...stockInfo
            });
        }
    } else {
        // MULTIPLE DROPDOWNS - iterate through all combinations
        const allOptions = [];

        for (const dropdown of dropdowns) {
            const attrId = await dropdown.getAttribute('id') || 'option';
            const attrName = attrId.replace('pa_', '');
            const options = await dropdown.$$eval('option[value]:not([value=""])', opts =>
                opts.map(o => ({ value: o.value, label: o.textContent.trim() }))
            );
            allOptions.push({ dropdown, attrName, options });
        }

        // Generate all combinations
        const combinations = generateCombinations(allOptions.map(a => a.options));

        for (let i = 0; i < combinations.length; i++) {
            const combo = combinations[i];
            const optionsMap = {};

            // Select each dropdown value
            for (let j = 0; j < allOptions.length; j++) {
                const { dropdown, attrName } = allOptions[j];
                const option = combo[j];
                await dropdown.selectOption(option.value);
                optionsMap[attrName] = option.label;
            }

            await delay(600);  // Faster - no images

            const stockText = await page.$eval('.stock', el => el.textContent).catch(() => null);
            const sku = await page.$eval('.sku', el => el.textContent.trim()).catch(() => '');
            const stockInfo = parseStock(stockText);

            variants.push({
                sku,
                options: optionsMap,
                ...stockInfo
            });
        }
    }

    return {
        name,
        slug,
        url,
        variantCount: variants.length,
        variants,
        scrapedAt: new Date().toISOString()
    };
}

async function main() {
    console.log('\n========================================');
    console.log('   SNIGEL B2B STOCK SCRAPER');
    console.log('========================================\n');

    // SPEED OPTIMIZATIONS:
    // 1. Headless mode = 2-3x faster
    // 2. Block images/CSS/fonts = less bandwidth
    // 3. Smaller viewport = faster rendering
    const browser = await chromium.launch({
        headless: true,  // Much faster than headed mode
        args: [
            '--disable-gpu',
            '--disable-dev-shm-usage',
            '--disable-setuid-sandbox',
            '--no-sandbox',
            '--disable-accelerated-2d-canvas',
            '--no-first-run',
            '--no-zygote',
            '--disable-infobars',
            '--disable-extensions'
        ]
    });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        viewport: { width: 1280, height: 720 }  // Smaller = faster
    });
    const page = await context.newPage();
    page.setDefaultTimeout(30000);

    // Block images, fonts, and stylesheets to speed up loading
    await page.route('**/*', (route) => {
        const resourceType = route.request().resourceType();
        if (['image', 'font', 'stylesheet', 'media'].includes(resourceType)) {
            route.abort();
        } else {
            route.continue();
        }
    });

    let results = [];
    let errors = [];
    let productUrls = [];
    let startIndex = 0;

    try {
        // Check for saved progress
        const progress = loadProgress();
        if (progress) {
            log(`Found saved progress from ${progress.savedAt}`);
            log(`Resuming from product ${progress.currentIndex + 1}/${progress.productUrls.length}...`);
            results = progress.results;
            errors = progress.errors;
            productUrls = progress.productUrls;
            startIndex = progress.currentIndex + 1;
        }

        await login(page);

        // Only collect URLs if not resuming
        if (productUrls.length === 0) {
            productUrls = await collectProductUrls(page);
        }

        log(`\nScraping ${productUrls.length - startIndex} remaining products...\n`);

        for (let i = startIndex; i < productUrls.length; i++) {
            const url = productUrls[i];
            const progressStr = `[${i + 1}/${productUrls.length}]`;

            try {
                const product = await scrapeProductStock(page, url);
                results.push(product);

                const totalStock = product.variants.reduce((sum, v) => sum + (v.stock === 999 ? 0 : v.stock), 0);
                const stockDisplay = product.variants[0]?.status === 'no_info' ? 'N/A' : totalStock.toString();

                log(`${progressStr} ${product.name.substring(0, 35).padEnd(35)} | ${product.variantCount} var | Stock: ${stockDisplay}`);

            } catch (error) {
                log(`${progressStr} ERROR: ${url.split('/product/')[1]} - ${error.message}`);
                errors.push({ url, error: error.message });

                // Extra delay after error to recover from rate limiting
                log(`  Waiting 10s before next product (rate limit recovery)...`);
                await delay(10000);
            }

            // Save progress every 10 products
            if ((i + 1) % 10 === 0) {
                saveProgress(results, errors, i, productUrls);
                log(`  [Progress saved]`);
            }

            // Random delay between products to avoid rate limiting
            await randomDelay(1500, 3000);  // 1.5-3 seconds random
        }

        // Clear progress file on successful completion
        clearProgress();

        // Save final results
        const timestamp = new Date().toISOString().split('T')[0];
        const outputFile = path.join(config.outputDir, `stock-${timestamp}.json`);

        const output = {
            scrapedAt: new Date().toISOString(),
            totalProducts: results.length,
            totalVariants: results.reduce((sum, p) => sum + p.variants.length, 0),
            inStock: results.filter(p => p.variants.some(v => v.status === 'in_stock')).length,
            outOfStock: results.filter(p => p.variants.every(v => v.status === 'out_of_stock')).length,
            noStockInfo: results.filter(p => p.variants.every(v => v.status === 'no_info')).length,
            products: results,
            errors
        };

        fs.writeFileSync(outputFile, JSON.stringify(output, null, 2));

        // Summary
        console.log('\n========================================');
        console.log('   COMPLETE');
        console.log('========================================');
        log(`Products scraped: ${results.length}`);
        log(`Total variants: ${output.totalVariants}`);
        log(`In stock: ${output.inStock}`);
        log(`Out of stock: ${output.outOfStock}`);
        log(`No stock info: ${output.noStockInfo}`);
        log(`Errors: ${errors.length}`);
        log(`Output: ${outputFile}`);
        console.log('========================================\n');

    } catch (error) {
        log(`FATAL ERROR: ${error.message}`);
        console.error(error.stack);

        // Save progress on error
        if (results.length > 0) {
            saveProgress(results, errors, results.length - 1, productUrls);
            log('Progress saved. Run script again to resume.');
        }
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
