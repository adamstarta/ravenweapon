/**
 * Snigel B2B Portal Scraper - Playwright Version
 *
 * Scrapes products from products.snigel.se B2B portal using Playwright
 * Handles JavaScript-rendered content, infinite scroll pagination, and image downloads
 *
 * Usage: node snigel-b2b-playwright.js
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const https = require('https');

// Configuration
const config = {
    baseUrl: 'https://products.snigel.se',
    username: 'Raven Weapon AG',
    password: 'wVREVbRZfqT&Fba@f(^2UKOw',
    outputDir: path.join(__dirname, 'snigel-b2b-data'),
    imagesDir: path.join(__dirname, 'snigel-b2b-data', 'images'),
    currency: 'EUR',
    delayBetweenRequests: 3000, // milliseconds
    pageTimeout: 45000, // 45 seconds
    maxRetries: 2,
};

// Create output directories
if (!fs.existsSync(config.outputDir)) {
    fs.mkdirSync(config.outputDir, { recursive: true });
    console.log(`Created output directory: ${config.outputDir}`);
}
if (!fs.existsSync(config.imagesDir)) {
    fs.mkdirSync(config.imagesDir, { recursive: true });
    console.log(`Created images directory: ${config.imagesDir}`);
}

/**
 * Download image from URL
 */
function downloadImage(url, savePath) {
    return new Promise((resolve, reject) => {
        if (fs.existsSync(savePath)) {
            resolve(true);
            return;
        }

        const file = fs.createWriteStream(savePath);
        https.get(url, (response) => {
            if (response.statusCode === 200) {
                response.pipe(file);
                file.on('finish', () => {
                    file.close();
                    resolve(true);
                });
            } else if (response.statusCode === 301 || response.statusCode === 302) {
                file.close();
                fs.unlinkSync(savePath);
                // Follow redirect
                downloadImage(response.headers.location, savePath).then(resolve).catch(reject);
            } else {
                file.close();
                if (fs.existsSync(savePath)) fs.unlinkSync(savePath);
                resolve(false);
            }
        }).on('error', (err) => {
            file.close();
            if (fs.existsSync(savePath)) fs.unlinkSync(savePath);
            resolve(false);
        });
    });
}

/**
 * Delay helper
 */
