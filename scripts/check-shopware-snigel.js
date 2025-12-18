/**
 * Check Snigel products in Shopware
 */
const https = require('https');

const SHOPWARE_URL = 'https://ortak.ch';

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
                    resolve(JSON.parse(data));
                } catch (e) {
                    resolve(data);
                }
            });
        });

        req.on('error', reject);
        if (options.body) req.write(JSON.stringify(options.body));
        req.end();
    });
}

async function main() {
    // Get token
    console.log('Getting Shopware token...');
    const tokenRes = await httpRequest(`${SHOPWARE_URL}/api/oauth/token`, {
        method: 'POST',
        body: {
            grant_type: 'password',
            client_id: 'administration',
            username: 'admin',
            password: 'shopware'
        }
    });
    const token = tokenRes.access_token;
    console.log('Token obtained!\n');

    // List manufacturers
    console.log('=== MANUFACTURERS ===');
    const mfrRes = await httpRequest(`${SHOPWARE_URL}/api/search/product-manufacturer`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: { limit: 50 }
    });

    if (mfrRes.data) {
        mfrRes.data.forEach(m => {
            console.log(`  ${m.name} (${m.id})`);
        });
    }

    // Search for Snigel products
    console.log('\n=== SEARCHING FOR SNIGEL PRODUCTS ===\n');

    // Try different search terms
    const searchTerms = ['Snigel', 'snigel', 'SN-', 'Belt closure', 'Foldable drinking'];

    for (const term of searchTerms) {
        const res = await httpRequest(`${SHOPWARE_URL}/api/search/product`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: {
                limit: 10,
                filter: [
                    { type: 'contains', field: 'name', value: term }
                ]
            }
        });

        const count = res.data?.length || 0;
        console.log(`Search "${term}": ${count} results`);
        if (count > 0) {
            res.data.slice(0, 3).forEach(p => {
                console.log(`  - ${p.name} (${p.productNumber})`);
            });
        }
    }

    // Get total products
    console.log('\n=== TOTAL PRODUCTS IN SHOPWARE ===');
    const totalRes = await httpRequest(`${SHOPWARE_URL}/api/search/product`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: { limit: 1, totalCountMode: 1 }
    });
    console.log(`Total products: ${totalRes.meta?.total || 'unknown'}`);

    // Sample some products
    console.log('\n=== SAMPLE PRODUCTS ===');
    const sampleRes = await httpRequest(`${SHOPWARE_URL}/api/search/product`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: { limit: 20 }
    });

    if (sampleRes.data) {
        sampleRes.data.forEach(p => {
            console.log(`  ${p.name}`);
        });
    }
}

main().catch(console.error);
