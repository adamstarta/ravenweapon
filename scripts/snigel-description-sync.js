/**
 * Snigel Description Sync Tool v2
 *
 * Compares product descriptions from Snigel B2B portal with current
 * descriptions on Shopware (via API).
 *
 * MATCHING: Uses product name and slug for reliable matching
 * - Removes "Snigel " prefix from Shopware names
 * - Falls back to slug matching
 * - Falls back to partial name matching
 *
 * Features:
 * - Uses Shopware API (not storefront scraping) for reliable matching
 * - Matches by product name (normalized) and slug
 * - Progress saving every 20 products
 * - Session health check every 30 products
 * - Retry failed products
 * - Headless mode by default
 *
 * Usage:
 *   node snigel-description-sync.js --compare              # Compare descriptions
 *   node snigel-description-sync.js --compare --retry-failed  # Retry failed only
 *   node snigel-description-sync.js --compare --product slug  # Single product
 *   node snigel-description-sync.js --dry-run              # Show what would change
 *   node snigel-description-sync.js --update               # Apply changes to Shopware
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const https = require('https');

// ============================================================
// CONFIGURATION
// ============================================================
const CONFIG = {
    // Snigel B2B Portal
    snigel: {
        baseUrl: 'https://products.snigel.se',
        username: 'Raven Weapon AG',
        password: 'wVREVbRZfqT&Fba@f(^2UKOw'
    },

    // Shopware API
    shopware: {
        storefrontUrl: 'https://ortak.ch',
        apiUrl: 'https://ortak.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
    },

    // Output files
    outputDir: path.join(__dirname, 'snigel-description-data'),
    outputFiles: {
        comparison: 'description-comparison.json',
        mismatches: 'description-mismatches.json',
        shopwareProducts: 'shopware-snigel-products.json',
        log: 'description-sync-log.json'
    },

    // Scraping settings (ROBUST MODE)
    requestDelay: 1500,
    pageTimeout: 30000,
    maxRetries: 3,
    sessionRefreshEvery: 30,
    retryDelays: [2000, 5000, 10000],

    // Comparison settings
    similarityThreshold: 0.85  // 85% similarity = considered match
};

// Create output directory
if (!fs.existsSync(CONFIG.outputDir)) {
    fs.mkdirSync(CONFIG.outputDir, { recursive: true });
}

// ============================================================
// HELPERS - DELAYS & HTTP
// ============================================================

function delay(ms) {
    const jitter = 0.5 + Math.random();
    const randomMs = Math.floor(ms * jitter);
    return new Promise(resolve => setTimeout(resolve, randomMs));
}

function productDelay() {
    const baseDelay = 2000 + Math.floor(Math.random() * 2000);
    return delay(baseDelay);
}

/**
 * Make HTTPS request
 */
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

// ============================================================
// HELPERS - TEXT COMPARISON
// ============================================================

function cleanText(text) {
    if (!text) return '';
    return text
        .replace(/<[^>]*>/g, ' ')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/\s+/g, ' ')
        .replace(/['']/g, "'")
        .replace(/[""]/g, '"')
        .trim()
        .toLowerCase();
}

function calculateSimilarity(text1, text2) {
    const clean1 = cleanText(text1);
    const clean2 = cleanText(text2);

    if (!clean1 && !clean2) return 1;
    if (!clean1 || !clean2) return 0;

    const words1 = new Set(clean1.split(/\s+/).filter(w => w.length > 2));
    const words2 = new Set(clean2.split(/\s+/).filter(w => w.length > 2));

    if (words1.size === 0 && words2.size === 0) return 1;
    if (words1.size === 0 || words2.size === 0) return 0;

    const intersection = new Set([...words1].filter(x => words2.has(x)));
    const union = new Set([...words1, ...words2]);

    return intersection.size / union.size;
}

