/**
 * Snigel B2B Portal - FAST Description & Category Scraper
 *
 * Optimizations:
 * - Parallel scraping with 4 browser contexts
 * - Shorter timeout (30s)
 * - Skip on first timeout (no retries)
 * - Faster delays between requests
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const config = {
    baseUrl: 'https://products.snigel.se',
    username: 'Raven Weapon AG',
    password: 'wVREVbRZfqT&Fba@f(^2UKOw',
    outputDir: path.join(__dirname, 'snigel-data'),
    // Fast settings
    parallelWorkers: 4,
    pageTimeout: 30000,
    requestDelay: 500,
};

if (!fs.existsSync(config.outputDir)) {
    fs.mkdirSync(config.outputDir, { recursive: true });
}

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function scrapeProduct(page, product) {
    try {
        await page.goto(product.url, {
            waitUntil: 'domcontentloaded',
            timeout: config.pageTimeout
        });
        await delay(config.requestDelay);

        const pageData = await page.evaluate(() => {
            const data = {
                description: '',
                description_html: '',
                categories: [],
                article_no: '',
                ean: '',
                weight: '',
                dimensions: ''
            };

            // Get description from WooCommerce short description container
            const descContainer = document.querySelector('.woocommerce-product-details__short-description');
            if (descContainer) {
                data.description_html = descContainer.innerHTML.trim();
                data.description = descContainer.innerText.trim();
            }

            // Get category from product meta
            const categoryLink = document.querySelector('.product_meta a[href*="product-category"]');
            if (categoryLink) {
                data.categories.push(categoryLink.innerText.trim());
            }

            // Get product meta
            const metaContainer = document.querySelector('.product_meta');
            if (metaContainer) {
                const metaText = metaContainer.innerText;

                const articleMatch = metaText.match(/([0-9]{2}-[0-9A-Z]+-[0-9A-Z-]+)/);
                if (articleMatch) data.article_no = articleMatch[1].trim();

                const eanMatch = metaText.match(/EAN[:\s\t]*([0-9]+)/i);
                if (eanMatch) data.ean = eanMatch[1].trim();

                const weightMatch = metaText.match(/Weight[:\s\t]*([0-9]+\s*g)/i);
                if (weightMatch) data.weight = weightMatch[1].trim();

                const dimMatch = metaText.match(/Dimensions[:\s\t]*([^\n]+)/i);
                if (dimMatch) data.dimensions = dimMatch[1].trim();
            }

            return data;
        });

        return {
            ...product,
            description: pageData.description || product.description,
            description_html: pageData.description_html || '',
            categories: pageData.categories.length > 0 ? pageData.categories : product.categories,
            article_no: pageData.article_no || product.article_no,
            ean: pageData.ean || product.ean,
            weight: pageData.weight || product.weight,
            dimensions: pageData.dimensions || product.dimensions,
            scraped: true
        };
    } catch (error) {
        return { ...product, scraped: false, error: error.message };
    }
}

async function worker(workerId, browser, products, results, progress) {
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    });
    const page = await context.newPage();
    page.setDefaultTimeout(config.pageTimeout);

    // Login
    await page.goto(`${config.baseUrl}/my-account/`);
    await delay(2000);
    try { await page.click('button:has-text("Ok")', { timeout: 2000 }); } catch (e) {}
    await page.fill('#username', config.username);
    await page.fill('#password', config.password);
    await page.click('button[name="login"]');
    await page.waitForLoadState('networkidle');
    await delay(2000);

    // Process assigned products
    for (const product of products) {
        const result = await scrapeProduct(page, product);
        results.push(result);
        progress.done++;

        const status = result.description ? 'OK' : 'NO DESC';
        const cat = result.categories?.[0] || 'NO CAT';
        console.log(`[W${workerId}] [${progress.done}/${progress.total}] ${product.name.substring(0, 40).padEnd(40)} ${status} | ${cat}`);
    }

    await context.close();
}

async function main() {
    console.log('\n========================================================');
    console.log('   SNIGEL B2B - FAST DESCRIPTION SCRAPER');
    console.log(`   Workers: ${config.parallelWorkers} | Timeout: ${config.pageTimeout/1000}s`);
    console.log('========================================================\n');

    // Load existing products
    const productsPath = path.join(config.outputDir, 'products.json');
    if (!fs.existsSync(productsPath)) {
        console.error('Error: products.json not found');
        process.exit(1);
    }

    let products = JSON.parse(fs.readFileSync(productsPath, 'utf8'));
    console.log(`Loaded ${products.length} products\n`);

    // Check for existing progress
    const outputPath = path.join(config.outputDir, 'products-with-descriptions.json');
    let existingResults = [];
    let processedSlugs = new Set();

    if (fs.existsSync(outputPath)) {
        existingResults = JSON.parse(fs.readFileSync(outputPath, 'utf8'));
        processedSlugs = new Set(existingResults.filter(p => p.scraped).map(p => p.slug));
        console.log(`Resuming: ${processedSlugs.size} already scraped\n`);
    }

    // Filter out already processed
    const toProcess = products.filter(p => !processedSlugs.has(p.slug));
    console.log(`Products to scrape: ${toProcess.length}\n`);

    if (toProcess.length === 0) {
        console.log('All products already scraped!');
        return;
    }

    const browser = await chromium.launch({ headless: true });
    const results = [...existingResults];
    const progress = { done: existingResults.length, total: products.length };

    try {
        // Split products among workers
        const chunks = [];
        const chunkSize = Math.ceil(toProcess.length / config.parallelWorkers);
        for (let i = 0; i < toProcess.length; i += chunkSize) {
            chunks.push(toProcess.slice(i, i + chunkSize));
        }

        console.log(`Starting ${chunks.length} parallel workers...\n`);

        // Run workers in parallel
        await Promise.all(
            chunks.map((chunk, i) => worker(i + 1, browser, chunk, results, progress))
        );

        // Save final results
        fs.writeFileSync(outputPath, JSON.stringify(results, null, 2));

        // Stats
        const withDesc = results.filter(p => p.description && p.description.length > 50).length;
        const withCat = results.filter(p => p.categories && p.categories.length > 0).length;
        const errors = results.filter(p => p.error).length;

        console.log('\n========================================================');
        console.log('                    COMPLETE');
        console.log('========================================================');
        console.log(`  Total products: ${results.length}`);
        console.log(`  With descriptions: ${withDesc}`);
        console.log(`  With categories: ${withCat}`);
        console.log(`  Errors/Timeouts: ${errors}`);
        console.log(`  Output: ${outputPath}`);
        console.log('========================================================\n');

    } catch (error) {
        console.error('Error:', error.message);
        // Save whatever we have
        fs.writeFileSync(outputPath, JSON.stringify(results, null, 2));
        console.log(`Saved ${results.length} products before error`);
    } finally {
        await browser.close();
    }
}

main();
