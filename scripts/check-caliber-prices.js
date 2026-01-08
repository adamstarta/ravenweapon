/**
 * Check Caliber Kit vs Complete Rifle Prices
 *
 * Rule: Caliber Kit = Complete Rifle Price - 900 CHF
 */

const https = require('https');

const CONFIG = {
    shopware: {
        apiUrl: 'https://ravenweapon.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
    }
};

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

async function getAllProducts() {
    const token = await getToken();
    const allProducts = [];
    let page = 1;
    const limit = 500;

    while (true) {
        const res = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: {
                limit: limit,
                page: page,
                includes: {
                    product: ['id', 'productNumber', 'name', 'price', 'active', 'parentId']
                }
            }
        });

        if (!res.data.data || res.data.data.length === 0) break;
        allProducts.push(...res.data.data);
        if (res.data.data.length < limit) break;
        page++;
    }

    return allProducts;
}

async function main() {
    console.log('\n' + '='.repeat(80));
    console.log('  CALIBER KIT vs COMPLETE RIFLE PRICE CHECK');
    console.log('  Rule: Caliber Kit = Complete Rifle - 900 CHF');
    console.log('='.repeat(80) + '\n');

    console.log('Fetching all products from Shopware...\n');
    const products = await getAllProducts();
    console.log(`Total products fetched: ${products.length}\n`);

    // Search for products with "caliber", "kit", "complete", "22lr", "9mm" in name
    const keywords = ['caliber', 'kit', 'complete', '22lr', '9mm', '.22', 'rifle', 'karabiner'];

    const relevantProducts = products.filter(p => {
        const name = (p.name || '').toLowerCase();
        const number = (p.productNumber || '').toLowerCase();
        return keywords.some(kw => name.includes(kw) || number.includes(kw));
    });

    console.log(`Found ${relevantProducts.length} potentially relevant products:\n`);
    console.log('-'.repeat(80));
    console.log('Product Number'.padEnd(25) + 'Price (CHF)'.padStart(12) + '  ' + 'Name');
    console.log('-'.repeat(80));

    // Sort by name for easier reading
    relevantProducts.sort((a, b) => (a.name || '').localeCompare(b.name || ''));

    for (const p of relevantProducts) {
        const price = p.price?.[0]?.gross || 0;
        const num = (p.productNumber || 'N/A').substring(0, 24).padEnd(25);
        const priceStr = price.toFixed(2).padStart(12);
        const name = (p.name || 'Unknown').substring(0, 50);
        console.log(`${num}${priceStr}  ${name}`);
    }

    // Now let's try to identify caliber kits vs complete rifles
    console.log('\n\n' + '='.repeat(80));
    console.log('  ANALYSIS: Looking for caliber kit / complete rifle pairs');
    console.log('='.repeat(80) + '\n');

    const caliberKits = relevantProducts.filter(p => {
        const name = (p.name || '').toLowerCase();
        return name.includes('caliber') && name.includes('kit') ||
               name.includes('wechselsystem') ||
               name.includes('conversion');
    });

    const completeRifles = relevantProducts.filter(p => {
        const name = (p.name || '').toLowerCase();
        return name.includes('complete') ||
               (name.includes('karabiner') && !name.includes('kit')) ||
               (name.includes('rifle') && !name.includes('kit'));
    });

    console.log('CALIBER KITS found:');
    console.log('-'.repeat(80));
    if (caliberKits.length === 0) {
        console.log('  (none found with keywords "caliber kit", "wechselsystem", or "conversion")');
    } else {
        for (const p of caliberKits) {
            const price = p.price?.[0]?.gross || 0;
            console.log(`  ${(p.productNumber || 'N/A').padEnd(25)} CHF ${price.toFixed(2).padStart(10)}  ${p.name}`);
        }
    }

    console.log('\nCOMPLETE RIFLES found:');
    console.log('-'.repeat(80));
    if (completeRifles.length === 0) {
        console.log('  (none found with keywords "complete", "karabiner", or "rifle")');
    } else {
        for (const p of completeRifles) {
            const price = p.price?.[0]?.gross || 0;
            console.log(`  ${(p.productNumber || 'N/A').padEnd(25)} CHF ${price.toFixed(2).padStart(10)}  ${p.name}`);
        }
    }

    // Print ALL products with 22lr or 9mm specifically
    console.log('\n\n' + '='.repeat(80));
    console.log('  ALL 22LR AND 9MM PRODUCTS');
    console.log('='.repeat(80) + '\n');

    const caliberProducts = products.filter(p => {
        const name = (p.name || '').toLowerCase();
        const number = (p.productNumber || '').toLowerCase();
        return name.includes('22lr') || name.includes('.22') || name.includes('9mm') ||
               number.includes('22lr') || number.includes('9mm');
    });

    caliberProducts.sort((a, b) => (a.name || '').localeCompare(b.name || ''));

    for (const p of caliberProducts) {
        const price = p.price?.[0]?.gross || 0;
        const num = (p.productNumber || 'N/A').substring(0, 24).padEnd(25);
        const priceStr = price.toFixed(2).padStart(12);
        const name = (p.name || 'Unknown').substring(0, 60);
        console.log(`${num}${priceStr}  ${name}`);
    }

    console.log('\n' + '='.repeat(80));
    console.log('  DONE - Review the products above to identify pricing issues');
    console.log('='.repeat(80) + '\n');
}

main().catch(err => {
    console.error('Error:', err.message);
    process.exit(1);
});