function getDiffSummary(snigelText, shopwareText) {
    const snigelClean = cleanText(snigelText);
    const shopwareClean = cleanText(shopwareText);

    const snigelWords = new Set(snigelClean.split(/\s+/).filter(w => w.length > 2));
    const shopwareWords = new Set(shopwareClean.split(/\s+/).filter(w => w.length > 2));

    const onlyInSnigel = [...snigelWords].filter(w => !shopwareWords.has(w));
    const onlyInShopware = [...shopwareWords].filter(w => !snigelWords.has(w));

    return {
        snigelLength: snigelText?.length || 0,
        shopwareLength: shopwareText?.length || 0,
        snigelWordCount: snigelWords.size,
        shopwareWordCount: shopwareWords.size,
        uniqueToSnigel: onlyInSnigel.slice(0, 10),
        uniqueToShopware: onlyInShopware.slice(0, 10)
    };
}

// ============================================================
// SHOPWARE API - FETCH ALL SNIGEL PRODUCTS
// ============================================================

let shopwareToken = null;

async function getShopwareToken() {
    if (shopwareToken) return shopwareToken;

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
        throw new Error('Failed to get Shopware API token');
    }

    shopwareToken = tokenRes.data.access_token;
    console.log('  ✓ Token obtained\n');
    return shopwareToken;
}

/**
 * Find Snigel manufacturer ID
 */
