/**
 * Snigel B2B Price Sync Tool
 *
 * Extracts B2B prices from "Quantity Discount - 1 and more" for ALL variants
 * and syncs to Shopware with correct pricing formula.
 *
 * Formula: B2B EUR × 1.5 × 1.08 × ExchangeRate = Selling Price CHF
 *
 * Usage:
 *   node snigel-b2b-sync.js --compare                # Compare prices (no changes)
 *   node snigel-b2b-sync.js --dry-run                # Show what would change
 *   node snigel-b2b-sync.js --update                 # Apply changes to Shopware
 *   node snigel-b2b-sync.js --update --product slug  # Update specific product
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

    // Shopware API (from CLAUDE.md)
    shopware: {
        baseUrl: 'https://ravenweapon.ch',
        apiUrl: 'https://ravenweapon.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg',
        currencyId: '0191c12cf40d718a8a3439b74a6f083c' // CHF
    },

    // Pricing formula
    pricing: {
        markup: 1.5,        // 50% markup
        vat: 1.08,          // 8% VAT
        tolerance: 5        // % difference to flag as mismatch
    },

    // Output files
    outputDir: path.join(__dirname, 'snigel-b2b-data'),
    outputFiles: {
        prices: 'snigel-b2b-prices.json',
        report: 'snigel-price-sync-report.json',
        log: 'snigel-price-sync-log.json'
    },

    // Scraping settings (ROBUST MODE - with session recovery)
    requestDelay: 1500,
    pageTimeout: 30000,   // Increased timeout
    maxRetries: 3,
    sessionRefreshEvery: 30,  // Re-verify login every N products
    retryDelays: [2000, 5000, 10000]  // Exponential backoff delays
};

// Create output directory
if (!fs.existsSync(CONFIG.outputDir)) {
    fs.mkdirSync(CONFIG.outputDir, { recursive: true });
}

// ============================================================
// HELPERS - DELAYS & HTTP
// ============================================================

/**
 * Random delay with jitter to look more human-like
 * Uses exponential jitter: delay * random(0.5, 1.5)
 */
function delay(ms) {
    // Add jitter: 50% to 150% of base delay
    const jitter = 0.5 + Math.random(); // 0.5 to 1.5
    const randomMs = Math.floor(ms * jitter);
    return new Promise(resolve => setTimeout(resolve, randomMs));
}

/**
 * Longer delay between products (2-4 seconds with jitter)
 */
function productDelay() {
    const baseDelay = 2000 + Math.floor(Math.random() * 2000); // 2-4 seconds
    return delay(baseDelay);
}

/**
 * Simulate human-like behavior on page (scroll, mouse move)
 */
async function simulateHumanBehavior(page) {
    try {
        // Random scroll down
        const scrollY = 100 + Math.floor(Math.random() * 300);
        await page.evaluate((y) => window.scrollBy(0, y), scrollY);
        await delay(300);

        // Scroll back up a bit
        await page.evaluate(() => window.scrollBy(0, -50));
        await delay(200);
    } catch (e) {
        // Ignore errors - not critical
    }
}

/**
 * Make HTTPS request (for Shopware API)
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
// HELPERS - EXCHANGE RATE
// ============================================================

/**
 * Cached exchange rate for session
 */
let cachedExchangeRate = null;
let cacheTimestamp = null;
const CACHE_DURATION = 24 * 60 * 60 * 1000; // 24 hours

/**
 * Fetch live ECB exchange rate (EUR to CHF)
 * Uses https://api.exchangerate-api.com/v4/latest/EUR
 */
async function getECBExchangeRate() {
    // Return cached rate if valid
    if (cachedExchangeRate && cacheTimestamp && (Date.now() - cacheTimestamp < CACHE_DURATION)) {
        return cachedExchangeRate;
    }

    try {
        console.log('  Fetching live EUR to CHF exchange rate...');
        const response = await httpRequest('https://api.exchangerate-api.com/v4/latest/EUR');

        if (response.data && response.data.rates && response.data.rates.CHF) {
            cachedExchangeRate = response.data.rates.CHF;
            cacheTimestamp = Date.now();
            console.log(`  ✓ Exchange rate: 1 EUR = ${cachedExchangeRate} CHF`);
            return cachedExchangeRate;
        } else {
            throw new Error('Invalid response from exchange rate API');
        }
    } catch (error) {
        console.error('  ✗ Failed to fetch exchange rate:', error.message);

        // Use cached rate if available (even if expired)
        if (cachedExchangeRate) {
            console.log(`  ⚠ Using cached rate: ${cachedExchangeRate} CHF`);
            return cachedExchangeRate;
        }

        // Fallback to approximate rate
        const fallbackRate = 0.94;
        console.log(`  ⚠ Using fallback rate: ${fallbackRate} CHF`);
        return fallbackRate;
    }
}

// ============================================================
// HELPERS - PRICE PARSING & CALCULATION
// ============================================================

/**
 * Parse European number format: "1 955,18" → 1955.18
 */
function parseEuropeanPrice(text) {
    if (!text) return null;

    // Remove currency symbols and trim
    let clean = text.replace(/[€$£CHF]/gi, '').trim();

    // Remove spaces (thousand separator in European format)
    clean = clean.replace(/\s/g, '');

    // Replace comma with dot (decimal separator)
    clean = clean.replace(',', '.');

    const num = parseFloat(clean);
    return isNaN(num) ? null : num;
}

/**
 * Swiss rounding to nearest 0.05 CHF
 */
function swissRound(price) {
    return Math.round(price * 20) / 20;
}

/**
 * Calculate selling price from B2B price
 * Formula: B2B EUR × 1.5 × 1.08 × ExchangeRate → Swiss Round
 */
async function calculateSellingPrice(b2bPriceEUR) {
    const exchangeRate = await getECBExchangeRate();

    // Apply formula
    const withMarkup = b2bPriceEUR * CONFIG.pricing.markup;
    const withVAT = withMarkup * CONFIG.pricing.vat;
    const inCHF = withVAT * exchangeRate;

    // Swiss rounding
    return swissRound(inCHF);
}

/**
 * Calculate purchase price (B2B price converted to CHF)
 */
