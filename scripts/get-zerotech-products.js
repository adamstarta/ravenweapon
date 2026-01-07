/**
 * Get ZeroTech products from Shopware that need weight data
 */

const https = require('https');
const fs = require('fs');

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
    const limit = 100;

    while (true) {
        const res = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: {
                limit: limit,
                page: page,
                includes: {
                    product: ['id', 'productNumber', 'name', 'weight', 'active', 'parentId']
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
    console.log('Fetching ZeroTech products from Shopware...\n');

    const products = await getAllProducts();

    // Filter ZeroTech products (ZRT- prefix)
    const zrtProducts = products.filter(p => {
        const num = (p.productNumber || '').toUpperCase();
        return num.startsWith('ZRT-') && !p.parentId;
    });

    console.log(`Total ZeroTech products: ${zrtProducts.length}\n`);

    // Separate by weight status
    const withWeight = [];
    const withoutWeight = [];

    for (const p of zrtProducts) {
        const item = {
            productNumber: p.productNumber,
            name: p.name || 'Unknown',
            weight: p.weight,
            active: p.active
        };

        if (p.weight && p.weight > 0) {
            withWeight.push(item);
        } else {
            withoutWeight.push(item);
        }
    }

    console.log('='.repeat(80));
    console.log('  ZEROTECH PRODUCTS WITH WEIGHT IN SHOPWARE');
    console.log('='.repeat(80));
    console.log(`Total: ${withWeight.length}\n`);

    withWeight.forEach(p => {
        console.log(`${p.productNumber.padEnd(35)} ${String(p.weight).padStart(8)}g  ${p.name.substring(0, 40)}`);
    });

    console.log('\n' + '='.repeat(80));
    console.log('  ZEROTECH PRODUCTS WITHOUT WEIGHT (Need to scrape)');
    console.log('='.repeat(80));
    console.log(`Total: ${withoutWeight.length}\n`);

    withoutWeight.forEach(p => {
        console.log(`${p.productNumber.padEnd(35)} ${p.name.substring(0, 50)}`);
    });

    // Save to JSON for scraping
    const output = {
        withWeight,
        withoutWeight,
        summary: {
            total: zrtProducts.length,
            withWeight: withWeight.length,
            withoutWeight: withoutWeight.length
        }
    };

    fs.writeFileSync('zerotech-products.json', JSON.stringify(output, null, 2));
    console.log('\n\nSaved to zerotech-products.json');
}

main().catch(err => {
    console.error('Error:', err.message);
    process.exit(1);
});
