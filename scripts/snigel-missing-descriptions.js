/**
 * Snigel B2B - Scrape Missing Descriptions
 * Targets only the 46 products without descriptions in Shopware
 *
 * Usage: node snigel-missing-descriptions.js
 * Output: snigel-data/missing-descriptions.json
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const config = {
    baseUrl: 'https://products.snigel.se',
    username: 'Raven Weapon AG',
    password: 'wVREVbRZfqT&Fba@f(^2UKOw',
    outputDir: path.join(__dirname, 'snigel-data'),
    requestDelay: 3000,      // 3 seconds between requests
    pageTimeout: 60000,      // 60 second timeout
    retryDelay: 10000,       // 10 seconds wait on error
    maxRetries: 2,           // Retry failed products twice
};

// 11 Products that failed in previous run (retry)
const missingProducts = [
    { sku: '29-01754A09-000', name: '30L Waterproof mission backpack 1.0' },
    { sku: 'SN-40l-velcro-and-webbing-panel-10', name: '40L Velcro and webbing panel -10' },
    { sku: '40-00102-01-000', name: '50 mm buckle adapter set' },
    { sku: '28-01350-', name: '55L Duffel bag -17' },
    { sku: '28-01291-', name: '6L Funny pack' },
    { sku: '28-01350', name: '90L Duffel bag 1.0' },
    { sku: 'SN-belt-closure-pack-5-11', name: 'Belt closure pack (5) -11' },
    { sku: 'SN-tactical-coverall-10jk', name: 'Tactical coverall 09F Briefs' },
    { sku: 'SN-tactical-coverall-digi-09f', name: 'Tactical coverall, Digi -09F' },
    { sku: '21-01078-', name: 'Virve radio pouch -10' },
    { sku: 'SN-weapon-hook-10', name: 'Weapon hook -10' },
];

if (!fs.existsSync(config.outputDir)) {
    fs.mkdirSync(config.outputDir, { recursive: true });
}

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function log(msg) {
    console.log(`[${new Date().toISOString().substr(11, 8)}] ${msg}`);
}

// Convert product name to URL slug
function nameToSlug(name) {
    return name
        .toLowerCase()
        .replace(/[,\.]/g, '')
        .replace(/\s+/g, '-')
        .replace(/[^a-z0-9-]/g, '')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}

async function searchProduct(page, productName) {
    const searchUrl = `${config.baseUrl}/?s=${encodeURIComponent(productName)}&post_type=product`;
    await page.goto(searchUrl, { timeout: config.pageTimeout, waitUntil: 'domcontentloaded' });
    await delay(1000);

    const productLink = await page.$eval(
        '.products a.woocommerce-LoopProduct-link, .tmb a[href*="/product/"]',
        el => el.href
    ).catch(() => null);

    return productLink;
}

async function scrapeDescription(page, url) {
    await page.goto(url, { timeout: config.pageTimeout, waitUntil: 'domcontentloaded' });
    await delay(config.requestDelay);

    const pageData = await page.evaluate(() => {
        const data = { title: '', description: '', description_html: '' };

        const titleEl = document.querySelector('h1.product_title, h1');
        if (titleEl) data.title = titleEl.innerText.trim();

        // Try short description first
        const descContainer = document.querySelector('.woocommerce-product-details__short-description');
        if (descContainer) {
            data.description_html = descContainer.innerHTML.trim();
            data.description = descContainer.innerText.trim();
        }

        // If no short description, try description tab
        if (!data.description) {
            const tabDesc = document.querySelector('#tab-description, .woocommerce-Tabs-panel--description');
            if (tabDesc) {
                data.description_html = tabDesc.innerHTML.trim();
                data.description = tabDesc.innerText.trim();
            }
        }

        return data;
    });

    return pageData;
}

async function main() {
    console.log('\n========================================');
    console.log('   SNIGEL MISSING DESCRIPTIONS SCRAPER');
    console.log('========================================\n');
    log(`Products to scrape: ${missingProducts.length}`);

    const browser = await chromium.launch({
        headless: true,
        args: ['--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage']
    });

    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        viewport: { width: 1280, height: 720 }
    });

    const page = await context.newPage();
    page.setDefaultTimeout(config.pageTimeout);

    // Block images for speed
    await page.route('**/*', (route) => {
        const resourceType = route.request().resourceType();
        if (['image', 'font', 'media'].includes(resourceType)) {
            route.abort();
        } else {
            route.continue();
        }
    });

    let results = [];
    let errors = [];
    let startIndex = 0;

    // Check for existing progress to resume
    const progressFile = path.join(config.outputDir, 'missing-descriptions-progress.json');
    if (fs.existsSync(progressFile)) {
        const progress = JSON.parse(fs.readFileSync(progressFile, 'utf8'));
        results = progress.results || [];
        errors = progress.errors || [];
        startIndex = results.length + errors.length;
        if (startIndex > 0) {
            log(`Resuming from product ${startIndex + 1} (${results.length} found, ${errors.length} failed)\n`);
        }
    }

    try {
        // Login
        log('Logging in to B2B portal...');
        await page.goto(`${config.baseUrl}/my-account/`);
        await delay(2000);
        try { await page.click('button:has-text("Ok")', { timeout: 3000 }); } catch (e) {}
        await page.fill('#username', config.username);
        await page.fill('#password', config.password);
        await page.click('button[name="login"]');
        await page.waitForLoadState('networkidle');
        await delay(2000);
        log('Login successful!\n');

        // Process each product with retry logic (skip already processed)
        for (let i = startIndex; i < missingProducts.length; i++) {
            const product = missingProducts[i];
            const progress = `[${i + 1}/${missingProducts.length}]`;

            log(`${progress} Searching: ${product.name}`);

            let success = false;
            let lastError = null;

            for (let retry = 0; retry <= config.maxRetries && !success; retry++) {
                if (retry > 0) {
                    log(`${progress}   Retry ${retry}/${config.maxRetries} after ${config.retryDelay/1000}s...`);
                    await delay(config.retryDelay);
                }

                try {
                    // Try direct slug first
                    const slug = nameToSlug(product.name);
                    let productUrl = `${config.baseUrl}/product/${slug}/`;

                    const response = await page.goto(productUrl, {
                        timeout: config.pageTimeout,
                        waitUntil: 'domcontentloaded'
                    });

                    // If 404, try search
                    if (response.status() === 404) {
                        log(`${progress}   Direct URL failed, searching...`);
                        productUrl = await searchProduct(page, product.name);
                        if (!productUrl) {
                            throw new Error('Product not found via search');
                        }
                    }

                    // Scrape description
                    const pageData = await scrapeDescription(page, productUrl);

                    if (pageData.description && pageData.description.length > 20) {
                        const preview = pageData.description.substring(0, 50).replace(/\n/g, ' ');
                        log(`${progress} OK: "${preview}..."`);
                        results.push({
                            sku: product.sku,
                            name: product.name,
                            url: productUrl,
                            description: pageData.description,
                            description_html: pageData.description_html,
                        });
                        success = true;
                    } else {
                        throw new Error('No description on page');
                    }

                } catch (error) {
                    lastError = error.message;
                    if (error.message.includes('Timeout')) {
                        log(`${progress}   Timeout - waiting before retry...`);
                    }
                }
            }

            if (!success) {
                log(`${progress} X Failed: ${lastError}`);
                errors.push({ sku: product.sku, name: product.name, error: lastError });
            }

            // Save progress every 5 products
            if ((i + 1) % 5 === 0) {
                const tempOutput = { results, errors, partial: true };
                fs.writeFileSync(path.join(config.outputDir, 'missing-descriptions-progress.json'), JSON.stringify(tempOutput, null, 2));
                log(`${progress} Progress saved`);
            }

            // Longer delay between products to avoid rate limiting
            await delay(config.requestDelay);
        }

        // Save final results
        const outputFile = path.join(config.outputDir, 'missing-descriptions.json');
        const output = {
            scrapedAt: new Date().toISOString(),
            total: missingProducts.length,
            found: results.length,
            notFound: errors.length,
            results,
            errors
        };

        fs.writeFileSync(outputFile, JSON.stringify(output, null, 2));

        console.log('\n========================================');
        console.log('   SCRAPING COMPLETE');
        console.log('========================================');
        log(`Found descriptions: ${results.length}`);
        log(`Not found: ${errors.length}`);
        log(`Output: ${outputFile}`);
        console.log('========================================\n');

        // Show summary of found descriptions
        if (results.length > 0) {
            console.log('Products with descriptions found:');
            results.forEach(r => console.log(`  - ${r.name}`));
        }

        if (errors.length > 0) {
            console.log('\nProducts without descriptions:');
            errors.forEach(e => console.log(`  - ${e.name}: ${e.error}`));
        }

    } catch (error) {
        log(`FATAL ERROR: ${error.message}`);
        console.error(error.stack);
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