async function calculatePurchasePrice(b2bPriceEUR) {
    const exchangeRate = await getECBExchangeRate();
    return swissRound(b2bPriceEUR * exchangeRate);
}

// ============================================================
// HELPERS - VARIANT DETECTION (DYNAMIC MULTI-DROPDOWN)
// ============================================================

/**
 * English to German label mapping for common variant types
 * Used as fallback when we can extract the visible label
 */
const LABEL_TRANSLATIONS = {
    // Colors
    'colour': 'FARBE',
    'color': 'FARBE',
    'farbe': 'FARBE',
    // Sizes
    'size': 'GRÖSSE',
    'v-size': 'V-GRÖSSE',
    's-size': 'S-GRÖSSE',
    'jacket-size': 'JACKEN-GRÖSSE',
    'trouser-size': 'HOSEN-GRÖSSE',
    'grösse': 'GRÖSSE',
    // Parts
    'parts': 'TEILE',
    'teile': 'TEILE',
    // Generic
    'variant': 'VARIANTE',
    'option': 'OPTION'
};

/**
 * Extract clean type name from attribute name
 * e.g., "attribute_pa_v-size" → "v-size"
 */
function extractTypeName(attrName) {
    // Remove "attribute_pa_" prefix
    const match = attrName.match(/attribute_pa_(.+)/);
    if (match) return match[1];
    return attrName;
}

/**
 * Get German label for a type
 */
function getGermanLabel(typeName) {
    const lower = typeName.toLowerCase();
    // Check exact match
    if (LABEL_TRANSLATIONS[lower]) {
        return LABEL_TRANSLATIONS[lower];
    }
    // Check if contains common keywords
    if (lower.includes('color') || lower.includes('colour')) return 'FARBE';
    if (lower.includes('size')) return 'GRÖSSE';
    if (lower.includes('part')) return 'TEILE';
    // Capitalize as-is
    return typeName.toUpperCase();
}

// ============================================================
// HELPERS - B2B PRICE EXTRACTION
// ============================================================

/**
 * Extract B2B price from product page
 * Priority:
 *   1. "1 and more" quantity discount price (preferred)
 *   2. Price below RRP (second price after RRP is B2B)
 *   3. Direct price (if no RRP label)
 */
