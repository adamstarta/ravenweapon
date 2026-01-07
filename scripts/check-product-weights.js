/**
 * Check Product Weights in Shopware
 *
 * Lists all products and shows which have weight and which don't
 *
 * Usage: node check-product-weights.js
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
let tokenExpiry = 0;

async function getToken(forceRefresh = false) {
    const now = Date.now();
    if (!accessToken || forceRefresh || now >= tokenExpiry) {
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
        tokenExpiry = now + ((res.data.expires_in || 600) - 60) * 1000;
    }
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
                },
                associations: {
                    translations: {}
                }
            }
        });

        if (res.status === 401) {
            accessToken = null;
            return getAllProducts();
        }

        if (!res.data.data || res.data.data.length === 0) break;

        allProducts.push(...res.data.data);
        console.log(`Fetched page ${page}, total products so far: ${allProducts.length}`);

        if (res.data.data.length < limit) break;
        page++;
    }

    return allProducts;
}

async function main() {
    console.log('\n' + '='.repeat(80));
    console.log('  PRODUCT WEIGHT CHECK');
    console.log('='.repeat(80));

    console.log('\nGetting API token...');
    await getToken();

    console.log('Fetching all products...\n');
    const products = await getAllProducts();

    // Filter out variants (products with parentId) - we want main products
    const mainProducts = products.filter(p => !p.parentId);
    const variants = products.filter(p => p.parentId);

    console.log(`\nTotal products found: ${products.length}`);
    console.log(`Main products: ${mainProducts.length}`);
    console.log(`Variants: ${variants.length}`);

    // Categorize products
    const withWeight = [];
    const withoutWeight = [];

    for (const product of mainProducts) {
        const name = product.name || product.translations?.[0]?.name || 'Unknown';
        const productNumber = product.productNumber;
        const weight = product.weight;
        const active = product.active;

        if (weight && weight > 0) {
            withWeight.push({
                number: productNumber,
                name: name,
                weight: weight,
                active: active
            });
        } else {
            withoutWeight.push({
                number: productNumber,
                name: name,
                weight: weight,
                active: active
            });
        }
    }

    // Print products WITH weight
    console.log('\n' + '='.repeat(80));
    console.log('  PRODUCTS WITH WEIGHT');
    console.log('='.repeat(80));
    console.log(`  Total: ${withWeight.length}\n`);

    if (withWeight.length > 0) {
        console.log('  Product Number                  Weight (kg)  Active   Name');
        console.log('  ' + '-'.repeat(75));
        withWeight.sort((a, b) => a.number.localeCompare(b.number));
        for (const p of withWeight) {
            const num = (p.number || '').substring(0, 30).padEnd(30);
            const wgt = String(p.weight || 0).padStart(10);
            const act = (p.active ? 'Yes' : 'No').padStart(6);
            const name = (p.name || '').substring(0, 40);
            console.log(`  ${num} ${wgt} ${act}   ${name}`);
        }
    }

    // Print products WITHOUT weight
    console.log('\n' + '='.repeat(80));
    console.log('  PRODUCTS WITHOUT WEIGHT (Missing weight!)');
    console.log('='.repeat(80));
    console.log(`  Total: ${withoutWeight.length}\n`);

    if (withoutWeight.length > 0) {
        console.log('  Product Number                  Active   Name');
        console.log('  ' + '-'.repeat(75));
        withoutWeight.sort((a, b) => a.number.localeCompare(b.number));
        for (const p of withoutWeight) {
            const num = (p.number || '').substring(0, 30).padEnd(30);
            const act = (p.active ? 'Yes' : 'No').padStart(6);
            const name = (p.name || '').substring(0, 50);
            console.log(`  ${num} ${act}   ${name}`);
        }
    }

    // Summary
    console.log('\n' + '='.repeat(80));
    console.log('  SUMMARY');
    console.log('='.repeat(80));
    console.log(`  Total main products:        ${mainProducts.length}`);
    console.log(`  With weight:                ${withWeight.length}`);
    console.log(`  Without weight:             ${withoutWeight.length}`);
    console.log(`  Percentage with weight:     ${((withWeight.length / mainProducts.length) * 100).toFixed(1)}%`);
    console.log('='.repeat(80) + '\n');
}

main().catch(err => {
    console.error('\nFatal error:', err.message);
    process.exit(1);
});
