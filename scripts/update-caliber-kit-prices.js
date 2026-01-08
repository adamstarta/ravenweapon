/**
 * Update Caliber Kit Prices
 * Rule: Kit Price = Complete Rifle Price - 1400 CHF
 */

const https = require('https');

const CONFIG = {
    shopware: {
        apiUrl: 'https://ravenweapon.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
    }
};

// New prices (Complete Rifle - 1400)
// 22LR: 3850 - 1400 = 2450 | 223: 3850 - 1400 = 2450
// 300AAC: 3950 - 1400 = 2550 | 762: 3950 - 1400 = 2550 | 9mm: 3340 - 1400 = 1940
const PRICE_UPDATES = [
    { productNumber: 'KIT-22LR', newPrice: 2450.00, name: '.22LR CALIBER KIT' },
    { productNumber: 'KIT-223', newPrice: 2450.00, name: '.223 CALIBER KIT' },
    { productNumber: 'KIT-300AAC', newPrice: 2550.00, name: '300 AAC CALIBER KIT' },
    { productNumber: 'KIT-762', newPrice: 2550.00, name: '7.62x39 CALIBER KIT' },
    { productNumber: 'KIT-9MM', newPrice: 1940.00, name: '9mm CALIBER KIT' }
];

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
                    resolve({ status: res.statusCode, data: data ? JSON.parse(data) : {} });
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

let accessToken = null;

async function getToken() {
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/oauth/token`, {
        method: 'POST',
        body: {
            grant_type: 'client_credentials',
            client_id: CONFIG.shopware.clientId,
            client_secret: CONFIG.shopware.clientSecret
        }
    });
    if (!res.data.access_token) throw new Error('Failed to get token');
    accessToken = res.data.access_token;
    return accessToken;
}

async function getProductByNumber(productNumber) {
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${accessToken}` },
        body: {
            limit: 1,
            filter: [{ type: 'equals', field: 'productNumber', value: productNumber }],
            includes: { product: ['id', 'productNumber', 'name', 'price'] }
        }
    });
    return res.data.data?.[0] || null;
}

async function updateProductPrice(productId, newGrossPrice) {
    // Shopware 6 uses net price internally, assuming 8.1% Swiss VAT
    const vatRate = 0.081;
    const netPrice = newGrossPrice / (1 + vatRate);

    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/product/${productId}`, {
        method: 'PATCH',
        headers: { 'Authorization': `Bearer ${accessToken}` },
        body: {
            price: [{
                currencyId: '0191c12cf40d718a8a3439b74a6f083c', // CHF currency ID (correct)
                gross: newGrossPrice,
                net: netPrice,
                linked: false
            }]
        }
    });

    if (res.status !== 204 && res.status !== 200) {
        console.log(`  API Response: ${res.status} - ${JSON.stringify(res.data)}`);
    }
    return res.status === 204 || res.status === 200;
}

async function main() {
    console.log('\n' + '='.repeat(70));
    console.log('  CALIBER KIT PRICE UPDATE');
    console.log('  Rule: Kit = Complete Rifle - 1400 CHF');
    console.log('='.repeat(70) + '\n');

    await getToken();
    console.log('API token obtained.\n');

    const results = [];

    for (const update of PRICE_UPDATES) {
        console.log(`Processing: ${update.productNumber} (${update.name})`);

        // Get current product
        const product = await getProductByNumber(update.productNumber);
        if (!product) {
            console.log(`  ERROR: Product not found!\n`);
            results.push({ ...update, status: 'NOT FOUND', oldPrice: null });
            continue;
        }

        const oldPrice = product.price?.[0]?.gross || 0;
        console.log(`  Current price: CHF ${oldPrice.toFixed(2)}`);
        console.log(`  New price:     CHF ${update.newPrice.toFixed(2)}`);

        // Update price
        const success = await updateProductPrice(product.id, update.newPrice);

        if (success) {
            console.log(`  Status: UPDATED\n`);
            results.push({ ...update, status: 'UPDATED', oldPrice });
        } else {
            console.log(`  Status: FAILED\n`);
            results.push({ ...update, status: 'FAILED', oldPrice });
        }
    }

    // Summary
    console.log('='.repeat(70));
    console.log('  SUMMARY');
    console.log('='.repeat(70));
    console.log('\nProduct Number'.padEnd(20) + 'Old Price'.padStart(12) + 'New Price'.padStart(12) + '  Status');
    console.log('-'.repeat(70));

    for (const r of results) {
        const old = r.oldPrice !== null ? `CHF ${r.oldPrice.toFixed(2)}` : 'N/A';
        console.log(
            r.productNumber.padEnd(20) +
            old.padStart(12) +
            `CHF ${r.newPrice.toFixed(2)}`.padStart(12) +
            `  ${r.status}`
        );
    }

    const updated = results.filter(r => r.status === 'UPDATED').length;
    const failed = results.filter(r => r.status !== 'UPDATED').length;

    console.log('\n' + '='.repeat(70));
    console.log(`  Updated: ${updated} | Failed: ${failed}`);
    console.log('='.repeat(70) + '\n');
}

main().catch(err => {
    console.error('Error:', err.message);
    process.exit(1);
});