async function extractB2BPrice(page) {
    const priceData = await page.evaluate(() => {
        const pageText = document.body.innerText;

        // =====================================================
        // PRIORITY 1: Look for "1 and more" quantity discount
        // =====================================================

        // Pattern 1: "1 and more : 1 955,18 €" or "1 and more:73,47 €"
        const match1 = pageText.match(/1\s+and\s+more\s*:\s*([\d\s.,]+)\s*€/i);
        if (match1) return { price: match1[1], source: '1-and-more' };

        // Pattern 2: "1 oder mehr : 1 955,18 €" (German)
        const match2 = pageText.match(/1\s+oder\s+mehr\s*:\s*([\d\s.,]+)\s*€/i);
        if (match2) return { price: match2[1], source: '1-oder-mehr' };

        // Pattern 3: Look in quantity discount sections
        const discountSections = document.querySelectorAll('.summary, .woocommerce-product-details, .product-summary');
        for (const section of discountSections) {
            const text = section.innerText;
            const match = text.match(/1\s+and\s+more\s*:\s*([\d\s.,]+)\s*€/i);
            if (match) return { price: match[1], source: 'discount-section' };
        }

        // Pattern 4: Check lines for "1 and more"
        const lines = pageText.split('\n');
        for (let i = 0; i < lines.length; i++) {
            if (lines[i].toLowerCase().includes('1 and more')) {
                const priceMatch = lines[i].match(/([\d\s.,]+)\s*€/);
                if (priceMatch) return { price: priceMatch[1], source: 'line-match' };
                if (lines[i + 1]) {
                    const nextMatch = lines[i + 1].match(/([\d\s.,]+)\s*€/);
                    if (nextMatch) return { price: nextMatch[1], source: 'next-line' };
                }
            }
        }

        // =====================================================
        // PRIORITY 2: Price below RRP (B2B is the non-RRP price)
        // Format: "RRP 26,63 €" then "17,31 €" on next line
        // =====================================================

        const hasRRP = pageText.includes('RRP') || pageText.includes('UVP');

        if (hasRRP) {
            // Find RRP line, then get the NEXT price (which is B2B)
            for (let i = 0; i < lines.length; i++) {
                if (lines[i].includes('RRP') || lines[i].includes('UVP')) {
                    // Look at next few lines for a price that's NOT RRP
                    for (let j = i + 1; j < Math.min(i + 5, lines.length); j++) {
                        const line = lines[j].trim();
                        // Skip if it's another RRP line or empty
                        if (line.includes('RRP') || line.includes('UVP') || line.length < 3) continue;
                        // Skip "5 and more" bulk discounts
                        if (line.includes('and more') || line.includes('oder mehr')) continue;
                        // Match price: "17,31 €" or "17,31 € (17,31 € incl. VAT)"
                        const priceMatch = line.match(/^([\d\s.,]+)\s*€/);
                        if (priceMatch) {
                            return { price: priceMatch[1], source: 'below-rrp' };
                        }
                    }
                }
            }
        }

        // =====================================================
        // PRIORITY 3: Direct price (no RRP on page)
        // =====================================================

        if (!hasRRP) {
            const priceElements = document.querySelectorAll('.price, .woocommerce-Price-amount, [class*="price"]');
            for (const el of priceElements) {
                const text = el.innerText.trim();
                const match = text.match(/^([\d\s.,]+)\s*€/);
                if (match) {
                    return { price: match[1], source: 'direct-price' };
                }
            }

            // Fallback: Find first price on page
            const directMatch = pageText.match(/^[\s\S]*?([\d]{1,3}(?:[\s.,]\d{2,3})*[.,]\d{2})\s*€\s*\(/m);
            if (directMatch) {
                return { price: directMatch[1], source: 'direct-fallback' };
            }
        }

        return null;
    });

    if (priceData) {
        const price = parseEuropeanPrice(priceData.price);
        if (price && price > 0) {
            return price;
        }
    }

    return null;
}

/**
 * Extract price from dropdown option text
 * For products like "ij3 = small short = 95,93 €"
 */
function extractPriceFromOptionText(optionText) {
    // Pattern: "= 95,93 €" or "= 95,93€"
    const match = optionText.match(/=\s*([\d\s.,]+)\s*€/i);
    if (match) {
        return parseEuropeanPrice(match[1]);
    }
    return null;
}

/**
 * Wait for B2B price to appear after selecting variant(s)
 */
async function waitForB2BPrice(page, maxWait = 5000) {
    const startTime = Date.now();
    while (Date.now() - startTime < maxWait) {
        const price = await extractB2BPrice(page);
        if (price && price > 0) {
            return price;
        }
        await delay(300);
    }
    return null;
}

/**
 * Snigel color name to image code mapping
 * Based on observed patterns in Snigel's product images
 */
const SNIGEL_COLOR_CODES = {
    'black': ['a01', 'black', 'blk', 'schwarz'],
    'grey': ['a09', 'grey', 'gray', 'grau'],
    'olive': ['a17', 'olive', 'oliv', 'green'],
    'multicam': ['a03', 'multicam', 'mc', 'camo'],
    'tan': ['a05', 'tan', 'coyote', 'khaki'],
    'ranger green': ['a02', 'ranger', 'rg'],
    'white': ['a00', 'white', 'weiss'],
    'navy': ['a08', 'navy', 'blue'],
};

/**
 * Capture color-specific product image URL
 * IMPROVED: Searches thumbnails for matching color code and returns full-size URL
 *
 * @param {Page} page - Playwright page
 * @param {string} colorName - The selected color name (e.g., "Black", "Grey")
 * @returns {string|null} - Image URL or null
 */
async function captureMainProductImage(page, colorName = null) {
    // If color name provided, search thumbnails for matching color image
    if (colorName) {
        const colorLower = colorName.toLowerCase();
        const colorPatterns = SNIGEL_COLOR_CODES[colorLower] || [colorLower];

        // Find thumbnail URL matching the color pattern
        const matchingImage = await page.evaluate((patterns) => {
            // Get all thumbnail images in gallery (Snigel uses .thumbnails)
            const thumbnails = document.querySelectorAll(
                '.thumbnails img, ' +
                '.woocommerce-product-gallery__image img, ' +
                '.product-thumbnails img, ' +
                '.gallery-thumbnails img'
            );

            for (const thumb of thumbnails) {
                const thumbSrc = (thumb.src || thumb.getAttribute('data-src') || '').toLowerCase();
                const parentHref = thumb.closest('a')?.href?.toLowerCase() || '';

                // Check if this thumbnail matches any of our color patterns
                for (const pattern of patterns) {
                    if (thumbSrc.includes(pattern) || parentHref.includes(pattern)) {
                        // Found matching thumbnail - get full size URL
                        // Parent <a> href usually has full size, or use data-large_image
                        const fullSizeUrl = thumb.closest('a')?.href ||
                                          thumb.getAttribute('data-large_image') ||
                                          thumb.src;

                        // Remove thumbnail size suffix (e.g., -uai-720x720, -150x150, etc.)
                        const cleanUrl = fullSizeUrl.replace(/-uai-\d+x\d+/i, '')
                                                   .replace(/-\d+x\d+/i, '');

                        return { found: true, url: cleanUrl, pattern: pattern };
                    }
                }
            }
            return { found: false };
        }, colorPatterns);

        if (matchingImage.found) {
            return matchingImage.url;
        }
    }

    // Fallback: capture the main product image (whatever is currently shown)
    return await page.evaluate(() => {
        // WooCommerce product gallery selectors (in order of specificity)
        const selectors = [
            '.woocommerce-product-gallery__image--active img',
            '.woocommerce-product-gallery__image:first-child img',
            '.flex-active-slide img',
            '.woocommerce-product-gallery img.wp-post-image',
            '.woocommerce-product-gallery__wrapper img:first-of-type',
            '.product-images img:first-of-type',
            '.single-product-main-image img',
            'figure.woocommerce-product-gallery__wrapper img',
            '.attachment-shop_single',
            '.wp-post-image'
        ];

        for (const selector of selectors) {
            const img = document.querySelector(selector);
            if (img) {
                // Get full resolution image (data-large_image or src)
                return img.getAttribute('data-large_image') ||
                       img.getAttribute('data-src') ||
                       img.src;
            }
        }
        return null;
    });
}

// ============================================================
// PLAYWRIGHT - LOGIN
// ============================================================

/**
 * Login to Snigel B2B portal
 */
async function loginToSnigel(page) {
    console.log('  Logging in to Snigel B2B portal...');

    await page.goto(`${CONFIG.snigel.baseUrl}/my-account/`, {
        waitUntil: 'domcontentloaded'
    });
    await delay(2000);

    // Dismiss cookie popup if present
    try {
        await page.click('button:has-text("Ok")', { timeout: 3000 });
    } catch (e) {
        // No popup, continue
    }

    // Fill login form
    await page.fill('#username', CONFIG.snigel.username);
    await page.fill('#password', CONFIG.snigel.password);
    await page.click('button[name="login"]');

    // Wait for login to complete
    await page.waitForURL('**/my-account/**', { timeout: 30000 });
    await delay(2000);

    console.log('  ✓ Login successful!');
}

// ============================================================
// PLAYWRIGHT - SCRAPE PRODUCT (MULTI-DROPDOWN SUPPORT)
// ============================================================

/**
 * Detect ALL variant dropdowns on the page (DYNAMIC)
 * Extracts dropdown info from actual page structure, not hardcoded patterns
 */
async function detectAllDropdowns(page) {
    const rawDropdowns = await page.evaluate(() => {
        const dropdowns = [];

        // Find all selects in variations table or with attribute_pa in name
        const selects = document.querySelectorAll('table.variations select, select[name*="attribute_pa"], .variations select');

        selects.forEach(select => {
            const name = select.name || select.id || '';
            const options = [];

            select.querySelectorAll('option').forEach(opt => {
                if (opt.value && opt.value !== '' &&
                    !opt.textContent.toLowerCase().includes('choose') &&
                    !opt.textContent.toLowerCase().includes('select') &&
                    !opt.textContent.toLowerCase().includes('wählen') &&
                    !opt.textContent.toLowerCase().includes('option')) {
                    options.push({
                        name: opt.textContent.trim(),
                        value: opt.value
                    });
                }
            });

            if (options.length > 0) {
                // Try to get visible label from the page
                let visibleLabel = null;

                // Method 1: Check label element
                const labelEl = document.querySelector(`label[for="${select.id}"]`);
                if (labelEl) {
                    visibleLabel = labelEl.textContent.trim();
                }

                // Method 2: Check parent row for label (table structure)
                if (!visibleLabel) {
                    const row = select.closest('tr');
                    if (row) {
                        const th = row.querySelector('th, td.label');
                        if (th) {
                            visibleLabel = th.textContent.trim();
                        }
                    }
                }

                // Method 3: Check data-attribute_name
                if (!visibleLabel && select.dataset.attributeName) {
                    visibleLabel = select.dataset.attributeName.replace('attribute_pa_', '');
                }

                dropdowns.push({
                    selector: `select[name="${name}"]`,
                    attrName: name,
                    visibleLabel: visibleLabel,
                    options: options
                });
            }
        });

        return dropdowns;
    });

    // Process dropdowns and add German labels
    return rawDropdowns.map(d => {
        const typeName = extractTypeName(d.attrName);
        const germanLabel = getGermanLabel(typeName);

        return {
            selector: d.selector,
            name: d.attrName,
            type: typeName,
            label: germanLabel,
            visibleLabel: d.visibleLabel || typeName,
            options: d.options
        };
    });
}

/**
 * Generate all combinations for multi-dropdown products
 * E.g., 3 colors × 2 sizes = 6 combinations
 */
function generateCombinations(dropdowns) {
    if (dropdowns.length === 0) return [];
    if (dropdowns.length === 1) {
        return dropdowns[0].options.map(opt => [{
            dropdown: dropdowns[0],
            option: opt
        }]);
    }

    // Multiple dropdowns - generate combinations
    const combinations = [];

    function combine(index, current) {
        if (index === dropdowns.length) {
            combinations.push([...current]);
            return;
        }

        for (const option of dropdowns[index].options) {
            current.push({
                dropdown: dropdowns[index],
                option: option
            });
            combine(index + 1, current);
            current.pop();
        }
    }

    combine(0, []);
    return combinations;
}

/**
 * Scrape B2B prices for a single product
 * Supports multiple dropdowns (Color + Size combinations)
 */
async function scrapeProductPrices(page, product) {
    const result = {
        name: product.name,
        slug: product.slug,
        url: product.url,
        dropdownTypes: [],
        variantLabels: [],
        hasVariants: false,
        isMultiDropdown: false,
        variants: [],
        error: null
    };

    try {
        // Navigate to product page with exponential backoff retry
        let loaded = false;
        for (let attempt = 1; attempt <= CONFIG.maxRetries; attempt++) {
            try {
                // Clear any stale state
                if (attempt > 1) {
                    try {
                        await page.goto('about:blank', { timeout: 5000 });
                        await delay(500);
                    } catch (e) { /* ignore */ }
                }

                // Load page with longer timeout
                await page.goto(product.url, {
                    waitUntil: 'domcontentloaded',
                    timeout: CONFIG.pageTimeout
                });

                // Wait for page content (price or dropdown)
                try {
                    await page.waitForSelector('select, .price, .woocommerce-Price-amount, [class*="price"], .summary', {
                        timeout: 10000
                    });
                    loaded = true;
                    break;
                } catch (selectorError) {
                    // Fallback 1: check if page has € symbol
                    const hasPrice = await page.evaluate(() => document.body.innerText.includes('€'));
                    if (hasPrice) {
                        loaded = true;
                        break;
                    }
                    // Fallback 2: check if product title exists
                    const hasTitle = await page.evaluate(() =>
                        document.querySelector('.product_title, h1') !== null
                    );
                    if (hasTitle) {
                        loaded = true;
                        break;
                    }
                }
            } catch (e) {
                const retryDelay = CONFIG.retryDelays[attempt - 1] || 10000;
                if (attempt < CONFIG.maxRetries) {
                    console.log(`      Retry ${attempt}/${CONFIG.maxRetries} (waiting ${retryDelay/1000}s)...`);
                    await delay(retryDelay);
                }
            }
        }

        if (!loaded) {
            result.error = 'Page failed to load after retries';
            console.log('    → ✗ Page failed to load after retries');
            return result;
        }

        // Simulate human behavior (scroll)
        await simulateHumanBehavior(page);

        // Detect ALL dropdowns
        const dropdowns = await detectAllDropdowns(page);

        if (dropdowns.length === 0) {
            // Simple product (no variants)
            const b2bPrice = await waitForB2BPrice(page, 3000) || await extractB2BPrice(page);

            if (b2bPrice && b2bPrice > 0) {
                const sellingPrice = await calculateSellingPrice(b2bPrice);
                const purchasePrice = await calculatePurchasePrice(b2bPrice);

                result.variants.push({
                    name: 'Default',
                    value: 'default',
                    combination: null,
                    b2bPriceEUR: b2bPrice,
                    purchasePriceCHF: purchasePrice,
                    sellingPriceCHF: sellingPrice
                });

                console.log(`    → Simple: €${b2bPrice.toFixed(2)} → CHF ${sellingPrice.toFixed(2)}`);
            } else {
                result.error = 'No B2B price found';
                console.log('    → ⚠ No B2B price found (simple product)');
            }
        } else if (dropdowns.length === 1) {
            // Single dropdown (Color OR Size OR Parts)
            result.hasVariants = true;
            result.dropdownTypes = [dropdowns[0].type];
            result.variantLabels = [dropdowns[0].label];

            console.log(`    → ${dropdowns[0].options.length} ${dropdowns[0].label} variants`);

            for (const option of dropdowns[0].options) {
                try {
                    // First check if price is embedded in option text (e.g., "ij3 = small short = 95,93 €")
                    let b2bPrice = extractPriceFromOptionText(option.name);

                    if (!b2bPrice) {
                        // Check if option is available
                        const isAvailable = await page.evaluate(({selector, value}) => {
                            const select = document.querySelector(selector);
                            if (!select) return false;
                            const opt = select.querySelector(`option[value="${value}"]`);
                            return opt && !opt.disabled;
                        }, {selector: dropdowns[0].selector, value: option.value});

                        if (!isAvailable) {
                            console.log(`      ${option.name}: ⊘ Not available`);
                            continue;
                        }

                        // No embedded price, select option and extract from page
                        await page.selectOption(dropdowns[0].selector, option.value);
                        await delay(1000);
                        b2bPrice = await waitForB2BPrice(page, 4000);
                    }

                    if (b2bPrice && b2bPrice > 0) {
                        const sellingPrice = await calculateSellingPrice(b2bPrice);
                        const purchasePrice = await calculatePurchasePrice(b2bPrice);

                        // Clean option name (remove price if embedded)
                        let cleanName = option.name;
                        if (option.name.includes('=') && option.name.includes('€')) {
                            // Extract just the size part: "ij3 = small short = 95,93 €" → "small short"
                            const parts = option.name.split('=');
                            if (parts.length >= 2) {
                                cleanName = parts[1].trim().replace(/\s*[\d.,]+\s*€.*$/, '').trim();
                            }
                        }

                        // Capture image URL for color variants
                        let imageUrl = null;
                        if (dropdowns[0].type.toLowerCase() === 'colour') {
                            imageUrl = await captureMainProductImage(page, cleanName);
                        }

                        const variantData = {
                            name: cleanName,
                            value: option.value,
                            originalText: option.name,
                            [dropdowns[0].type.toLowerCase()]: cleanName,
                            b2bPriceEUR: b2bPrice,
                            purchasePriceCHF: purchasePrice,
                            sellingPriceCHF: sellingPrice
                        };

                        if (imageUrl) {
                            variantData.imageUrl = imageUrl;
                        }

                        result.variants.push(variantData);

                        console.log(`      ${cleanName}: €${b2bPrice.toFixed(2)} → CHF ${sellingPrice.toFixed(2)}${imageUrl ? ' 📷' : ''}`);
                    } else {
                        console.log(`      ${option.name}: ⚠ No B2B price`);
                    }
                } catch (e) {
                    console.log(`      ${option.name}: ✗ ${e.message.substring(0, 50)}`);
                }
            }
        } else {
            // MULTIPLE DROPDOWNS (e.g., Color + Size)
            // SMART: Select first dropdown, then re-read second dropdown for available options
            result.hasVariants = true;
            result.isMultiDropdown = true;
            result.dropdownTypes = dropdowns.map(d => d.type);
            result.variantLabels = dropdowns.map(d => d.label);

            const firstDropdown = dropdowns[0];
            console.log(`    → ${dropdowns.length} dropdowns: ${dropdowns.map(d => `${d.label}(${d.options.length})`).join(' + ')} (dynamic)`);

            // Iterate through FIRST dropdown options
            for (const firstOption of firstDropdown.options) {
                try {
                    // Check if option is available (not disabled/out of stock)
                    const isAvailable = await page.evaluate(({selector, value}) => {
                        const select = document.querySelector(selector);
                        if (!select) return false;
                        const option = select.querySelector(`option[value="${value}"]`);
                        return option && !option.disabled && !option.classList.contains('out-of-stock');
                    }, {selector: firstDropdown.selector, value: firstOption.value});

                    if (!isAvailable) {
                        console.log(`      ${firstOption.name}: ⊘ Not available`);
                        continue;
                    }

                    // Select first dropdown
                    await page.selectOption(firstDropdown.selector, firstOption.value);
                    await delay(800);

                    // Capture image URL for color variants (after selecting color)
                    let colorImageUrl = null;
                    if (firstDropdown.type.toLowerCase() === 'colour') {
                        colorImageUrl = await captureMainProductImage(page, firstOption.name);
                        if (colorImageUrl) {
                            console.log(`      ${firstOption.name}: 📷 Image captured`);
                        }
                    }

                    // Re-read AVAILABLE options from remaining dropdowns
                    const availableSecondOptions = await page.evaluate((selectors) => {
                        const results = [];
                        // Skip first selector, get remaining
                        for (let i = 1; i < selectors.length; i++) {
                            const select = document.querySelector(selectors[i]);
                            if (select) {
                                const options = [];
                                select.querySelectorAll('option').forEach(opt => {
                                    // Only include enabled, non-placeholder options
                                    if (opt.value && opt.value !== '' &&
                                        !opt.disabled &&
                                        !opt.textContent.toLowerCase().includes('choose') &&
                                        !opt.textContent.toLowerCase().includes('select')) {
                                        options.push({
                                            name: opt.textContent.trim(),
                                            value: opt.value
                                        });
                                    }
                                });
                                results.push({ selector: selectors[i], options });
                            }
                        }
                        return results;
                    }, dropdowns.map(d => d.selector));

                    // If only 2 dropdowns, iterate second dropdown's available options
                    if (dropdowns.length === 2 && availableSecondOptions.length > 0) {
                        const secondDropdown = dropdowns[1];
                        const availableOpts = availableSecondOptions[0].options;

                        console.log(`      ${firstOption.name}: ${availableOpts.length} sizes available`);

                        for (const secondOption of availableOpts) {
                            try {
                                await page.selectOption(secondDropdown.selector, secondOption.value);
                                await delay(600);

                                const b2bPrice = await waitForB2BPrice(page, 3000);
                                const comboName = `${firstOption.name} / ${secondOption.name}`;

                                if (b2bPrice && b2bPrice > 0) {
                                    const sellingPrice = await calculateSellingPrice(b2bPrice);
                                    const purchasePrice = await calculatePurchasePrice(b2bPrice);

                                    const variantData = {
                                        name: comboName,
                                        combination: [
                                            { type: firstDropdown.type, label: firstDropdown.label, value: firstOption.value, name: firstOption.name },
                                            { type: secondDropdown.type, label: secondDropdown.label, value: secondOption.value, name: secondOption.name }
                                        ],
                                        b2bPriceEUR: b2bPrice,
                                        purchasePriceCHF: purchasePrice,
                                        sellingPriceCHF: sellingPrice
                                    };
                                    variantData[firstDropdown.type.toLowerCase()] = firstOption.name;
                                    variantData[secondDropdown.type.toLowerCase()] = secondOption.name;

                                    // Add image URL if captured for this color
                                    if (colorImageUrl) {
                                        variantData.imageUrl = colorImageUrl;
                                    }

                                    result.variants.push(variantData);
                                    console.log(`        ${comboName}: €${b2bPrice.toFixed(2)} → CHF ${sellingPrice.toFixed(2)}`);
                                } else {
                                    console.log(`        ${comboName}: ⚠ No price`);
                                }
                            } catch (e) {
                                console.log(`        ${firstOption.name}/${secondOption.name}: ✗ ${e.message.substring(0, 30)}`);
                            }
                        }
                    } else {
                        // 3+ dropdowns - use recursive approach (rare case)
                        // For now, just get price after selecting first option
                        const b2bPrice = await waitForB2BPrice(page, 3000);
                        if (b2bPrice && b2bPrice > 0) {
                            const sellingPrice = await calculateSellingPrice(b2bPrice);
                            const purchasePrice = await calculatePurchasePrice(b2bPrice);
                            result.variants.push({
                                name: firstOption.name,
                                [firstDropdown.type.toLowerCase()]: firstOption.name,
                                b2bPriceEUR: b2bPrice,
                                purchasePriceCHF: purchasePrice,
                                sellingPriceCHF: sellingPrice
                            });
                            console.log(`      ${firstOption.name}: €${b2bPrice.toFixed(2)} → CHF ${sellingPrice.toFixed(2)}`);
                        }
                    }
                } catch (e) {
                    console.log(`      ${firstOption.name}: ✗ ${e.message.substring(0, 40)}`);
                }
            }
        }

    } catch (error) {
        result.error = error.message;
        console.log(`    → ✗ Error: ${error.message.substring(0, 80)}`);
    }

    return result;
}

// ============================================================
// CLI COMMAND: COMPARE
// ============================================================

/**
 * Compare mode: Extract B2B prices and save to JSON
 */
async function compareMode(singleProductSlug = null, retryFailed = false, startFrom = 0) {
    console.log('\n' + '═'.repeat(60));
    console.log('  SNIGEL B2B PRICE SYNC - COMPARE MODE');
    if (startFrom > 0) {
        console.log(`  (Resuming from product ${startFrom})`);
    }
    console.log('═'.repeat(60));

    // Load product list
    const productsPath = path.join(__dirname, 'snigel-data', 'products-with-variants.json');
    if (!fs.existsSync(productsPath)) {
        console.error('\n  ✗ Product list not found:', productsPath);
        console.error('  Run snigel-variants-scraper.js first.');
        process.exit(1);
    }

    let products = JSON.parse(fs.readFileSync(productsPath, 'utf8'));

    // Load existing results for retry-failed or merging
    const outputPath = path.join(CONFIG.outputDir, CONFIG.outputFiles.prices);
    let existingResults = [];
    if (fs.existsSync(outputPath)) {
        existingResults = JSON.parse(fs.readFileSync(outputPath, 'utf8'));
    }

    // Filter to single product if specified
    if (singleProductSlug) {
        products = products.filter(p => p.slug === singleProductSlug);
        if (products.length === 0) {
            console.error(`\n  ✗ Product not found: ${singleProductSlug}`);
            process.exit(1);
        }
        console.log(`\n  Processing single product: ${products[0].name}`);
    } else if (retryFailed) {
        // Find failed products from existing results
        const failedSlugs = existingResults
            .filter(r => r.error || r.variants.length === 0 ||
                        r.variants.some(v => !v.b2bPriceEUR || v.b2bPriceEUR === 0))
            .map(r => r.slug);

        // ALSO find products that are NOT in results at all (never processed)
        const existingSlugs = existingResults.map(r => r.slug);
        const missingSlugs = products
            .filter(p => !existingSlugs.includes(p.slug))
            .map(p => p.slug);

        const allRetryableSlugs = [...new Set([...failedSlugs, ...missingSlugs])];

        if (allRetryableSlugs.length === 0) {
            console.log('\n  ✓ No failed products to retry!');
            console.log(`  All ${existingResults.length} products have prices.`);
            return;
        }

        products = products.filter(p => allRetryableSlugs.includes(p.slug));
        console.log(`\n  Retrying ${products.length} products:`);
        console.log(`    - ${failedSlugs.length} with errors`);
        console.log(`    - ${missingSlugs.length} never processed`);
        if (products.length <= 20) {
            products.forEach(p => console.log(`    • ${p.name}`));
        }
    } else {
        console.log(`\n  Loaded ${products.length} products`);
    }

    // Launch browser with stealth settings
    const browser = await chromium.launch({
        headless: false,  // Show browser so user can login manually
        args: [
            '--disable-blink-features=AutomationControlled',
            '--disable-dev-shm-usage',
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-accelerated-2d-canvas',
            '--disable-gpu'
        ]
    });

    // Create context with realistic browser fingerprint
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        viewport: { width: 1920, height: 1080 },
        locale: 'de-DE',
        timezoneId: 'Europe/Zurich',
        extraHTTPHeaders: {
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language': 'de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding': 'gzip, deflate, br',
            'Connection': 'keep-alive',
            'Upgrade-Insecure-Requests': '1',
            'Sec-Fetch-Dest': 'document',
            'Sec-Fetch-Mode': 'navigate',
            'Sec-Fetch-Site': 'same-origin',
            'Sec-Fetch-User': '?1',
            'Cache-Control': 'max-age=0'
        }
    });

    // Block heavy resources but keep essential ones
    await context.route('**/*', (route) => {
        const resourceType = route.request().resourceType();
        const url = route.request().url();

        // Block images, fonts, media, and tracking
        if (['image', 'font', 'media'].includes(resourceType)) {
            route.abort();
        } else if (
            url.includes('analytics') ||
            url.includes('tracker') ||
            url.includes('facebook') ||
            url.includes('google-analytics') ||
            url.includes('googletagmanager') ||
            url.includes('hotjar') ||
            url.includes('doubleclick') ||
            url.includes('ads') ||
            url.includes('.gif') ||
            url.includes('.png') ||
            url.includes('.jpg') ||
            url.includes('.webp')
        ) {
            route.abort();
        } else {
            route.continue();
        }
    });

    const page = await context.newPage();
    page.setDefaultTimeout(CONFIG.pageTimeout);

    // Add stealth script to avoid detection
    await page.addInitScript(() => {
        // Override navigator properties to look more human
        Object.defineProperty(navigator, 'webdriver', { get: () => false });
        Object.defineProperty(navigator, 'plugins', { get: () => [1, 2, 3, 4, 5] });
        Object.defineProperty(navigator, 'languages', { get: () => ['de-DE', 'de', 'en-US', 'en'] });
    });

    try {
        // Login
        await loginToSnigel(page);

        // Scrape products
        console.log('\n  Extracting B2B prices...\n');

        const results = [];
        let processed = 0;
        let withPrices = 0;
        let errors = 0;
        let consecutiveFailures = 0;

        // Helper function to verify and refresh session (lenient - don't break on timeout)
        async function verifySession(page) {
            try {
                console.log('    🔄 Verifying session...');

                // Try to check session, but don't fail if server is slow
                try {
                    await page.goto(`${CONFIG.snigel.baseUrl}/my-account/`, {
                        waitUntil: 'domcontentloaded',
                        timeout: 45000  // Longer timeout
                    });
                } catch (navError) {
                    // Navigation failed - server might be slow, just continue
                    console.log('    ⚠ Session check timed out - continuing anyway');
                    await delay(5000); // Wait a bit before continuing
                    return; // Don't try to re-login, just continue
                }

                await delay(1500);

                // Check if still logged in (should see account page, not login form)
                const isLoggedIn = await page.evaluate(() => {
                    const pageText = document.body.innerText;
                    // If we see login form, we're logged out
                    if (document.querySelector('#username') && document.querySelector('#password')) {
                        return false;
                    }
                    // If we see account menu, we're logged in
                    return pageText.includes('My Account') || pageText.includes('Log out') ||
                           pageText.includes('Logout') || pageText.includes('Dashboard');
                });

                if (!isLoggedIn) {
                    console.log('    ⚠ Session expired, re-logging in...');
                    // Retry login up to 3 times
                    for (let attempt = 1; attempt <= 3; attempt++) {
                        try {
                            await loginToSnigel(page);
                            break; // Success
                        } catch (loginError) {
                            if (attempt < 3) {
                                console.log(`    ⚠ Login attempt ${attempt} failed, retrying in 10s...`);
                                await delay(10000);
                            } else {
                                console.log('    ⚠ Login failed after 3 attempts - continuing anyway');
                            }
                        }
                    }
                } else {
                    console.log('    ✓ Session active');
                }
                await delay(2000);
            } catch (e) {
                // Don't try to re-login on error - just continue
                console.log('    ⚠ Session check error - continuing anyway');
                await delay(5000);
            }
        }

        for (const product of products) {
            processed++;

            // Skip products before startFrom index
            if (startFrom > 0 && processed < startFrom) {
                // Copy existing result if available
                const existingResult = existingResults.find(r => r.slug === product.slug);
                if (existingResult) {
                    results.push(existingResult);
                }
                continue;
            }

            // Periodic session health check
            if (processed > 1 && processed % CONFIG.sessionRefreshEvery === 0) {
                await verifySession(page);
            }

            console.log(`  [${processed}/${products.length}] ${product.name.substring(0, 50)}`);

            const result = await scrapeProductPrices(page, product);
            results.push(result);

            if (result.variants.length > 0) {
                withPrices++;
                consecutiveFailures = 0; // Reset on success
            } else {
                errors++;
                consecutiveFailures++;

                // If 3+ consecutive failures, verify session and pause
                if (consecutiveFailures >= 3) {
                    console.log('    ⏸ Multiple failures - verifying session...');
                    await verifySession(page);
                    await delay(5000);
                    consecutiveFailures = 0;
                }
            }

            // Human-like delay between products (2-4 seconds with jitter)
            await productDelay();

            // Save progress every 20 products (to not lose work)
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

        // Save results (merge with existing if retry-failed OR single product)
        let finalResults = results;
        if ((retryFailed || singleProductSlug) && existingResults.length > 0) {
            // Merge: replace old results with new ones for retried/single products
            const newSlugs = results.map(r => r.slug);
            // Update existing entries
            finalResults = existingResults.map(existing => {
                const newResult = results.find(r => r.slug === existing.slug);
                return newResult || existing;
            });
            // Add any new products not in existing results
            results.forEach(r => {
                if (!existingResults.find(e => e.slug === r.slug)) {
                    finalResults.push(r);
                }
            });
            console.log(`\n  Merged ${results.length} products with existing ${existingResults.length} results`);
        }
        fs.writeFileSync(outputPath, JSON.stringify(finalResults, null, 2));

        // Summary
        console.log('\n' + '═'.repeat(60));
        console.log('  EXTRACTION COMPLETE');
        console.log('═'.repeat(60));
        console.log(`  Total products: ${processed}`);
        console.log(`  With B2B prices: ${withPrices}`);
        console.log(`  Errors: ${errors}`);
        console.log(`  Output: ${outputPath}`);
        console.log('═'.repeat(60));

    } catch (error) {
        console.error('\n  ✗ Error:', error.message);
    } finally {
        await browser.close();
    }
}

