/**
 * Snigel B2B Portal - Description & Category Scraper
 *
 * This script visits each product page to extract:
 * - Full product description (HTML)
 * - Category assignment
 * - Article number, EAN, Weight, Dimensions
 *
 * Output: snigel-data/products-with-descriptions.json
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const config = {
    baseUrl: 'https://products.snigel.se',
    username: 'Raven Weapon AG',
    password: 'wVREVbRZfqT&Fba@f(^2UKOw',
    outputDir: path.join(__dirname, 'snigel-data'),
    // Delay between product page requests to avoid rate limiting
    requestDelay: 2000,
    // Longer timeouts for slow connection
    pageTimeout: 60000,
    maxRetries: 3,
};

if (!fs.existsSync(config.outputDir)) {
    fs.mkdirSync(config.outputDir, { recursive: true });
}

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function main() {
    console.log('\n========================================================');
    console.log('   SNIGEL B2B - DESCRIPTION & CATEGORY SCRAPER');
    console.log('========================================================\n');

    // Load existing products
    const productsPath = path.join(config.outputDir, 'products.json');
    if (!fs.existsSync(productsPath)) {
        console.error('Error: products.json not found. Run the main scraper first.');
        process.exit(1);
    }

    const products = JSON.parse(fs.readFileSync(productsPath, 'utf8'));
    console.log(`Loaded ${products.length} products from products.json\n`);

    // Check for existing progress
    const outputPath = path.join(config.outputDir, 'products-with-descriptions.json');
    let processedSlugs = new Set();
    let outputProducts = [];

    if (fs.existsSync(outputPath)) {
        outputProducts = JSON.parse(fs.readFileSync(outputPath, 'utf8'));
        processedSlugs = new Set(outputProducts.map(p => p.slug));
        console.log(`Resuming: ${processedSlugs.size} products already processed\n`);
    }

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        viewport: { width: 1920, height: 1080 }
    });
    const page = await context.newPage();
    page.setDefaultTimeout(config.pageTimeout);

    try {
        // Step 1: Login
        console.log('Step 1: Logging in...');
        await page.goto(`${config.baseUrl}/my-account/`);
        await page.waitForLoadState('networkidle');
        await delay(2000);

        // Dismiss cookie popup
        try {
            await page.click('button:has-text("Ok")', { timeout: 3000 });
        } catch (e) {}

        await page.fill('#username', config.username);
        await page.fill('#password', config.password);
        await page.click('button[name="login"]');
        await page.waitForLoadState('networkidle');
        await delay(3000);
        console.log('  Login successful!\n');

        // Step 2: Process each product
        console.log('Step 2: Scraping product descriptions...\n');

        let processed = 0;
        let errors = 0;
        const total = products.length;
        const toProcess = products.filter(p => !processedSlugs.has(p.slug));

        console.log(`  Products to process: ${toProcess.length}\n`);

        for (const product of toProcess) {
            processed++;
            const progress = `[${processed}/${toProcess.length}]`;

            try {
                process.stdout.write(`${progress} ${product.name.substring(0, 50).padEnd(50)}  `);

                // Retry logic for slow connections
                let loaded = false;
                for (let attempt = 1; attempt <= config.maxRetries && !loaded; attempt++) {
                    try {
                        await page.goto(product.url, {
                            waitUntil: 'domcontentloaded',
                            timeout: config.pageTimeout
                        });
                        loaded = true;
                    } catch (navError) {
                        if (attempt < config.maxRetries) {
                            process.stdout.write(`(retry ${attempt}) `);
                            await delay(3000);
                        } else {
                            throw navError;
                        }
                    }
                }
                await delay(config.requestDelay);

                // Extract data from product page
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

                    // Get product meta (Article no, EAN, Weight, Dimensions)
                    const metaContainer = document.querySelector('.product_meta');
                    if (metaContainer) {
                        const metaText = metaContainer.innerText;

                        // Article no - format like 29-01161C09-000
                        const articleMatch = metaText.match(/([0-9]{2}-[0-9A-Z]+-[0-9A-Z-]+)/);
                        if (articleMatch) {
                            data.article_no = articleMatch[1].trim();
                        }

                        // EAN
                        const eanMatch = metaText.match(/EAN[:\s\t]*([0-9]+)/i);
                        if (eanMatch) {
                            data.ean = eanMatch[1].trim();
                        }

                        // Weight
                        const weightMatch = metaText.match(/Weight[:\s\t]*([0-9]+\s*g)/i);
                        if (weightMatch) {
                            data.weight = weightMatch[1].trim();
                        }

                        // Dimensions
                        const dimMatch = metaText.match(/Dimensions[:\s\t]*([^\n]+)/i);
                        if (dimMatch) {
                            data.dimensions = dimMatch[1].trim();
                        }
                    }

                    return data;
                });

                // Merge with existing product data
                const enrichedProduct = {
                    ...product,
                    description: pageData.description || product.description,
                    description_html: pageData.description_html || '',
                    categories: pageData.categories.length > 0 ? pageData.categories : product.categories,
                    article_no: pageData.article_no || product.article_no,
                    ean: pageData.ean || product.ean,
                    weight: pageData.weight || product.weight,
                    dimensions: pageData.dimensions || product.dimensions,
                };

                outputProducts.push(enrichedProduct);

                const hasDesc = enrichedProduct.description ? 'OK' : 'NO DESC';
                const hasCat = enrichedProduct.categories.length > 0 ? enrichedProduct.categories[0] : 'NO CAT';
                console.log(`${hasDesc} | ${hasCat}`);

                // Save progress every 10 products
                if (processed % 10 === 0) {
                    fs.writeFileSync(outputPath, JSON.stringify(outputProducts, null, 2));
                }

            } catch (error) {
                errors++;
                console.log(`ERROR: ${error.message}`);
                // Add product without enrichment
                outputProducts.push(product);
            }
        }

        // Final save
        fs.writeFileSync(outputPath, JSON.stringify(outputProducts, null, 2));

        // Stats
        const withDesc = outputProducts.filter(p => p.description && p.description.length > 50).length;
        const withCat = outputProducts.filter(p => p.categories && p.categories.length > 0).length;

        console.log('\n========================================================');
        console.log('                    COMPLETE');
        console.log('========================================================');
        console.log(`  Total products: ${outputProducts.length}`);
        console.log(`  With descriptions: ${withDesc}`);
        console.log(`  With categories: ${withCat}`);
        console.log(`  Errors: ${errors}`);
        console.log(`  Output: ${outputPath}`);
        console.log('========================================================\n');

    } catch (error) {
        console.error('Error:', error.message);
        console.error(error.stack);
        // Save whatever we have
        if (outputProducts.length > 0) {
            fs.writeFileSync(outputPath, JSON.stringify(outputProducts, null, 2));
            console.log(`Saved ${outputProducts.length} products before error`);
        }
    } finally {
        await browser.close();
    }
}

main();
