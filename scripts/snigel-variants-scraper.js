/**
 * Snigel B2B Portal - Variants & Category Scraper (v2 - Enhanced)
 *
 * Improvements:
 * - Block unnecessary resources (images, fonts, css) for faster loading
 * - Use waitForSelector instead of just timeout
 * - Randomized delays to look more human-like
 * - Better error recovery
 *
 * Output: snigel-data/products-with-variants.json
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const config = {
    baseUrl: 'https://products.snigel.se',
    username: 'Raven Weapon AG',
    password: 'wVREVbRZfqT&Fba@f(^2UKOw',
    outputDir: path.join(__dirname, 'snigel-data'),
    requestDelay: 3000,
    pageTimeout: 60000,
    maxRetries: 4,
};

if (!fs.existsSync(config.outputDir)) {
    fs.mkdirSync(config.outputDir, { recursive: true });
}

// Randomized delay to look more human-like
function delay(ms) {
    const randomMs = ms + Math.floor(Math.random() * 1000);
    return new Promise(resolve => setTimeout(resolve, randomMs));
}

async function main() {
    console.log('\n========================================================');
    console.log('   SNIGEL B2B - VARIANTS & CATEGORY SCRAPER (v2)');
    console.log('========================================================\n');

    // Load existing products with descriptions
    const productsPath = path.join(config.outputDir, 'products-with-descriptions.json');
    if (!fs.existsSync(productsPath)) {
        const basicPath = path.join(config.outputDir, 'products.json');
        if (!fs.existsSync(basicPath)) {
            console.error('Error: No products.json found. Run the main scraper first.');
            process.exit(1);
        }
    }

    const inputFile = fs.existsSync(productsPath) ? productsPath : path.join(config.outputDir, 'products.json');
    const products = JSON.parse(fs.readFileSync(inputFile, 'utf8'));
    console.log(`Loaded ${products.length} products from ${path.basename(inputFile)}\n`);

    // Check for existing progress
    const outputPath = path.join(config.outputDir, 'products-with-variants.json');
    let processedSlugs = new Set();
    let outputProducts = [];

    if (fs.existsSync(outputPath)) {
        outputProducts = JSON.parse(fs.readFileSync(outputPath, 'utf8'));
        processedSlugs = new Set(outputProducts.map(p => p.slug));
        console.log(`Resuming: ${processedSlugs.size} products already processed\n`);
    }

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        viewport: { width: 1920, height: 1080 }
    });

    // Block unnecessary resources to speed up loading
    await context.route('**/*', (route) => {
        const resourceType = route.request().resourceType();
        // Block images, fonts, media - we only need HTML content
        if (['image', 'font', 'media'].includes(resourceType)) {
            route.abort();
        } else {
            route.continue();
        }
    });

    const page = await context.newPage();
    page.setDefaultTimeout(config.pageTimeout);

    try {
        // Step 1: Login
        console.log('Step 1: Logging in...');
        await page.goto(`${config.baseUrl}/my-account/`, { waitUntil: 'domcontentloaded' });
        await delay(2000);

        // Dismiss cookie popup
        try {
            await page.click('button:has-text("Ok")', { timeout: 3000 });
        } catch (e) {}

        await page.fill('#username', config.username);
        await page.fill('#password', config.password);
        await page.click('button[name="login"]');

        // Wait for login to complete
        await page.waitForURL('**/my-account/**', { timeout: 30000 });
        await delay(2000);
        console.log('  Login successful!\n');

        // Step 2: Process each product
        console.log('Step 2: Scraping variants & categories...\n');

        let processed = 0;
        let errors = 0;
        let withVariants = 0;
        let simpleProducts = 0;
        const toProcess = products.filter(p => !processedSlugs.has(p.slug));

        console.log(`  Products to process: ${toProcess.length}\n`);

        for (const product of toProcess) {
            processed++;
            const progress = `[${processed}/${toProcess.length}]`;

            try {
                process.stdout.write(`${progress} ${product.name.substring(0, 45).padEnd(45)}  `);

                // Retry logic with exponential backoff
                let loaded = false;
                let lastError = null;

                for (let attempt = 1; attempt <= config.maxRetries && !loaded; attempt++) {
                    try {
                        // Navigate with shorter initial timeout
                        await page.goto(product.url, {
                            waitUntil: 'commit',  // Faster - just wait for first response
                            timeout: 30000
                        });

                        // Then wait for the product meta section to appear
                        await page.waitForSelector('.product_meta', { timeout: 20000 });
                        loaded = true;

                    } catch (navError) {
                        lastError = navError;
                        if (attempt < config.maxRetries) {
                            process.stdout.write(`(retry ${attempt}) `);
                            // Exponential backoff: 3s, 6s, 12s
                            await delay(3000 * attempt);
                        }
                    }
                }

                if (!loaded) {
                    throw lastError || new Error('Failed to load page');
                }

                // Small delay after page load
                await delay(1000);

                // Extract variant and category data from product page
                const pageData = await page.evaluate(() => {
                    const data = {
                        hasColorVariants: false,
                        colorOptions: [],
                        category: null,
                        colour: null,
                        galleryImages: []
                    };

                    // Check for color/variant dropdown
                    const variantSelect = document.querySelector('table.variations select, .variations select, select[name*="attribute"]');
                    if (variantSelect) {
                        const options = variantSelect.querySelectorAll('option');
                        options.forEach(opt => {
                            if (opt.value && opt.value !== '' && opt.value.toLowerCase() !== 'choose an option') {
                                data.colorOptions.push({
                                    name: opt.textContent.trim(),
                                    value: opt.value
                                });
                            }
                        });
                        if (data.colorOptions.length > 0) {
                            data.hasColorVariants = true;
                        }
                    }

                    // Get Category and Colour from product meta section
                    const metaTable = document.querySelector('.product_meta');
                    if (metaTable) {
                        const metaText = metaTable.innerText;

                        const catMatch = metaText.match(/Category[:\s\t]+([^\n]+)/i);
                        if (catMatch) {
                            data.category = catMatch[1].trim();
                        }

                        const colourMatch = metaText.match(/Colou?r[:\s\t]+([^\n]+)/i);
                        if (colourMatch) {
                            data.colour = colourMatch[1].trim();
                        }
                    }

                    // Alternative: check for category link
                    if (!data.category) {
                        const categoryLink = document.querySelector('.product_meta a[href*="product-category"]');
                        if (categoryLink) {
                            data.category = categoryLink.innerText.trim();
                        }
                    }

                    // Get all gallery images (from data attributes, not actual loading)
                    const galleryImages = document.querySelectorAll('.woocommerce-product-gallery__image');
                    galleryImages.forEach(div => {
                        const img = div.querySelector('img');
                        if (img) {
                            const src = img.getAttribute('data-large_image') || img.getAttribute('data-src') || img.getAttribute('src');
                            if (src && !src.includes('placeholder') && !data.galleryImages.includes(src)) {
                                data.galleryImages.push(src);
                            }
                        }
                    });

                    return data;
                });

                // If product has color variants, get image for each color (skip this to speed up)
                // Just record the color options without fetching individual images
                if (pageData.hasColorVariants && pageData.colorOptions.length > 0) {
                    withVariants++;
                } else {
                    simpleProducts++;
                }

                // Merge with existing product data
                const enrichedProduct = {
                    ...product,
                    hasColorVariants: pageData.hasColorVariants,
                    colorOptions: pageData.colorOptions,
                    category: pageData.category || product.categories?.[0] || null,
                    colour: pageData.colour || null,
                    galleryImages: pageData.galleryImages,
                };

                outputProducts.push(enrichedProduct);

                // Log result
                const type = pageData.hasColorVariants ? `VARIANT(${pageData.colorOptions.length})` : 'SIMPLE';
                const cat = pageData.category ? pageData.category.substring(0, 20) : 'NO CAT';
                console.log(`${type.padEnd(12)} | ${cat}`);

                // Save progress every 5 products
                if (processed % 5 === 0) {
                    fs.writeFileSync(outputPath, JSON.stringify(outputProducts, null, 2));
                }

                // Delay between requests
                await delay(config.requestDelay);

            } catch (error) {
                errors++;
                console.log(`ERROR: ${error.message.substring(0, 50)}`);

                // Add product without enrichment but mark as error
                outputProducts.push({
                    ...product,
                    hasColorVariants: false,
                    colorOptions: [],
                    category: product.categories?.[0] || null,
                    colour: null,
                    galleryImages: [],
                    scrapeError: error.message
                });

                // Save after error
                fs.writeFileSync(outputPath, JSON.stringify(outputProducts, null, 2));

                // Longer delay after error
                await delay(5000);
            }
        }

        // Final save
        fs.writeFileSync(outputPath, JSON.stringify(outputProducts, null, 2));

        // Stats
        console.log('\n========================================================');
        console.log('                    COMPLETE');
        console.log('========================================================');
        console.log(`  Total products: ${outputProducts.length}`);
        console.log(`  With color variants: ${withVariants}`);
        console.log(`  Simple products: ${simpleProducts}`);
        console.log(`  With category: ${outputProducts.filter(p => p.category).length}`);
        console.log(`  With colour: ${outputProducts.filter(p => p.colour).length}`);
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
