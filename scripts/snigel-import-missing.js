/**
 * Snigel Import Missing Products
 *
 * Scrapes and imports ONLY the 2 missing products to Shopware
 * Without conflicting with existing products
 *
 * Usage: node snigel-import-missing.js
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const https = require('https');

// ============================================================
// CONFIGURATION
// ============================================================

const CONFIG = {
    // The 2 missing products
    missingProducts: [
        {
            name: 'COVERT EQUIPMENT VEST -12 FIN',
            slug: 'covert-equipment-vest-12-fin',
            url: 'https://products.snigel.se/product/covert-equipment-vest-12-fin/'
        },
        {
            name: 'EQUIPMENT BELT HARNESS -10',
            slug: 'equipment-belt-harness-10',
            url: 'https://products.snigel.se/product/equipment-belt-harness-10/'
        }
    ],

    // Snigel B2B Portal
    snigel: {
        baseUrl: 'https://products.snigel.se',
        username: 'Raven Weapon AG',
        password: 'wVREVbRZfqT&Fba@f(^2UKOw'
    },

    // Shopware API
    shopware: {
        baseUrl: 'https://ortak.ch',
        apiUrl: 'https://ortak.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg',
        currencyId: '0191c12cf40d718a8a3439b74a6f083c', // CHF
        taxId: '0191c12cf4077193b2e5c4cfd8ce8dee', // 8.1% Swiss VAT
        salesChannelId: '0191c12cf77a7193b38f4a2f72ce6fb9' // Storefront
    },

    // Pricing formula: B2B EUR × 1.5 × 1.08 × ExchangeRate = CHF
    pricing: {
        markup: 1.5,
        vat: 1.08,
        exchangeRate: 0.94 // EUR to CHF (will fetch live)
    },

    // Output
    outputDir: path.join(__dirname, 'snigel-import-data')
};

if (!fs.existsSync(CONFIG.outputDir)) {
    fs.mkdirSync(CONFIG.outputDir, { recursive: true });
}

// ============================================================
// HELPERS
// ============================================================

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function httpRequest(url, options = {}) {
    return new Promise((resolve, reject) => {
        const urlObj = new URL(url);
        const reqOptions = {
            hostname: urlObj.hostname,
            port: 443,
            path: urlObj.pathname + urlObj.search,
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...options.headers
            }
        };

        const req = https.request(reqOptions, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try {
                    resolve({ status: res.statusCode, data: JSON.parse(data) });
                } catch (e) {
                    resolve({ status: res.statusCode, data: data });
                }
            });
        });

        req.on('error', reject);
        if (options.body) req.write(JSON.stringify(options.body));
        req.end();
    });
}

// Parse European price: "30,22 €" → 30.22
function parseEuropeanPrice(text) {
    if (!text) return null;
    let clean = text.replace(/[€$£CHF]/gi, '').trim();
    clean = clean.replace(/\s/g, '');
    clean = clean.replace(',', '.');
    const num = parseFloat(clean);
    return isNaN(num) ? null : num;
}

// Swiss rounding to nearest 0.05 CHF
function swissRound(price) {
    return Math.round(price * 20) / 20;
}

// Calculate selling price
async function calculateSellingPrice(b2bPriceEUR) {
    const rate = CONFIG.pricing.exchangeRate;
    const withMarkup = b2bPriceEUR * CONFIG.pricing.markup;
    const withVAT = withMarkup * CONFIG.pricing.vat;
    const inCHF = withVAT * rate;
    return swissRound(inCHF);
}

// Get exchange rate
async function getExchangeRate() {
    try {
        console.log('  Fetching EUR to CHF exchange rate...');
        const response = await httpRequest('https://api.exchangerate-api.com/v4/latest/EUR');
        if (response.data && response.data.rates && response.data.rates.CHF) {
            CONFIG.pricing.exchangeRate = response.data.rates.CHF;
            console.log(`  ✓ Exchange rate: 1 EUR = ${CONFIG.pricing.exchangeRate} CHF`);
        }
    } catch (e) {
        console.log(`  ⚠ Using fallback rate: ${CONFIG.pricing.exchangeRate} CHF`);
    }
}

// ============================================================
// SCRAPE PRODUCT FROM SNIGEL
// ============================================================

async function scrapeProduct(page, product) {
    console.log(`\n  Scraping: ${product.name}`);

    const result = {
        ...product,
        description: '',
        description_html: '',
        shortDescription: '',
        images: [],
        b2bPriceEUR: null,
        sellingPriceCHF: null,
        hasVariants: false,
        variants: [],
        category: 'Snigel',
        categories: [],
        articleNo: '',
        ean: '',
        weight: null,
        dimensions: '',
        inStock: true
    };

    try {
        await page.goto(product.url, { waitUntil: 'domcontentloaded', timeout: 60000 });
        await delay(3000);

        // Extract product data (using logic from snigel-description-scraper-fast.js)
        const data = await page.evaluate(() => {
            const result = {
                description: '',
                description_html: '',
                shortDescription: '',
                categories: [],
                articleNo: '',
                ean: '',
                weight: null,
                dimensions: '',
                images: [],
                priceText: null,
                hasVariants: false,
                variantOptions: [],
                inStock: true
            };

            // ========================================
            // DESCRIPTION (from woocommerce short desc)
            // ========================================
            const descContainer = document.querySelector('.woocommerce-product-details__short-description');
            if (descContainer) {
                result.description_html = descContainer.innerHTML.trim();
                result.shortDescription = descContainer.innerText.trim();
            }

            // Full description from tab
            const fullDescEl = document.querySelector('#tab-description, .woocommerce-Tabs-panel--description');
            if (fullDescEl) {
                result.description = fullDescEl.innerText.trim();
            }

            // If no full description, use short description
            if (!result.description && result.shortDescription) {
                result.description = result.shortDescription;
            }

            // ========================================
            // PRODUCT META (article, ean, weight, etc)
            // ========================================
            const metaContainer = document.querySelector('.product_meta');
            if (metaContainer) {
                const metaText = metaContainer.innerText;

                // Article number (format: 00-00000-00-000)
                const articleMatch = metaText.match(/([0-9]{2}-[0-9A-Z]+-[0-9A-Z-]+)/);
                if (articleMatch) result.articleNo = articleMatch[1].trim();

                // EAN
                const eanMatch = metaText.match(/EAN[:\s\t]*([0-9]+)/i);
                if (eanMatch) result.ean = eanMatch[1].trim();

                // Weight
                const weightMatch = metaText.match(/Weight[:\s\t]*([0-9]+)\s*g/i);
                if (weightMatch) result.weight = parseInt(weightMatch[1]);

                // Dimensions
                const dimMatch = metaText.match(/Dimensions[:\s\t]*([^\n]+)/i);
                if (dimMatch) result.dimensions = dimMatch[1].trim();
            }

            // ========================================
            // CATEGORY
            // ========================================
            const categoryLink = document.querySelector('.product_meta a[href*="product-category"]');
            if (categoryLink) {
                result.categories.push(categoryLink.innerText.trim());
            }

            // ========================================
            // IMAGES
            // ========================================
            document.querySelectorAll('.woocommerce-product-gallery__image img, .product-images img, .thumbnails img').forEach(img => {
                const src = img.getAttribute('data-large_image') || img.getAttribute('data-src') || img.src;
                if (src && !src.includes('placeholder') && !result.images.includes(src)) {
                    // Clean URL (remove size suffix)
                    const cleanSrc = src.replace(/-uai-\d+x\d+/i, '').replace(/-\d+x\d+\./, '.');
                    if (!result.images.includes(cleanSrc)) {
                        result.images.push(cleanSrc);
                    }
                }
            });

            // ========================================
            // PRICE - "1 and more" discount price
            // ========================================
            const pageText = document.body.innerText;
            const priceMatch = pageText.match(/1\s+and\s+more\s*:\s*([\d\s.,]+)\s*€/i);
            if (priceMatch) {
                result.priceText = priceMatch[1];
            } else {
                // Fallback: look for price below RRP
                const lines = pageText.split('\n');
                for (let i = 0; i < lines.length; i++) {
                    if (lines[i].includes('RRP') || lines[i].includes('UVP')) {
                        for (let j = i + 1; j < Math.min(i + 5, lines.length); j++) {
                            const line = lines[j].trim();
                            if (line.includes('and more')) continue;
                            const match = line.match(/^([\d\s.,]+)\s*€/);
                            if (match) {
                                result.priceText = match[1];
                                break;
                            }
                        }
                        break;
                    }
                }
            }

            // ========================================
            // VARIANTS (dropdowns)
            // ========================================
            const variantSelect = document.querySelector('table.variations select, .variations select');
            if (variantSelect) {
                result.hasVariants = true;
                variantSelect.querySelectorAll('option').forEach(opt => {
                    if (opt.value && opt.value !== '' && !opt.textContent.toLowerCase().includes('choose')) {
                        result.variantOptions.push({
                            name: opt.textContent.trim(),
                            value: opt.value
                        });
                    }
                });
            }

            // Stock
            result.inStock = !pageText.toLowerCase().includes('out of stock');

            return result;
        });

        // Merge data
        result.description = data.description || data.shortDescription || '';
        result.description_html = data.description_html || '';
        result.shortDescription = data.shortDescription || '';
        result.images = data.images || [];
        result.articleNo = data.articleNo || '';
        result.ean = data.ean || '';
        result.category = data.categories.length > 0 ? data.categories[0] : 'Snigel';
        result.categories = data.categories || [];
        result.weight = data.weight;
        result.dimensions = data.dimensions || '';
        result.inStock = data.inStock;
        result.hasVariants = data.hasVariants;

        console.log(`    ✓ Description: ${result.description ? result.description.substring(0, 60) + '...' : 'N/A'}`);
        console.log(`    ✓ Article No: ${result.articleNo || 'N/A'}`);
        console.log(`    ✓ EAN: ${result.ean || 'N/A'}`);
        console.log(`    ✓ Weight: ${result.weight ? result.weight + 'g' : 'N/A'}`);
        console.log(`    ✓ Category: ${result.category}`);

        // Parse price
        if (data.priceText) {
            result.b2bPriceEUR = parseEuropeanPrice(data.priceText);
            if (result.b2bPriceEUR) {
                result.sellingPriceCHF = await calculateSellingPrice(result.b2bPriceEUR);
            }
        }

        // Handle variants if present
        if (data.hasVariants && data.variantOptions && data.variantOptions.length > 0) {
            console.log(`    → ${data.variantOptions.length} variants found`);

            for (const opt of data.variantOptions) {
                try {
                    // Select variant
                    await page.selectOption('table.variations select, .variations select', opt.value);
                    await delay(1500);

                    // Get price for this variant
                    const variantPrice = await page.evaluate(() => {
                        const pageText = document.body.innerText;
                        const match = pageText.match(/1\s+and\s+more\s*:\s*([\d\s.,]+)\s*€/i);
                        return match ? match[1] : null;
                    });

                    const b2bPrice = parseEuropeanPrice(variantPrice);
                    if (b2bPrice) {
                        const sellingPrice = await calculateSellingPrice(b2bPrice);
                        result.variants.push({
                            name: opt.name,
                            value: opt.value,
                            b2bPriceEUR: b2bPrice,
                            sellingPriceCHF: sellingPrice
                        });
                        console.log(`      ${opt.name}: €${b2bPrice.toFixed(2)} → CHF ${sellingPrice.toFixed(2)}`);
                    }
                } catch (e) {
                    console.log(`      ${opt.name}: ⚠ Error`);
                }
            }

            // Use first variant price as main price
            if (result.variants.length > 0 && !result.b2bPriceEUR) {
                result.b2bPriceEUR = result.variants[0].b2bPriceEUR;
                result.sellingPriceCHF = result.variants[0].sellingPriceCHF;
            }
        }

        console.log(`    ✓ Price: €${result.b2bPriceEUR?.toFixed(2) || 'N/A'} → CHF ${result.sellingPriceCHF?.toFixed(2) || 'N/A'}`);
        console.log(`    ✓ Images: ${result.images.length}`);

    } catch (error) {
        console.log(`    ✗ Error: ${error.message}`);
        result.error = error.message;
    }

    return result;
}

// ============================================================
// IMPORT TO SHOPWARE
// ============================================================

async function importToShopware(products) {
    console.log('\n═══════════════════════════════════════════════════════════');
    console.log('  IMPORTING TO SHOPWARE');
    console.log('═══════════════════════════════════════════════════════════\n');

    // Get API token
    console.log('  Getting Shopware API token...');
    const tokenRes = await httpRequest(`${CONFIG.shopware.apiUrl}/oauth/token`, {
        method: 'POST',
        body: {
            grant_type: 'client_credentials',
            client_id: CONFIG.shopware.clientId,
            client_secret: CONFIG.shopware.clientSecret
        }
    });

    if (!tokenRes.data.access_token) {
        console.error('  ✗ Failed to get API token');
        return;
    }
    const token = tokenRes.data.access_token;
    console.log('  ✓ Token obtained\n');

    // Get Tax ID (8.1% Swiss VAT)
    console.log('  Finding tax rate...');
    const taxSearch = await httpRequest(`${CONFIG.shopware.apiUrl}/search/tax`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: { limit: 10 }
    });

    let taxId = null;
    if (taxSearch.data.data && taxSearch.data.data.length > 0) {
        // Find ~8% tax rate
        const swissTax = taxSearch.data.data.find(t => t.taxRate >= 7 && t.taxRate <= 9);
        if (swissTax) {
            taxId = swissTax.id;
            console.log(`  ✓ Found tax: ${swissTax.name} (${swissTax.taxRate}%)`);
        } else {
            // Use first available tax
            taxId = taxSearch.data.data[0].id;
            console.log(`  ✓ Using tax: ${taxSearch.data.data[0].name} (${taxSearch.data.data[0].taxRate}%)`);
        }
    }

    if (!taxId) {
        console.error('  ✗ No tax rate found!');
        return;
    }

    // Get Snigel manufacturer ID
    console.log('  Finding Snigel manufacturer...');
    const mfgSearch = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product-manufacturer`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: {
            limit: 1,
            filter: [{ type: 'contains', field: 'name', value: 'Snigel' }]
        }
    });

    let manufacturerId = null;
    if (mfgSearch.data.data && mfgSearch.data.data.length > 0) {
        manufacturerId = mfgSearch.data.data[0].id;
        console.log(`  ✓ Found manufacturer: ${mfgSearch.data.data[0].name}`);
    }

    // Get Sales Channel ID
    console.log('  Finding sales channel...');
    const channelSearch = await httpRequest(`${CONFIG.shopware.apiUrl}/search/sales-channel`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: { limit: 5 }
    });

    let salesChannelId = null;
    if (channelSearch.data.data && channelSearch.data.data.length > 0) {
        // Find Storefront channel
        const storefront = channelSearch.data.data.find(c => c.typeId === '8a243080f92e4c719546314b577cf82b');
        if (storefront) {
            salesChannelId = storefront.id;
            console.log(`  ✓ Found sales channel: ${storefront.name || 'Storefront'}`);
        } else {
            salesChannelId = channelSearch.data.data[0].id;
            console.log(`  ✓ Using sales channel: ${channelSearch.data.data[0].name || 'Default'}`);
        }
    }

    // Get Snigel category ID
    console.log('  Finding Snigel category...');
    const catSearch = await httpRequest(`${CONFIG.shopware.apiUrl}/search/category`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: {
            limit: 1,
            filter: [{ type: 'contains', field: 'name', value: 'Snigel' }]
        }
    });

    let categoryId = null;
    if (catSearch.data.data && catSearch.data.data.length > 0) {
        categoryId = catSearch.data.data[0].id;
        console.log(`  ✓ Found category: ${catSearch.data.data[0].name}`);
    }

    // Import each product
    for (const product of products) {
        if (!product.sellingPriceCHF) {
            console.log(`\n  ✗ Skipping ${product.name} - no price data`);
            continue;
        }

        console.log(`\n  Importing: ${product.name}`);

        // Check if product already exists
        const existsSearch = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: {
                limit: 1,
                filter: [{ type: 'contains', field: 'name', value: product.name }]
            }
        });

        if (existsSearch.data.data && existsSearch.data.data.length > 0) {
            console.log(`    ⚠ Product already exists - skipping to avoid conflict`);
            continue;
        }

        // Generate product number (use article number if available, otherwise generate)
        const productNumber = product.articleNo || `SN-${product.slug.toUpperCase().replace(/-/g, '').substring(0, 20)}`;

        // Calculate net price
        const grossPrice = product.sellingPriceCHF;
        const netPrice = Math.round((grossPrice / 1.081) * 100) / 100; // 8.1% VAT

        // Build description (HTML or plain text)
        let finalDescription = product.description || product.shortDescription || '';
        if (product.description_html) {
            finalDescription = product.description_html;
        }

        // Create product payload
        const payload = {
            name: product.name,
            productNumber: productNumber,
            stock: 100,
            taxId: taxId,  // Use dynamically fetched tax ID
            price: [{
                currencyId: CONFIG.shopware.currencyId,
                gross: grossPrice,
                net: netPrice,
                linked: false
            }],
            description: finalDescription,
            active: true,
            visibilities: salesChannelId ? [{
                salesChannelId: salesChannelId,
                visibility: 30 // All (search + listing)
            }] : [],
            customFields: {
                snigel_b2b_price_eur: product.b2bPriceEUR,
                snigel_source_url: product.url,
                snigel_slug: product.slug
            }
        };

        // Add EAN if available
        if (product.ean) {
            payload.ean = product.ean;
        }

        // Add weight if available (in grams, convert to kg for Shopware)
        if (product.weight) {
            payload.weight = product.weight / 1000; // Convert g to kg
        }

        // Add manufacturer if found
        if (manufacturerId) {
            payload.manufacturerId = manufacturerId;
        }

        // Add category if found
        if (categoryId) {
            payload.categories = [{ id: categoryId }];
        }

        // Create the product
        const createRes = await httpRequest(`${CONFIG.shopware.apiUrl}/product`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: payload
        });

        if (createRes.status === 204 || createRes.status === 200 || createRes.status === 201) {
            console.log(`    ✓ Created: ${product.name}`);
            console.log(`      Product #: ${productNumber}`);
            console.log(`      Price: CHF ${grossPrice.toFixed(2)}`);

            // Note: Images would need to be uploaded separately via media API
            if (product.images.length > 0) {
                console.log(`      📷 ${product.images.length} images available (manual upload needed)`);
            }
        } else {
            console.log(`    ✗ Failed: ${createRes.status}`);
            if (createRes.data.errors) {
                createRes.data.errors.forEach(err => {
                    console.log(`      Error: ${err.detail || err.title}`);
                });
            }
        }

        await delay(500);
    }
}

// ============================================================
// MAIN
// ============================================================

async function main() {
    console.log('\n╔══════════════════════════════════════════════════════════╗');
    console.log('║  SNIGEL IMPORT MISSING PRODUCTS                          ║');
    console.log('║  Importing 2 missing products to Shopware                ║');
    console.log('╚══════════════════════════════════════════════════════════╝\n');

    // Get exchange rate
    await getExchangeRate();

    // Launch browser
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        viewport: { width: 1920, height: 1080 }
    });
    const page = await context.newPage();
    page.setDefaultTimeout(60000);

    try {
        // Login to Snigel
        console.log('  Logging in to Snigel B2B portal...');
        await page.goto(`${CONFIG.snigel.baseUrl}/my-account/`, { waitUntil: 'domcontentloaded' });
        await delay(2000);

        // Dismiss cookie popup
        try {
            await page.click('button:has-text("Ok")', { timeout: 3000 });
        } catch (e) {}

        await page.fill('#username', CONFIG.snigel.username);
        await page.fill('#password', CONFIG.snigel.password);
        await page.click('button[name="login"]');
        await page.waitForLoadState('networkidle');
        await delay(3000);
        console.log('  ✓ Login successful!\n');

        // Scrape the 2 missing products
        console.log('═══════════════════════════════════════════════════════════');
        console.log('  SCRAPING MISSING PRODUCTS');
        console.log('═══════════════════════════════════════════════════════════');

        const scrapedProducts = [];
        for (const product of CONFIG.missingProducts) {
            const scraped = await scrapeProduct(page, product);
            scrapedProducts.push(scraped);
            await delay(2000);
        }

        // Save scraped data
        const outputPath = path.join(CONFIG.outputDir, 'missing-products-scraped.json');
        fs.writeFileSync(outputPath, JSON.stringify(scrapedProducts, null, 2));
        console.log(`\n  ✓ Saved scraped data to: ${outputPath}`);

        // Import to Shopware
        await importToShopware(scrapedProducts);

        console.log('\n═══════════════════════════════════════════════════════════');
        console.log('  COMPLETE!');
        console.log('═══════════════════════════════════════════════════════════\n');

    } catch (error) {
        console.error('\n  ✗ Error:', error.message);
    } finally {
        await browser.close();
    }
}

main().catch(console.error);
