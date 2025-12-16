/**
 * Snigel B2B - Retry Failed Products
 *
 * Re-scrapes only products that had errors in the previous run
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const config = {
    baseUrl: 'https://products.snigel.se',
    username: 'Raven Weapon AG',
    password: 'wVREVbRZfqT&Fba@f(^2UKOw',
    outputDir: path.join(__dirname, 'snigel-data'),
    requestDelay: 5000,      // Longer delay for retries
    pageTimeout: 60000,
    maxRetries: 5,           // More retries
};

function delay(ms) {
    const randomMs = ms + Math.floor(Math.random() * 2000);
    return new Promise(resolve => setTimeout(resolve, randomMs));
}

async function main() {
    console.log('\n========================================================');
    console.log('   SNIGEL B2B - RETRY FAILED PRODUCTS');
    console.log('========================================================\n');

    const outputPath = path.join(config.outputDir, 'products-with-variants.json');

    if (!fs.existsSync(outputPath)) {
        console.error('Error: products-with-variants.json not found. Run main scraper first.');
        process.exit(1);
    }

    let products = JSON.parse(fs.readFileSync(outputPath, 'utf8'));
    const errorProducts = products.filter(p => p.scrapeError);

    console.log(`Total products: ${products.length}`);
    console.log(`Products with errors: ${errorProducts.length}\n`);

    if (errorProducts.length === 0) {
        console.log('No errors to retry!');
        process.exit(0);
    }

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();
    page.setDefaultTimeout(config.pageTimeout);

    try {
        // Login
        console.log('Logging in...');
        await page.goto(`${config.baseUrl}/my-account/`, { waitUntil: 'domcontentloaded' });
        await delay(2000);

        try {
            await page.click('button:has-text("Ok")', { timeout: 3000 });
        } catch (e) {}

        await page.fill('#username', config.username);
        await page.fill('#password', config.password);
        await page.click('button[name="login"]');
        await page.waitForURL('**/my-account/**', { timeout: 30000 });
        await delay(3000);
        console.log('Login successful!\n');

        let fixed = 0;
        let stillFailed = 0;

        for (let i = 0; i < errorProducts.length; i++) {
            const product = errorProducts[i];
            const progress = `[${i + 1}/${errorProducts.length}]`;

            process.stdout.write(`${progress} ${product.name.substring(0, 40).padEnd(40)}  `);

            let success = false;
            let pageData = null;

            for (let attempt = 1; attempt <= config.maxRetries && !success; attempt++) {
                try {
                    await page.goto(product.url, {
                        waitUntil: 'domcontentloaded',
                        timeout: 45000
                    });

                    // Wait for page content
                    await delay(3000);

                    pageData = await page.evaluate(() => {
                        const data = {
                            hasColorVariants: false,
                            colorOptions: [],
                            category: null,
                            colour: null,
                            galleryImages: []
                        };

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

                        const metaTable = document.querySelector('.product_meta');
                        if (metaTable) {
                            const metaText = metaTable.innerText;
                            const catMatch = metaText.match(/Category[:\s\t]+([^\n]+)/i);
                            if (catMatch) data.category = catMatch[1].trim();
                            const colourMatch = metaText.match(/Colou?r[:\s\t]+([^\n]+)/i);
                            if (colourMatch) data.colour = colourMatch[1].trim();
                        }

                        if (!data.category) {
                            const categoryLink = document.querySelector('.product_meta a[href*="product-category"]');
                            if (categoryLink) data.category = categoryLink.innerText.trim();
                        }

                        const galleryImages = document.querySelectorAll('.woocommerce-product-gallery__image img');
                        galleryImages.forEach(img => {
                            const src = img.getAttribute('data-large_image') || img.getAttribute('data-src') || img.src;
                            if (src && !src.includes('placeholder') && !data.galleryImages.includes(src)) {
                                data.galleryImages.push(src);
                            }
                        });

                        return data;
                    });

                    success = true;

                } catch (e) {
                    if (attempt < config.maxRetries) {
                        process.stdout.write(`(retry ${attempt}) `);
                        await delay(5000 * attempt);
                    }
                }
            }

            if (success && pageData) {
                // Update the product in the array
                const idx = products.findIndex(p => p.slug === product.slug);
                if (idx !== -1) {
                    products[idx] = {
                        ...products[idx],
                        hasColorVariants: pageData.hasColorVariants,
                        colorOptions: pageData.colorOptions,
                        category: pageData.category || products[idx].categories?.[0] || null,
                        colour: pageData.colour || null,
                        galleryImages: pageData.galleryImages,
                        scrapeError: undefined  // Remove error
                    };
                    delete products[idx].scrapeError;
                }
                fixed++;
                const type = pageData.hasColorVariants ? `VARIANT(${pageData.colorOptions.length})` : 'SIMPLE';
                console.log(`FIXED | ${type}`);
            } else {
                stillFailed++;
                console.log('STILL FAILED');
            }

            // Save progress
            fs.writeFileSync(outputPath, JSON.stringify(products, null, 2));
            await delay(config.requestDelay);
        }

        console.log('\n========================================================');
        console.log('                    RETRY COMPLETE');
        console.log('========================================================');
        console.log(`  Fixed: ${fixed}`);
        console.log(`  Still failed: ${stillFailed}`);
        console.log(`  Output: ${outputPath}`);
        console.log('========================================================\n');

    } catch (error) {
        console.error('Error:', error.message);
        fs.writeFileSync(outputPath, JSON.stringify(products, null, 2));
    } finally {
        await browser.close();
    }
}

main();