async function findSnigelManufacturerId() {
    const token = await getShopwareToken();

    console.log('  Finding Snigel manufacturer ID...');
    const searchRes = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product-manufacturer`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: {
            limit: 50,
            filter: [
                { type: 'contains', field: 'name', value: 'Snigel' }
            ]
        }
    });

    if (searchRes.data.data && searchRes.data.data.length > 0) {
        const manufacturer = searchRes.data.data[0];
        console.log(`  ✓ Found manufacturer: ${manufacturer.name} (${manufacturer.id})`);
        return manufacturer.id;
    }

    console.log('  ⚠ Snigel manufacturer not found, will fetch all products');
    return null;
}

/**
 * Fetch ALL Snigel products from Shopware via API
 * Uses manufacturer ID for accurate filtering
 */
async function fetchAllShopwareProducts() {
    const token = await getShopwareToken();
    const allProducts = [];
    let page = 1;
    const limit = 100;

    // First find Snigel manufacturer ID
    const manufacturerId = await findSnigelManufacturerId();

    console.log('  Fetching all Snigel products from Shopware API...');

    while (true) {
        // Build filter - by manufacturer if found, otherwise by name patterns
        const filters = manufacturerId
            ? [{ type: 'equals', field: 'manufacturerId', value: manufacturerId }]
            : [{ type: 'multi', operator: 'or', queries: [
                { type: 'contains', field: 'name', value: 'Snigel' },
                { type: 'contains', field: 'name', value: 'SNIGEL' },
                { type: 'prefix', field: 'productNumber', value: 'SN-' }
              ]}];

        const searchRes = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: {
                limit: limit,
                page: page,
                filter: filters,
                includes: {
                    product: ['id', 'productNumber', 'name', 'description', 'customFields']
                }
            }
        });

        if (!searchRes.data.data || searchRes.data.data.length === 0) {
            break;
        }

        allProducts.push(...searchRes.data.data);
        console.log(`    Page ${page}: ${searchRes.data.data.length} products (total: ${allProducts.length})`);

        if (searchRes.data.data.length < limit) {
            break;  // Last page
        }
        page++;
        await delay(300);  // Rate limiting
    }

    console.log(`  ✓ Fetched ${allProducts.length} Snigel products from Shopware\n`);

    // Save for reference
    const outputPath = path.join(CONFIG.outputDir, CONFIG.outputFiles.shopwareProducts);
    fs.writeFileSync(outputPath, JSON.stringify(allProducts, null, 2));

    return allProducts;
}

/**
 * Build lookup maps for matching products
 * - By normalized name (primary)
 * - By slug (secondary)
 */
function buildShopwareLookup(products) {
    const byName = {};
    const bySlug = {};

    for (const product of products) {
        const productData = {
            id: product.id,
            name: product.name,
            description: product.description || '',
            productNumber: product.productNumber,
            customFields: product.customFields || {}
        };

        // Normalize name for matching: lowercase, remove "Snigel " prefix
        if (product.name) {
            const normalizedName = product.name
                .toLowerCase()
                .replace(/^snigel\s+/i, '')  // Remove "Snigel " prefix
                .replace(/\s+/g, ' ')
                .trim();
            byName[normalizedName] = productData;
        }

        // Also index by slug (from productNumber, remove SN- prefix)
        if (product.productNumber) {
            const slug = product.productNumber
                .toLowerCase()
                .replace(/^sn-/, '')  // Remove SN- prefix
                .trim();
            bySlug[slug] = productData;
        }
    }

    return { byName, bySlug };
}

/**
 * Find matching Shopware product for a Snigel product
 */
function findShopwareMatch(snigelProduct, lookup) {
    // 1. Try matching by normalized name
    const normalizedSnigelName = snigelProduct.name
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();

    if (lookup.byName[normalizedSnigelName]) {
        return lookup.byName[normalizedSnigelName];
    }

    // 2. Try matching by slug
    if (snigelProduct.slug) {
        const snigelSlug = snigelProduct.slug.toLowerCase();
        if (lookup.bySlug[snigelSlug]) {
            return lookup.bySlug[snigelSlug];
        }
    }

    // 3. Try partial name match (first 20 chars)
    const shortName = normalizedSnigelName.substring(0, 20);
    for (const [name, product] of Object.entries(lookup.byName)) {
        if (name.startsWith(shortName) || shortName.startsWith(name.substring(0, 20))) {
            return product;
        }
    }

    return null;
}

// ============================================================
// SNIGEL B2B - DESCRIPTION EXTRACTION
// ============================================================

async function loginToSnigel(page) {
    console.log('  Logging in to Snigel B2B portal...');

    await page.goto(`${CONFIG.snigel.baseUrl}/my-account/`, {
        waitUntil: 'domcontentloaded'
    });
    await delay(2000);

    try {
        await page.click('button:has-text("Ok")', { timeout: 3000 });
    } catch (e) { /* No popup */ }

    await page.fill('#username', CONFIG.snigel.username);
    await page.fill('#password', CONFIG.snigel.password);
    await page.click('button[name="login"]');

    await page.waitForURL('**/my-account/**', { timeout: 30000 });
    await delay(2000);

    console.log('  ✓ Login successful!');
}

async function extractSnigelDescription(page, productUrl) {
    try {
        await page.goto(productUrl, {
            waitUntil: 'domcontentloaded',
            timeout: CONFIG.pageTimeout
        });
        await delay(1000);

        const data = await page.evaluate(() => {
            const result = {
                description: '',
                description_html: '',
                short_description: '',
                features: [],
                specs: {}
            };

            // Short description
            const shortDesc = document.querySelector('.woocommerce-product-details__short-description');
            if (shortDesc) {
                result.description_html = shortDesc.innerHTML.trim();
                result.description = shortDesc.innerText.trim();
            }

            // Long description tab
            const longDesc = document.querySelector('.woocommerce-Tabs-panel--description, #tab-description');
            if (longDesc) {
                result.short_description = result.description;
                result.description = longDesc.innerText.trim();
                result.description_html = longDesc.innerHTML.trim();
            }

            // Extract features from bullet points
            const bullets = document.querySelectorAll('.woocommerce-product-details__short-description li');
            bullets.forEach(li => {
                const text = li.innerText.trim();
                if (text) result.features.push(text);
            });

            // Product meta/specs
            const metaContainer = document.querySelector('.product_meta');
            if (metaContainer) {
                const text = metaContainer.innerText;

                const weightMatch = text.match(/Weight[:\s]*([^\n]+)/i);
                if (weightMatch) result.specs.weight = weightMatch[1].trim();

                const dimMatch = text.match(/Dimensions[:\s]*([^\n]+)/i);
                if (dimMatch) result.specs.dimensions = dimMatch[1].trim();

                const articleMatch = text.match(/([0-9]{2}-[0-9A-Z]+-[0-9A-Z-]+)/);
                if (articleMatch) result.specs.articleNo = articleMatch[1].trim();
            }

            return result;
        });

        return data;
    } catch (error) {
        return { error: error.message };
    }
}

// ============================================================
// COMPARE MODE (UPDATED - API BASED)
// ============================================================

async function compareMode(singleProductSlug = null, retryFailed = false) {
    console.log('\n' + '═'.repeat(60));
    console.log('  SNIGEL DESCRIPTION SYNC v2 - COMPARE MODE');
    console.log('  Matching by Name/Slug via Manufacturer ID');
    console.log('═'.repeat(60));

    // Load Snigel product list
    const productsPath = path.join(__dirname, 'snigel-data', 'products-with-descriptions.json');
    if (!fs.existsSync(productsPath)) {
        console.error('\n  ✗ Product list not found:', productsPath);
        console.error('  Run snigel-description-scraper-fast.js first.');
        process.exit(1);
    }

    let snigelProducts = JSON.parse(fs.readFileSync(productsPath, 'utf8'));

    // Load existing comparison results
    const outputPath = path.join(CONFIG.outputDir, CONFIG.outputFiles.comparison);
    let existingResults = [];
    if (fs.existsSync(outputPath)) {
        existingResults = JSON.parse(fs.readFileSync(outputPath, 'utf8'));
    }

    // Filter products
    if (singleProductSlug) {
        snigelProducts = snigelProducts.filter(p => p.slug === singleProductSlug);
        if (snigelProducts.length === 0) {
            console.error(`\n  ✗ Product not found: ${singleProductSlug}`);
            process.exit(1);
        }
        console.log(`\n  Processing single product: ${snigelProducts[0].name}`);
    } else if (retryFailed) {
        const failedSlugs = existingResults
            .filter(r => r.error || !r.shopwareFound)
            .map(r => r.slug);

        const existingSlugs = existingResults.map(r => r.slug);
        const missingSlugs = snigelProducts
            .filter(p => !existingSlugs.includes(p.slug))
            .map(p => p.slug);

        const allRetryableSlugs = [...new Set([...failedSlugs, ...missingSlugs])];

        if (allRetryableSlugs.length === 0) {
            console.log('\n  ✓ No failed products to retry!');
            return;
        }

        snigelProducts = snigelProducts.filter(p => allRetryableSlugs.includes(p.slug));
        console.log(`\n  Retrying ${snigelProducts.length} products`);
    } else {
        console.log(`\n  Loaded ${snigelProducts.length} Snigel products`);
    }

    // STEP 1: Fetch all Shopware products via API
    const shopwareProducts = await fetchAllShopwareProducts();
    const shopwareLookup = buildShopwareLookup(shopwareProducts);

    console.log(`  Built lookup with ${Object.keys(shopwareLookup.byName).length} names, ${Object.keys(shopwareLookup.bySlug).length} slugs\n`);

    // STEP 2: Launch browser for fresh Snigel descriptions (only if needed)
    const browser = await chromium.launch({
        headless: true,
        args: ['--disable-blink-features=AutomationControlled', '--no-sandbox']
    });

    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        viewport: { width: 1920, height: 1080 },
        locale: 'de-DE'
    });

    const page = await context.newPage();
    page.setDefaultTimeout(CONFIG.pageTimeout);

    try {
        await loginToSnigel(page);

        console.log('\n  Comparing descriptions...\n');

        const results = [];
        let processed = 0;
        let matches = 0;
        let mismatches = 0;
        let notFound = 0;
        let errors = 0;
        let consecutiveFailures = 0;

        // Session verification
        async function verifySession() {
            try {
                console.log('    🔄 Verifying Snigel session...');
                await page.goto(`${CONFIG.snigel.baseUrl}/my-account/`, {
                    waitUntil: 'domcontentloaded',
                    timeout: 45000
                });
                await delay(1500);

                const isLoggedIn = await page.evaluate(() => {
                    if (document.querySelector('#username') && document.querySelector('#password')) {
                        return false;
                    }
                    return document.body.innerText.includes('My Account') ||
                           document.body.innerText.includes('Log out');
                });

                if (!isLoggedIn) {
                    console.log('    ⚠ Session expired, re-logging in...');
                    await loginToSnigel(page);
                } else {
                    console.log('    ✓ Session active');
                }
                await delay(2000);
            } catch (e) {
                console.log('    ⚠ Session check error - continuing');
                await delay(5000);
            }
        }

        for (const product of snigelProducts) {
            processed++;

            // Periodic session check
            if (processed > 1 && processed % CONFIG.sessionRefreshEvery === 0) {
                await verifySession();
            }

            console.log(`  [${processed}/${snigelProducts.length}] ${product.name.substring(0, 50)}`);

            const result = {
                name: product.name,
                slug: product.slug,
                articleNo: product.article_no,
                snigelUrl: product.url,
                shopwareId: null,
                shopwareName: null,
                shopwareFound: false,
                snigelDescription: product.description || '',
                snigelDescriptionHtml: product.description_html || '',
                shopwareDescription: '',
                similarity: 0,
                isMatch: false,
                diff: null,
                error: null
            };

            try {
                // MATCH BY NAME OR SLUG
                const shopwareProduct = findShopwareMatch(product, shopwareLookup);

                if (shopwareProduct) {
                    result.shopwareFound = true;
                    result.shopwareId = shopwareProduct.id;
                    result.shopwareName = shopwareProduct.name;
                    result.shopwareDescription = shopwareProduct.description || '';

                    console.log(`    → Found in Shopware: ${shopwareProduct.name.substring(0, 40)}`);

                    // Fetch fresh Snigel description if existing is empty
                    if (!result.snigelDescription || result.snigelDescription.length < 20) {
                        console.log('    → Fetching fresh Snigel description...');
                        const snigelData = await extractSnigelDescription(page, product.url);
                        if (snigelData.description) {
                            result.snigelDescription = snigelData.description;
                            result.snigelDescriptionHtml = snigelData.description_html;
                        }
                    }

                    // Compare descriptions
                    result.similarity = calculateSimilarity(result.snigelDescription, result.shopwareDescription);
                    result.isMatch = result.similarity >= CONFIG.similarityThreshold;
                    result.diff = getDiffSummary(result.snigelDescription, result.shopwareDescription);

                    const similarityPct = Math.round(result.similarity * 100);
                    if (result.isMatch) {
                        console.log(`    → ✓ MATCH (${similarityPct}% similar)`);
                        matches++;
                    } else {
                        console.log(`    → ✗ MISMATCH (${similarityPct}% similar)`);
                        if (result.snigelDescription) {
                            console.log(`       Snigel: ${result.snigelDescription.substring(0, 50)}...`);
                        }
                        if (result.shopwareDescription) {
                            console.log(`       Shopware: ${result.shopwareDescription.substring(0, 50)}...`);
                        } else {
                            console.log(`       Shopware: (empty)`);
                        }
                        mismatches++;
                    }
                    consecutiveFailures = 0;

                } else {
                    console.log(`    → ⚠ NOT FOUND in Shopware (name: ${product.name.substring(0, 30)})`);
                    notFound++;
                    consecutiveFailures++;
                }

            } catch (error) {
                result.error = error.message;
                console.log(`    → ✗ Error: ${error.message.substring(0, 60)}`);
                errors++;
                consecutiveFailures++;
            }

            results.push(result);

            // Check for consecutive failures
            if (consecutiveFailures >= 5) {
                console.log('    ⏸ Multiple failures - verifying session...');
                await verifySession();
                await delay(5000);
                consecutiveFailures = 0;
            }

            // Human-like delay
            await productDelay();

            // Save progress every 20 products
            if (processed % 20 === 0) {
                const tempResults = [...existingResults];
                results.forEach(r => {
                    const idx = tempResults.findIndex(e => e.slug === r.slug);
                    if (idx >= 0) tempResults[idx] = r;
                    else tempResults.push(r);
                });
                fs.writeFileSync(outputPath, JSON.stringify(tempResults, null, 2));
                console.log(`    💾 Progress saved (${processed} products)`);
            }
        }

        // Merge and save final results
        let finalResults = results;
        if ((retryFailed || singleProductSlug) && existingResults.length > 0) {
            finalResults = existingResults.map(existing => {
                const newResult = results.find(r => r.slug === existing.slug);
                return newResult || existing;
            });
            results.forEach(r => {
                if (!existingResults.find(e => e.slug === r.slug)) {
                    finalResults.push(r);
                }
            });
        }
        fs.writeFileSync(outputPath, JSON.stringify(finalResults, null, 2));

        // Save mismatches separately
        const mismatchResults = finalResults.filter(r => !r.isMatch && !r.error && r.shopwareFound);
        const mismatchesPath = path.join(CONFIG.outputDir, CONFIG.outputFiles.mismatches);
        fs.writeFileSync(mismatchesPath, JSON.stringify(mismatchResults, null, 2));

        // Summary
        console.log('\n' + '═'.repeat(60));
        console.log('  COMPARISON COMPLETE');
        console.log('═'.repeat(60));
        console.log(`  Total Snigel products:  ${processed}`);
        console.log(`  Found in Shopware:      ${matches + mismatches}`);
        console.log(`  ✓ Matches:              ${matches} (≥${CONFIG.similarityThreshold * 100}% similar)`);
        console.log(`  ✗ Mismatches:           ${mismatches}`);
        console.log(`  ⚠ Not in Shopware:      ${notFound}`);
        console.log(`  ✗ Errors:               ${errors}`);
        console.log(`  Output: ${outputPath}`);
        console.log(`  Mismatches: ${mismatchesPath}`);
        console.log('═'.repeat(60));

    } catch (error) {
        console.error('\n  ✗ Error:', error.message);
    } finally {
        await browser.close();
    }
}

// ============================================================
// DRY-RUN MODE
// ============================================================

async function dryRunMode() {
    console.log('\n' + '═'.repeat(60));
    console.log('  DRY RUN - SHOWING DESCRIPTION DIFFERENCES');
    console.log('═'.repeat(60));

    const comparisonPath = path.join(CONFIG.outputDir, CONFIG.outputFiles.comparison);
    if (!fs.existsSync(comparisonPath)) {
        console.error('\n  ✗ No comparison data found. Run --compare first.');
        process.exit(1);
    }

    const products = JSON.parse(fs.readFileSync(comparisonPath, 'utf8'));
    const mismatches = products.filter(p => !p.isMatch && !p.error && p.shopwareFound);

    console.log(`\n  Found ${mismatches.length} products with description differences\n`);

    for (const product of mismatches) {
        console.log(`\n  ${product.name}`);
        console.log(`  ${'─'.repeat(50)}`);
        console.log(`  Article No: ${product.articleNo}`);
        console.log(`  Similarity: ${Math.round(product.similarity * 100)}%`);
        console.log(`  Shopware ID: ${product.shopwareId}`);

        if (product.diff) {
            console.log(`  Snigel: ${product.diff.snigelLength} chars, ${product.diff.snigelWordCount} words`);
            console.log(`  Shopware: ${product.diff.shopwareLength} chars, ${product.diff.shopwareWordCount} words`);

            if (product.diff.uniqueToSnigel.length > 0) {
                console.log(`  Only in Snigel: ${product.diff.uniqueToSnigel.join(', ')}`);
            }
            if (product.diff.uniqueToShopware.length > 0) {
                console.log(`  Only in Shopware: ${product.diff.uniqueToShopware.join(', ')}`);
            }
        }
    }

    console.log('\n' + '═'.repeat(60));
    console.log(`  ${mismatches.length} products would be updated`);
    console.log('  To apply changes, run: node snigel-description-sync.js --update');
    console.log('═'.repeat(60));
}

// ============================================================
// UPDATE MODE
// ============================================================

async function updateMode(singleProductSlug = null) {
    console.log('\n' + '═'.repeat(60));
    console.log('  UPDATING SHOPWARE DESCRIPTIONS');
    console.log('  (Only if Snigel has MORE content than Shopware)');
    console.log('═'.repeat(60));

    const comparisonPath = path.join(CONFIG.outputDir, CONFIG.outputFiles.comparison);
    if (!fs.existsSync(comparisonPath)) {
        console.error('\n  ✗ No comparison data found. Run --compare first.');
        process.exit(1);
    }

    let products = JSON.parse(fs.readFileSync(comparisonPath, 'utf8'));

    // Filter to mismatches only (or single product)
    if (singleProductSlug) {
        products = products.filter(p => p.slug === singleProductSlug);
    } else {
        products = products.filter(p => !p.isMatch && !p.error && p.shopwareFound && p.snigelDescription);
    }

    console.log(`\n  ${products.length} mismatched products found\n`);

    const token = await getShopwareToken();

    const log = [];
    let updated = 0, failed = 0, skipped = 0, keptBetter = 0;

    for (const product of products) {
        console.log(`  Processing: ${product.name.substring(0, 45)}`);

        if (!product.shopwareId) {
            console.log(`    ✗ No Shopware ID`);
            skipped++;
            continue;
        }

        if (!product.snigelDescription && !product.snigelDescriptionHtml) {
            console.log(`    ✗ No Snigel description`);
            skipped++;
            continue;
        }

        // OPTION A: Only update if Snigel has MORE content
        const snigelLength = (product.snigelDescription || '').length;
        const shopwareLength = (product.shopwareDescription || '').length;

        if (snigelLength <= shopwareLength) {
            console.log(`    ⊘ SKIP - Shopware is better (${shopwareLength} chars vs Snigel ${snigelLength} chars)`);
            keptBetter++;
            log.push({
                timestamp: new Date().toISOString(),
                name: product.name,
                reason: `Shopware better: ${shopwareLength} vs ${snigelLength} chars`,
                status: 'kept_shopware'
            });
            continue;
        }

        console.log(`    → Snigel has more content (${snigelLength} vs ${shopwareLength} chars)`);

        // Update description
        const updatePayload = {
            description: product.snigelDescriptionHtml || product.snigelDescription
        };

        const updateRes = await httpRequest(`${CONFIG.shopware.apiUrl}/product/${product.shopwareId}`, {
            method: 'PATCH',
            headers: { 'Authorization': `Bearer ${token}` },
            body: updatePayload
        });

        if (updateRes.status === 204 || updateRes.status === 200) {
            console.log(`    ✓ Updated description`);
            updated++;
            log.push({
                timestamp: new Date().toISOString(),
                productId: product.shopwareId,
                name: product.name,
                articleNo: product.articleNo,
                snigelLength: snigelLength,
                shopwareLength: shopwareLength,
                status: 'success'
            });
        } else {
            console.log(`    ✗ Failed: ${updateRes.status}`);
            failed++;
            log.push({
                timestamp: new Date().toISOString(),
                name: product.name,
                error: `API error: ${updateRes.status}`,
                status: 'failed'
            });
        }

        await delay(300);
    }

    // Save log
    const logPath = path.join(CONFIG.outputDir, CONFIG.outputFiles.log);
    fs.writeFileSync(logPath, JSON.stringify(log, null, 2));

    console.log('\n' + '═'.repeat(60));
    console.log('  UPDATE COMPLETE');
    console.log('═'.repeat(60));
    console.log(`  ✓ Updated:       ${updated} products (Snigel had more content)`);
    console.log(`  ⊘ Kept Shopware: ${keptBetter} products (Shopware was better)`);
    console.log(`  ✗ Failed:        ${failed} products`);
    console.log(`  ⊘ Skipped:       ${skipped} products (no data)`);
    console.log(`  Log saved: ${logPath}`);
    console.log('═'.repeat(60));
}

// ============================================================
// MAIN
// ============================================================

async function main() {
    const args = process.argv.slice(2);

    let mode = '--compare';
    let productSlug = null;
    let retryFailed = false;

    for (let i = 0; i < args.length; i++) {
        if (args[i] === '--compare' || args[i] === '--dry-run' || args[i] === '--update') {
            mode = args[i];
        } else if (args[i] === '--product' && args[i + 1]) {
            productSlug = args[i + 1];
            i++;
        } else if (args[i] === '--retry-failed' || args[i] === '--retry') {
            retryFailed = true;
        }
    }

    console.log('\n' + '╔' + '═'.repeat(58) + '╗');
    console.log('║' + '  SNIGEL DESCRIPTION SYNC TOOL v2'.padEnd(58) + '║');
    console.log('║' + '  Matching by Name/Slug via Manufacturer ID'.padEnd(58) + '║');
    console.log('╚' + '═'.repeat(58) + '╝');

    try {
        if (mode === '--compare') {
            await compareMode(productSlug, retryFailed);
        } else if (mode === '--dry-run') {
            await dryRunMode();
        } else if (mode === '--update') {
            await updateMode(productSlug);
        }
    } catch (error) {
        console.error('\n  ✗ Fatal error:', error.message);
        console.error(error.stack);
        process.exit(1);
    }
}

main();