function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function main() {
    console.log('\n');
    console.log('========================================================');
    console.log('       SNIGEL B2B PORTAL SCRAPER (Playwright)');
    console.log('========================================================\n');

    const browser = await chromium.launch({
        headless: false,
        slowMo: 100
    });

    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    });

    const page = await context.newPage();
    page.setDefaultTimeout(config.pageTimeout);
    page.setDefaultNavigationTimeout(config.pageTimeout);

    try {
        // Step 1: Login
        console.log('Step 1: Logging in...');
        await page.goto(`${config.baseUrl}/my-account/`);
        await page.waitForLoadState('networkidle');

        // Fill login form
        await page.fill('#username', config.username);
        await page.fill('#password', config.password);
        await page.click('button[name="login"]');

        // Wait for login to complete
        await page.waitForLoadState('networkidle');
        await delay(2000);

        // Check if logged in
        const logoutLink = await page.$('a[href*="logout"]');
        if (logoutLink) {
            console.log('  Login successful!\n');
        } else {
            console.log('  Login may have failed, continuing anyway...\n');
        }

        // Step 2: Go to ALL PRODUCTS with EUR currency and collect URLs via infinite scroll
        console.log('Step 2: Collecting product URLs (infinite scroll)...');
        await page.goto(`${config.baseUrl}/product-category/all/?currency=EUR`);
        await page.waitForLoadState('networkidle');
        await delay(2000);

        // Keep scrolling until no new products are loaded
        let previousCount = 0;
        let currentCount = 0;
        let noChangeCount = 0;

        while (noChangeCount < 5) {
            previousCount = await page.$$eval('a[href*="/product/"]', links => links.length);
            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
            await delay(2000);
            currentCount = await page.$$eval('a[href*="/product/"]', links => links.length);

            if (currentCount === previousCount) {
                noChangeCount++;
            } else {
                noChangeCount = 0;
                process.stdout.write(`  Loaded ${currentCount} links...\r`);
            }
        }

        // Get all unique product URLs
        const allProductUrls = await page.$$eval(
            'a[href*="/product/"]',
            links => [...new Set(links.map(a => a.href).filter(url => url.includes('/product/') && !url.includes('add-to-cart')))]
        );

        console.log(`\n  Total unique products found: ${allProductUrls.length}\n`);

        // Step 3: Scrape each product
        console.log('Step 3: Scraping product details...');
        const products = [];

        for (let i = 0; i < allProductUrls.length; i++) {
            const productUrl = allProductUrls[i];
            const count = i + 1;
            const total = allProductUrls.length;

            process.stdout.write(`  [${count}/${total}] `);

            let retries = 0;
            let pageLoaded = false;

            while (retries < config.maxRetries && !pageLoaded) {
                try {
                    // Add currency parameter
                    const url = productUrl.includes('?')
                        ? `${productUrl}&currency=EUR`
                        : `${productUrl}?currency=EUR`;

                    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: config.pageTimeout });
                    await delay(1000);
                    pageLoaded = true;
                } catch (navErr) {
                    retries++;
                    if (retries < config.maxRetries) {
                        process.stdout.write(`(retry ${retries}) `);
                        await delay(3000);
                    }
                }
            }

            if (!pageLoaded) {
                console.log(`FAILED after ${config.maxRetries} retries`);
                continue;
            }

            try {

                const product = {
                    url: productUrl,
                    slug: productUrl.split('/product/')[1]?.replace(/\/$/, '') || '',
                };

                // Extract product name
                product.name = await page.$eval(
                    'h1.product_title, h1',
                    el => el.textContent.trim()
                ).catch(() => null);

                // Extract RRP price
                const rrpText = await page.$eval(
                    'ins .woocommerce-Price-amount, .price ins bdi, [class*="rrp"] bdi',
                    el => el.textContent.trim()
                ).catch(() => null);

                if (rrpText) {
                    const rrpMatch = rrpText.match(/([0-9,.]+)/);
                    if (rrpMatch) {
                        product.rrp_eur = parseFloat(rrpMatch[1].replace('.', '').replace(',', '.'));
                    }
                }

                // Extract B2B prices from table
                const priceRows = await page.$$eval('table tr', rows => {
                    return rows.map(row => {
                        const cells = row.querySelectorAll('td');
                        if (cells.length >= 2) {
                            const qty = cells[0].textContent.trim();
                            const priceEl = cells[1].querySelector('bdi, .woocommerce-Price-amount');
                            const price = priceEl ? priceEl.textContent.trim() : cells[1].textContent.trim();
                            return { qty, price };
                        }
                        return null;
                    }).filter(Boolean);
                }).catch(() => []);

                for (const row of priceRows) {
                    const priceMatch = row.price.match(/([0-9,.]+)/);
                    if (priceMatch) {
                        const price = parseFloat(priceMatch[1].replace('.', '').replace(',', '.'));
                        if (row.qty.includes('1 and more')) {
                            product.b2b_price_eur = price;
                        } else if (row.qty.includes('10 and more')) {
                            product.bulk_price_eur = price;
                        }
                    }
                }

                // If no B2B price from table, get from main price display
                if (!product.b2b_price_eur) {
                    const mainPrice = await page.$eval(
                        '.price bdi, .woocommerce-Price-amount bdi',
                        el => el.textContent.trim()
                    ).catch(() => null);

                    if (mainPrice) {
                        const match = mainPrice.match(/([0-9,.]+)/);
                        if (match) {
                            product.b2b_price_eur = parseFloat(match[1].replace('.', '').replace(',', '.'));
                        }
                    }
                }

                // Extract article number (format: XX-XXXXX-XX-XXX)
                const articleText = await page.evaluate(() => {
                    const text = document.body.innerText;
                    const artMatch = text.match(/(\d{2}-\d{5}-\d{2}-\d{3})/);
                    return artMatch ? artMatch[1] : null;
                });
                if (articleText) product.article_no = articleText;

                // Extract EAN
                const eanText = await page.evaluate(() => {
                    const el = document.body.innerText.match(/EAN[:\s]*(\d{8,14})/i);
                    return el ? el[1] : null;
                });
                if (eanText) product.ean = eanText;

                // Extract weight
                const weightText = await page.evaluate(() => {
                    const el = document.body.innerText.match(/Weight[:\s]*(\d+)\s*g/i);
                    return el ? parseInt(el[1]) : null;
                });
                if (weightText) product.weight_g = weightText;

                // Extract stock
                const stockText = await page.evaluate(() => {
                    const el = document.body.innerText.match(/(\d+)\s+in stock/i);
                    return el ? parseInt(el[1]) : null;
                });
                if (stockText) product.stock = stockText;

                // Extract category
                const category = await page.$eval(
                    'a[href*="product-category/all/"]',
                    el => el.textContent.trim()
                ).catch(() => null);
                if (category) product.category = category;

                // Extract colour
                const colour = await page.$eval(
                    'a[href*="/colour/"]',
                    el => el.textContent.trim()
                ).catch(() => null);
                if (colour) product.colour = colour;

                // Extract description
                product.description = await page.$eval(
                    '.woocommerce-product-details__short-description',
                    el => el.textContent.trim()
                ).catch(() => null);

                // Extract images from gallery links
                const images = await page.$$eval(
                    '.woocommerce-product-gallery__image a, a[href*="wp-content/uploads"][href$=".jpg"], a[href*="wp-content/uploads"][href$=".png"]',
                    links => links.map(a => a.href).filter(url => url.includes('wp-content/uploads'))
                ).catch(() => []);

                product.images = [...new Set(images)].slice(0, 5);

                // Download images
                product.local_images = [];
                for (let j = 0; j < product.images.length; j++) {
                    const imageUrl = product.images[j];
                    const ext = path.extname(new URL(imageUrl).pathname) || '.jpg';
                    const filename = `${product.article_no || product.slug}_${j}${ext}`.replace(/[<>:"/\\|?*]/g, '_');
                    const savePath = path.join(config.imagesDir, filename);

                    try {
                        const success = await downloadImage(imageUrl, savePath);
                        if (success) {
                            product.local_images.push(filename);
                        }
                    } catch (err) {
                        // Silently skip failed downloads
                    }
                }

                products.push(product);

                const name = (product.name || 'Unknown').substring(0, 35);
                const price = product.b2b_price_eur ? `€${product.b2b_price_eur.toFixed(2)}` : 'N/A';
                const articleNo = product.article_no || 'N/A';
                console.log(`${name.padEnd(35)} - ${price.padEnd(10)} - ${articleNo}`);

                // Save progress after each product
                const progressFile = path.join(config.outputDir, 'products-b2b-progress.json');
                fs.writeFileSync(progressFile, JSON.stringify(products, null, 2));

            } catch (err) {
                console.log(`FAILED: ${err.message}`);
            }

            await delay(config.delayBetweenRequests);
        }

        // Save products to JSON
        const jsonFile = path.join(config.outputDir, 'products-b2b.json');
        fs.writeFileSync(jsonFile, JSON.stringify(products, null, 2));

        console.log('\n');
        console.log('========================================================');
        console.log('                    SCRAPING COMPLETE');
        console.log('========================================================');
        console.log(`  Products scraped: ${products.length}`);
        console.log(`  Output file: ${jsonFile}`);
        console.log(`  Images directory: ${config.imagesDir}`);
        console.log('========================================================\n');

        // Summary statistics
        const withPrices = products.filter(p => p.b2b_price_eur).length;
        const withImages = products.filter(p => p.local_images && p.local_images.length > 0).length;
        const withArticleNo = products.filter(p => p.article_no).length;
        const withStock = products.filter(p => p.stock).length;

        console.log('Statistics:');
        console.log(`  - With B2B prices: ${withPrices}`);
        console.log(`  - With images: ${withImages}`);
        console.log(`  - With article numbers: ${withArticleNo}`);
        console.log(`  - With stock info: ${withStock}`);
        console.log('');

    } catch (error) {
        console.error('Error:', error.message);
    } finally {
        await browser.close();
    }
}

main();
