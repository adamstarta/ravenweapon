/**
 * Compare prices between shop.ravenweapon.ch and ortak.ch
 */

const https = require('https');
const http = require('http');

const SHOPWARE_URL = 'https://ortak.ch';
const CHF_CURRENCY_ID = '0191c12cf40d718a8a3439b74a6f083c';

// Function to make HTTP requests
function makeRequest(url, options = {}) {
    return new Promise((resolve, reject) => {
        const isHttps = url.startsWith('https');
        const lib = isHttps ? https : http;
        const urlObj = new URL(url);

        const reqOptions = {
            hostname: urlObj.hostname,
            port: urlObj.port || (isHttps ? 443 : 80),
            path: urlObj.pathname + urlObj.search,
            method: options.method || 'GET',
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept': 'application/json',
                ...options.headers
            }
        };

        const req = lib.request(reqOptions, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try {
                    resolve({ status: res.statusCode, data: JSON.parse(data), raw: data });
                } catch (e) {
                    resolve({ status: res.statusCode, data: data, raw: data });
                }
            });
        });

        req.on('error', reject);
        if (options.body) req.write(options.body);
        req.end();
    });
}

// Get Shopware API token
async function getShopwareToken() {
    const response = await makeRequest(`${SHOPWARE_URL}/api/oauth/token`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            grant_type: 'password',
            client_id: 'administration',
            username: 'admin',
            password: 'shopware'
        })
    });
    return response.data.access_token;
}

// Get all products from Shopware
async function getShopwareProducts(token) {
    const products = [];
    let page = 1;

    while (true) {
        const response = await makeRequest(`${SHOPWARE_URL}/api/search/product`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({
                limit: 500,
                page: page,
                associations: {
                    prices: {}
                }
            })
        });

        if (!response.data.data || response.data.data.length === 0) break;

        for (const product of response.data.data) {
            const name = product.translated?.name || product.name || '';
            const productNumber = product.productNumber || '';

            // Find CHF price
            let price = 0;
            if (product.price && Array.isArray(product.price)) {
                const chfPrice = product.price.find(p => p.currencyId === CHF_CURRENCY_ID);
                price = chfPrice?.gross || product.price[0]?.gross || 0;
            }

            products.push({ productNumber, name, price });
        }

        console.log(`Fetched ${products.length} products from ortak.ch...`);
        if (response.data.data.length < 500) break;
        page++;
    }

    return products;
}

