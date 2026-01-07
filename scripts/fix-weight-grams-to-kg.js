/**
 * Fix products with weight stored in grams instead of kg
 * Only fixes weights >= 10 (definitely grams like 200, 150, 1500)
 */

const https = require('https');

const CONFIG = {
    apiUrl: 'https://ravenweapon.ch/api',
    clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
    clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
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

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function main() {
    console.log('='.repeat(80));
    console.log('  FIX WEIGHTS: Convert grams to kg');
    console.log('='.repeat(80));

    const tokenRes = await httpRequest(CONFIG.apiUrl + '/oauth/token', {
        method: 'POST',
        body: {
            grant_type: 'client_credentials',
            client_id: CONFIG.clientId,
            client_secret: CONFIG.clientSecret
        }
    });
    const token = tokenRes.data.access_token;
    console.log('Authenticated\n');

    // Get all products with weight >= 10 (definitely stored in grams)
    const res = await httpRequest(CONFIG.apiUrl + '/search/product', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + token },
        body: {
            limit: 500,
            filter: [
                { type: 'range', field: 'weight', parameters: { gte: 10 } },
                { type: 'equals', field: 'parentId', value: null }
            ],
            includes: { product: ['id', 'productNumber', 'name', 'weight'] }
        }
    });

    const products = res.data.data || [];
    console.log('Products with weight >= 10 (stored in grams): ' + products.length + '\n');

    let fixed = 0;
    let failed = 0;

    for (const p of products) {
        const oldWeight = p.weight;
        const newWeight = oldWeight / 1000; // Convert grams to kg

        process.stdout.write(`[${fixed + failed + 1}/${products.length}] ${p.productNumber}: ${oldWeight}g -> ${newWeight}kg... `);

        try {
            const updateRes = await httpRequest(CONFIG.apiUrl + '/product/' + p.id, {
                method: 'PATCH',
                headers: { 'Authorization': 'Bearer ' + token },
                body: { weight: newWeight }
            });

            if (updateRes.status === 204 || updateRes.status === 200) {
                console.log('OK');
                fixed++;
            } else {
                console.log('FAILED');
                failed++;
            }
        } catch (err) {
            console.log('ERROR: ' + err.message);
            failed++;
        }

        await delay(50);
    }

    console.log('\n' + '='.repeat(80));
    console.log('  SUMMARY');
    console.log('='.repeat(80));
    console.log('Fixed: ' + fixed);
    console.log('Failed: ' + failed);
}

main().catch(console.error);