// ============================================================
// CLI COMMAND: DRY-RUN
// ============================================================

/**
 * Dry-run mode: Show what would be updated without making changes
 */
async function dryRunMode() {
    console.log('\n' + '═'.repeat(60));
    console.log('  DRY RUN - SHOWING WHAT WOULD BE UPDATED');
    console.log('═'.repeat(60));

    const pricesPath = path.join(CONFIG.outputDir, CONFIG.outputFiles.prices);
    if (!fs.existsSync(pricesPath)) {
        console.error('\n  ✗ No price data found. Run --compare first.');
        process.exit(1);
    }

    const products = JSON.parse(fs.readFileSync(pricesPath, 'utf8'));
    console.log(`\n  Loaded ${products.length} products with price data\n`);

    let updateCount = 0;

    for (const product of products) {
        if (product.variants.length === 0 || product.error) continue;

        console.log(`\n  ${product.name}`);
        console.log(`    Variant type: ${product.variantLabel || 'Simple Product'}`);

        for (const variant of product.variants) {
            console.log(`    ${variant.name}:`);
            console.log(`      B2B EUR:      €${variant.b2bPriceEUR.toFixed(2)}`);
            console.log(`      Purchase CHF: CHF ${variant.purchasePriceCHF.toFixed(2)}`);
            console.log(`      Selling CHF:  CHF ${variant.sellingPriceCHF.toFixed(2)}`);
            updateCount++;
        }
    }

    console.log('\n' + '═'.repeat(60));
    console.log(`  Would update ${updateCount} prices`);
    console.log('  To apply changes, run: node snigel-b2b-sync.js --update');
    console.log('═'.repeat(60));
}

