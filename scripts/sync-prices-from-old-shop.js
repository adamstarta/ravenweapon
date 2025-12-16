/**
 * Sync prices from shop.ravenweapon.ch to ortak.ch
 * Uses old shop prices as source of truth for CHF prices
 */

const https = require('https');
const http = require('http');

const CHF_CURRENCY_ID = '0191c12cf40d718a8a3439b74a6f083c';
const SHOPWARE_URL = 'https://ortak.ch';

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
                'User-Agent': 'Mozilla/5.0',
                'Content-Type': 'application/json',
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

function sleep(ms) { return new Promise(resolve => setTimeout(resolve, ms)); }

function normalizeName(name) {
    return name.toLowerCase()
        .replace(/[®™©]/g, '')
        .replace(/&amp;/g, '&')
        .replace(/&quot;/g, '"')
        .replace(/&#039;/g, "'")
        .replace(/\s+/g, ' ')
        .replace(/[^a-z0-9\s.-]/g, '')
        .trim();
}

// Scrape old shop
async function scrapeOldShop() {
    const products = [];
    let page = 1;

    console.log('Scraping shop.ravenweapon.ch for correct CHF prices...');

    while (page <= 30) {
        const url = `https://shop.ravenweapon.ch/en?page=${page}&per_page=96`;
        const response = await makeRequest(url);
        const html = response.raw || response.data;

        const productBlocks = html.split('product-box');
        let foundOnPage = 0;

        for (let i = 1; i < productBlocks.length; i++) {
            const block = productBlocks[i];

            // Handle both relative and absolute URLs
            const linkMatch = block.match(/href="(?:https:\/\/shop\.ravenweapon\.ch)?\/en\/products\/([^"]+)"/);
            if (!linkMatch) continue;

            const slug = linkMatch[1];
            const h6Match = block.match(/<h6[^>]*>([^<]+)<\/h6>/i);
            if (!h6Match) continue;

            const name = h6Match[1].trim();
            const h4Match = block.match(/<h4[^>]*>\s*([0-9&#;',.]+)\s*<\/h4>/i);
            let price = 0;
            if (h4Match) {
                let priceStr = h4Match[1]
                    .replace(/&#039;/g, '')
                    .replace(/&apos;/g, '')
                    .replace(/'/g, '')
                    .replace(/,/g, '.');
                price = parseFloat(priceStr) || 0;
            }

            if (!products.some(p => p.slug === slug)) {
                products.push({ slug, name, price });
                foundOnPage++;
            }
        }

        console.log(`Page ${page}: ${foundOnPage} products (total: ${products.length})`);

        if (!html.includes(`page=${page + 1}`) || foundOnPage === 0) break;
        page++;
    }

    // Filter out Snigel
    const filtered = products.filter(p => {
        const name = p.name.toLowerCase();
        return !name.includes('snigel') && !name.includes('sn-');
    });

    console.log('After filtering Snigel:', filtered.length, 'products\n');
    return filtered;
}

async function main() {
    // Get old shop prices
    const oldProducts = await scrapeOldShop();

    // Get Shopware token
    const tokenRes = await makeRequest(SHOPWARE_URL + '/api/oauth/token', {
        method: 'POST',
        body: JSON.stringify({
            grant_type: 'password',
            client_id: 'administration',
            username: 'admin',
            password: 'shopware'
        })
    });
    const token = tokenRes.data.access_token;
    console.log('Shopware token obtained');

    // Get all Shopware products
    let swProducts = [];
    let page = 1;
    while (true) {
        const res = await makeRequest(SHOPWARE_URL + '/api/search/product', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token },
            body: JSON.stringify({ limit: 500, page: page })
        });
        if (!res.data?.data || res.data.data.length === 0) break;
        swProducts = swProducts.concat(res.data.data);
        if (res.data.data.length < 500) break;
        page++;
    }
    console.log('Found', swProducts.length, 'products in Shopware\n');

    // Match and update
    let updated = 0;
    let notFound = 0;
    let errors = 0;
    let skipped = [];

    console.log('Updating prices to match old shop...\n');

    // Debug: show first few products from each
    console.log('Sample old shop products:');
    oldProducts.slice(0, 3).forEach(p => console.log('  ', p.name));
    console.log('\nSample Shopware products:');
    swProducts.slice(0, 3).forEach(p => console.log('  ', p.name));
    console.log('');

    for (const old of oldProducts) {
        const oldNameNorm = normalizeName(old.name);

        // Find matching Shopware product - exact match first
        let match = swProducts.find(p => {
            const swName = normalizeName(p.name || p.translated?.name || '');
            return swName === oldNameNorm;
        });

        // Debug first product
        if (old === oldProducts[0]) {
            console.log('DEBUG first product:');
            console.log('  Old name:', old.name);
            console.log('  Old normalized:', oldNameNorm);
            const testSw = swProducts.find(p => p.name && p.name.includes('TRACE'));
            if (testSw) {
                console.log('  Sample SW name:', testSw.name);
                console.log('  SW normalized:', normalizeName(testSw.name));
            }
            console.log('');
        }

        // Partial match
        if (!match) {
            match = swProducts.find(p => {
                const swName = normalizeName(p.name || p.translated?.name || '');
                return oldNameNorm.includes(swName) || swName.includes(oldNameNorm);
            });
        }

        // Word-based match
        if (!match) {
            const oldWords = oldNameNorm.split(' ').filter(w => w.length > 2);
            match = swProducts.find(p => {
                const swWords = normalizeName(p.name || '').split(' ').filter(w => w.length > 2);
                const matching = oldWords.filter(w => swWords.includes(w));
                return matching.length >= Math.max(2, oldWords.length * 0.6);
            });
        }

        if (match && old.price > 0) {
            // Update price to match old shop
            const grossPrice = old.price;
            const netPrice = grossPrice / 1.081; // Swiss VAT 8.1%

            const updateRes = await makeRequest(SHOPWARE_URL + '/api/product/' + match.id, {
                method: 'PATCH',
                headers: { 'Authorization': 'Bearer ' + token },
                body: JSON.stringify({
                    price: [{
                        currencyId: CHF_CURRENCY_ID,
                        gross: grossPrice,
                        net: netPrice,
                        linked: false
                    }]
                })
            });

            if (updateRes.status === 204 || updateRes.status === 200) {
                updated++;
                process.stdout.write('\rUpdated: ' + updated + '/' + oldProducts.length + ' - ' + old.name.substring(0, 30));
            } else {
                errors++;
                console.log('\nError:', old.name, updateRes.status);
            }

            await sleep(50);
        } else {
            notFound++;
            skipped.push(old.name);
            // Debug: show first 5 not found
            if (notFound <= 5) {
                console.log('Not found:', old.name, '| price:', old.price);
            }
        }
    }

    console.log('\n\n=== DONE ===');
    console.log('Updated:', updated);
    console.log('Not found in ortak.ch:', notFound);
    console.log('Errors:', errors);

    if (skipped.length > 0 && skipped.length <= 20) {
        console.log('\nSkipped products (not found in ortak.ch):');
        skipped.forEach(s => console.log('  -', s));
    } else if (skipped.length > 20) {
        console.log('\nFirst 20 skipped products:');
        skipped.slice(0, 20).forEach(s => console.log('  -', s));
        console.log('  ... and', skipped.length - 20, 'more');
    }
}

main().catch(console.error);