// Scrape products from shop.ravenweapon.ch
async function scrapeOldShop() {
    const products = [];
    let page = 1;

    console.log('Scraping shop.ravenweapon.ch...');

    while (page <= 30) {
        const url = `https://shop.ravenweapon.ch/en?page=${page}&per_page=96`;

        try {
            const response = await makeRequest(url);
            const html = response.raw || response.data;

            // Different patterns to try
            // Pattern 1: Look for product links with heading
            const linkPattern = /href="\/en\/products\/([^"]+)"[^>]*>[\s\S]*?<(?:h6|span)[^>]*class="[^"]*product[^"]*"[^>]*>([^<]+)<\/(?:h6|span)>/gi;

            // Pattern 2: Look for h6 headings with product names and h4 with prices
            const namePattern = /<h6[^>]*>([^<]+)<\/h6>/gi;
            const pricePattern = /<h4[^>]*>([0-9',\.]+)<\/h4>/gi;

            // Try to extract from HTML structure
            const productBlocks = html.split('product-box');
            let foundOnPage = 0;

            for (let i = 1; i < productBlocks.length; i++) {
                const block = productBlocks[i];

                // Extract product link
                const linkMatch = block.match(/href="\/en\/products\/([^"]+)"/);
                if (!linkMatch) continue;

                const slug = linkMatch[1];

                // Extract name from h6
                const h6Match = block.match(/<h6[^>]*>([^<]+)<\/h6>/i);
                if (!h6Match) continue;

                const name = h6Match[1].trim();

                // Extract price from h4 (handle HTML entities like &#039; for ')
                const h4Match = block.match(/<h4[^>]*>([0-9&#;',\.]+)<\/h4>/i);
                let price = 0;
                if (h4Match) {
                    // Convert HTML entities and clean up price
                    let priceStr = h4Match[1]
                        .replace(/&#039;/g, '')  // HTML entity for '
                        .replace(/&apos;/g, '')  // Another form
                        .replace(/'/g, '')       // Direct apostrophe
                        .replace(/,/g, '.');     // European decimal
                    price = parseFloat(priceStr) || 0;
                }

                if (!products.some(p => p.slug === slug)) {
                    products.push({ slug, name, price });
                    foundOnPage++;
                }
            }

            // If product-box splitting didn't work, try direct h6/h4 extraction
            if (foundOnPage === 0) {
                // Find all h6 elements that look like product names
                const h6Matches = [...html.matchAll(/<h6[^>]*>([^<]+)<\/h6>/gi)];
                const h4Matches = [...html.matchAll(/<h4[^>]*>([0-9&#;',\.\s]+)<\/h4>/gi)];

                for (let i = 0; i < h6Matches.length && i < h4Matches.length; i++) {
                    const name = h6Matches[i][1].trim();
                    const priceStr = h4Matches[i][1]
                        .replace(/&#039;/g, '')
                        .replace(/&apos;/g, '')
                        .replace(/'/g, '')
                        .replace(/\s/g, '')
                        .replace(/,/g, '.');
                    const price = parseFloat(priceStr) || 0;

                    if (name && !products.some(p => p.name === name)) {
                        products.push({ slug: `product-${i}`, name, price });
                        foundOnPage++;
                    }
                }
            }

            console.log(`Page ${page}: found ${foundOnPage} products (total: ${products.length})`);

            // Check if there's a next page
            if (!html.includes(`page=${page + 1}`) || foundOnPage === 0) {
                break;
            }

            page++;

        } catch (error) {
            console.error(`Error on page ${page}:`, error.message);
            break;
        }
    }

    return products;
}

// Normalize product name for comparison
function normalizeName(name) {
    return name.toLowerCase()
        .replace(/[®™©]/g, '')
        .replace(/\s+/g, ' ')
        .replace(/[^a-z0-9\s.-]/g, '')
        .trim();
}

// Find matching product
function findMatch(oldProduct, shopwareProducts) {
    const oldName = normalizeName(oldProduct.name);

    // Exact match
    let match = shopwareProducts.find(p => normalizeName(p.name) === oldName);
    if (match) return match;

    // Partial match
    match = shopwareProducts.find(p => {
        const swName = normalizeName(p.name);
        return oldName.includes(swName) || swName.includes(oldName);
    });
    if (match) return match;

    // Word-based match (at least 60% of words match)
    const oldWords = oldName.split(' ').filter(w => w.length > 2);
    match = shopwareProducts.find(p => {
        const swWords = normalizeName(p.name).split(' ').filter(w => w.length > 2);
        const matching = oldWords.filter(w => swWords.includes(w));
        return matching.length >= Math.max(2, oldWords.length * 0.5);
    });

    return match;
}

// Main
async function main() {
    console.log('='.repeat(60));
    console.log('PRICE COMPARISON: shop.ravenweapon.ch vs ortak.ch');
    console.log('='.repeat(60) + '\n');

    // Get Shopware products
    console.log('Getting Shopware API token...');
    const token = await getShopwareToken();
    console.log('Token obtained!\n');

    const shopwareProducts = await getShopwareProducts(token);
    console.log(`\nTotal ortak.ch products: ${shopwareProducts.length}\n`);

    // Get old shop products
    const oldProducts = await scrapeOldShop();
    console.log(`\nTotal shop.ravenweapon.ch products: ${oldProducts.length}\n`);

    // Compare
    console.log('='.repeat(60));
    console.log('COMPARING...');
    console.log('='.repeat(60) + '\n');

    const samePrices = [];
    const differentPrices = [];
    const notFound = [];

    // Filter out Snigel products
    const filteredOldProducts = oldProducts.filter(p => {
        const name = p.name.toLowerCase();
        return !name.includes('snigel') && !name.includes('sn-');
    });

    console.log(`Excluded Snigel products. Comparing ${filteredOldProducts.length} products...\n`);

    for (const old of filteredOldProducts) {
        const match = findMatch(old, shopwareProducts);

        if (match) {
            if (Math.abs(old.price - match.price) < 0.01) {
                samePrices.push({ old, match });
            } else {
                differentPrices.push({ old, match, diff: match.price - old.price });
            }
        } else {
            notFound.push(old);
        }
    }

    // Results
    console.log('='.repeat(60));
    console.log('RESULTS');
    console.log('='.repeat(60));
    console.log(`Products scraped from old shop: ${oldProducts.length}`);
    console.log(`Products compared (excluding Snigel): ${filteredOldProducts.length}`);
    console.log(`Same prices: ${samePrices.length}`);
    console.log(`Different prices: ${differentPrices.length}`);
    console.log(`Not found on ortak.ch: ${notFound.length}`);

    if (differentPrices.length > 0) {
        console.log('\n' + '='.repeat(60));
        console.log('PRODUCTS WITH DIFFERENT PRICES');
        console.log('='.repeat(60) + '\n');

        for (const item of differentPrices) {
            const diffStr = item.diff > 0 ? `+${item.diff.toFixed(2)}` : item.diff.toFixed(2);
            console.log(`OLD: ${item.old.name}`);
            console.log(`NEW: ${item.match.name}`);
            console.log(`  Old: CHF ${item.old.price.toFixed(2)} | New: CHF ${item.match.price.toFixed(2)} | Diff: ${diffStr}`);
            console.log('');
        }
    }

    if (samePrices.length > 0) {
        console.log('\n' + '='.repeat(60));
        console.log(`PRODUCTS WITH SAME PRICES (${samePrices.length})`);
        console.log('='.repeat(60) + '\n');

        for (const item of samePrices.slice(0, 30)) {
            console.log(`✓ ${item.old.name}: CHF ${item.old.price.toFixed(2)}`);
        }
        if (samePrices.length > 30) {
            console.log(`... and ${samePrices.length - 30} more`);
        }
    }

    console.log('\n' + '='.repeat(60));
    console.log('DONE');
    console.log('='.repeat(60));
}

main().catch(console.error);