// ============================================================
// CLI COMMAND: UPDATE
// ============================================================

/**
 * Update mode: Apply changes to Shopware
 */
async function updateMode(singleProductSlug = null) {
    console.log('\n' + '═'.repeat(60));
    console.log('  UPDATING SHOPWARE PRICES');
    console.log('═'.repeat(60));

    const pricesPath = path.join(CONFIG.outputDir, CONFIG.outputFiles.prices);
    if (!fs.existsSync(pricesPath)) {
        console.error('\n  ✗ No price data found. Run --compare first.');
        process.exit(1);
    }

    let products = JSON.parse(fs.readFileSync(pricesPath, 'utf8'));

    // Filter to single product if specified
    if (singleProductSlug) {
        products = products.filter(p => p.slug === singleProductSlug);
        if (products.length === 0) {
            console.error(`\n  ✗ Product not found: ${singleProductSlug}`);
            process.exit(1);
        }
    }

    // Get Shopware API token
    console.log('\n  Getting Shopware API token...');
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
        console.error('  Response:', JSON.stringify(tokenRes.data).substring(0, 500));
        process.exit(1);
    }
    const token = tokenRes.data.access_token;
    console.log('  ✓ Token obtained\n');

    // Update products
    const log = [];
    let updated = 0, failed = 0, notFound = 0;

    for (const product of products) {
        if (product.variants.length === 0 || product.error) {
            console.log(`  ✗ ${product.name} - No price data`);
            continue;
        }

        console.log(`  Updating: ${product.name}`);

        // Search for product by name
        const searchRes = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: {
                limit: 5,
                filter: [
                    { type: 'contains', field: 'name', value: product.name }
                ]
            }
        });

        if (!searchRes.data.data || searchRes.data.data.length === 0) {
            console.log(`    ✗ Not found in Shopware`);
            notFound++;
            continue;
        }

        const shopwareProduct = searchRes.data.data[0];
        const productId = shopwareProduct.id;

        // For simple products, update main price
        // For variant products, we'll update the main product price with first variant
        const mainVariant = product.variants[0];

        const updatePayload = {
            price: [{
                currencyId: CONFIG.shopware.currencyId,
                gross: mainVariant.sellingPriceCHF,
                net: Math.round(mainVariant.sellingPriceCHF / CONFIG.pricing.vat * 100) / 100,
                linked: false
            }],
            purchasePrices: [{
                currencyId: CONFIG.shopware.currencyId,
                gross: mainVariant.purchasePriceCHF,
                net: Math.round(mainVariant.purchasePriceCHF / CONFIG.pricing.vat * 100) / 100,
                linked: false
            }],
            customFields: {
                snigel_variant_type: product.variantLabel,
                snigel_variants: JSON.stringify(product.variants)
            }
        };

        const updateRes = await httpRequest(`${CONFIG.shopware.apiUrl}/product/${productId}`, {
            method: 'PATCH',
            headers: { 'Authorization': `Bearer ${token}` },
            body: updatePayload
        });

        if (updateRes.status === 204 || updateRes.status === 200) {
            console.log(`    ✓ Updated: CHF ${mainVariant.sellingPriceCHF.toFixed(2)}`);
            updated++;

            log.push({
                timestamp: new Date().toISOString(),
                productId: productId,
                name: product.name,
                variantType: product.variantLabel,
                variants: product.variants,
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

        await delay(300); // Rate limiting
    }

    // Save log
    const logPath = path.join(CONFIG.outputDir, CONFIG.outputFiles.log);
    fs.writeFileSync(logPath, JSON.stringify(log, null, 2));

    // Summary
    console.log('\n' + '═'.repeat(60));
    console.log('  UPDATE COMPLETE');
    console.log('═'.repeat(60));
    console.log(`  ✓ Updated:   ${updated} products`);
    console.log(`  ✗ Failed:    ${failed} products`);
    console.log(`  ? Not found: ${notFound} products`);
    console.log(`  Log saved: ${logPath}`);
    console.log('═'.repeat(60));
}

// ============================================================
// MAIN
// ============================================================

async function main() {
    const args = process.argv.slice(2);

    // Parse arguments
    let mode = '--compare';
    let productSlug = null;
    let retryFailed = false;
    let startFrom = 0;

    for (let i = 0; i < args.length; i++) {
        if (args[i] === '--compare' || args[i] === '--dry-run' || args[i] === '--update') {
            mode = args[i];
        } else if (args[i] === '--product' && args[i + 1]) {
            productSlug = args[i + 1];
            i++; // Skip next arg
        } else if (args[i] === '--retry-failed' || args[i] === '--retry') {
            retryFailed = true;
        } else if (args[i] === '--start-from' && args[i + 1]) {
            startFrom = parseInt(args[i + 1], 10);
            i++; // Skip next arg
        }
    }

    console.log('\n' + '╔' + '═'.repeat(58) + '╗');
    console.log('║' + '  SNIGEL B2B PRICE SYNC TOOL'.padEnd(58) + '║');
    console.log('║' + '  Formula: B2B × 1.5 × 1.08 × ExchangeRate = CHF'.padEnd(58) + '║');
    console.log('╚' + '═'.repeat(58) + '╝');

    try {
        if (mode === '--compare') {
            await compareMode(productSlug, retryFailed, startFrom);
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

// Run
main();
