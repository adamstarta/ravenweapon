/**
 * Delete Products from Shopware
 */

const https = require('https');

const CONFIG = {
    apiUrl: 'https://ravenweapon.ch/api',
    clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
    clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
};

const productsToDelete = [
    'FCH-AMO-XXX-22LR-XX-00085',  // .22 LR OFFICIAL
    'FCH-AMO-XXX-223-XX-00082'    // 223 Remington FMJ
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
    console.log('Deleting redundant products...\n');

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

    for (const productNumber of productsToDelete) {
        console.log(`Deleting: ${productNumber}`);

        // Find product
        const searchRes = await httpRequest(CONFIG.apiUrl + '/search/product', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token },
            body: {
                limit: 1,
                filter: [{ type: 'equals', field: 'productNumber', value: productNumber }]
            }
        });

        if (!searchRes.data.data || searchRes.data.data.length === 0) {
            console.log('  Not found\n');
            continue;
        }

        const productId = searchRes.data.data[0].id;
        const productName = searchRes.data.data[0].name;
        console.log(`  Name: ${productName}`);
        console.log(`  ID: ${productId}`);

        // Delete product
        const deleteRes = await httpRequest(CONFIG.apiUrl + '/product/' + productId, {
            method: 'DELETE',
            headers: { 'Authorization': 'Bearer ' + token }
        });

        if (deleteRes.status === 204) {
            console.log('  DELETED\n');
        } else {
            console.log(`  Delete failed: ${deleteRes.status}\n`);
        }
    }

    console.log('Done!');
}

main().catch(err => console.error(err.message));
