/**
 * Find products in Ausrüstung category with specific description text:
 * "Not a stock item. It is made to order and normally has 16 weeks delivery time..."
 */

const https = require('https');

const CONFIG = {
    apiUrl: 'https://ravenweapon.ch/api',
    clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
    clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
};

const SEARCH_TEXT = 'Not a stock item';

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

async function main() {
    console.log('Searching for products with "Not a stock item" in description...\n');

    // Get token
    const tokenRes = await httpRequest(CONFIG.apiUrl + '/oauth/token', {
        method: 'POST',
        body: {
            grant_type: 'client_credentials',
            client_id: CONFIG.clientId,
            client_secret: CONFIG.clientSecret
        }
    });
    const token = tokenRes.data.access_token;
    console.log('Got API token\n');

    // Find Ausrüstung category
    const catRes = await httpRequest(CONFIG.apiUrl + '/search/category', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + token },
        body: {
            filter: [{ field: 'name', type: 'equals', value: 'Ausrüstung' }],
            includes: { category: ['id', 'name'] }
        }
    });

    const ausruestungCat = catRes.data.data?.[0];
    if (!ausruestungCat) {
        console.log('ERROR: Ausrüstung category not found!');
        return;
    }
    console.log(`Found Ausrüstung category: ${ausruestungCat.id}\n`);

    // Get ALL products and search for the text
    let page = 1;
    const pageSize = 500;
    const matchingProducts = [];
    let totalProducts = 0;

    console.log('Searching ALL products for the description text...\n');

    while (true) {
        const prodRes = await httpRequest(CONFIG.apiUrl + '/search/product', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token },
            body: {
                page: page,
                limit: pageSize,
                associations: {
                    categories: {}
                },
                includes: {
                    product: ['id', 'productNumber', 'name', 'description', 'categories'],
                    category: ['id', 'name']
                }
            }
        });

        const products = prodRes.data.data || [];
        if (products.length === 0) break;

        totalProducts += products.length;
        console.log(`Checking page ${page} (${products.length} products, total: ${totalProducts})...`);

        for (const product of products) {
            const desc = product.description || '';
            if (desc.toLowerCase().includes(SEARCH_TEXT.toLowerCase())) {
                const categoryNames = (product.categories || []).map(c => c.name).join(', ');
                matchingProducts.push({
                    id: product.id,
                    productNumber: product.productNumber,
                    name: product.name,
                    categories: categoryNames,
                    description: desc
                });
            }
        }

        page++;
    }

    console.log(`\n${'='.repeat(70)}`);
    console.log(`RESULTS: Found ${matchingProducts.length} products out of ${totalProducts} total`);
    console.log(`${'='.repeat(70)}\n`);

    if (matchingProducts.length > 0) {
        console.log('Products with "Not a stock item" in description:\n');
        for (const p of matchingProducts) {
            console.log(`${'='.repeat(70)}`);
            console.log(`PRODUCT: ${p.name}`);
            console.log(`Product Number: ${p.productNumber}`);
            console.log(`Categories: ${p.categories || 'None'}`);
            console.log(`\nDESCRIPTION:`);
            // Strip HTML tags and show description
            const cleanDesc = (p.description || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            console.log(cleanDesc);
            console.log('');
        }
    } else {
        console.log('No products found with that description text.');
    }
}

main().catch(console.error);
