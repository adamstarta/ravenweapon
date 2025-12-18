/**
 * Check Snigel products via Shopware Storefront API
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
                'sw-access-key': 'SWSCNVBGWU1LU2DRDWNOWVHYZW',  // Default sales channel key
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
    console.log('Checking Storefront API...\n');

    // Try to search for products
    console.log('=== SEARCHING FOR SNIGEL ===\n');

    const searchRes = await httpRequest(`${SHOPWARE_URL}/store-api/search`, {
        method: 'POST',
        body: {
            search: 'Snigel',
            limit: 20
        }
    });

    if (searchRes.elements) {
        console.log(`Found ${searchRes.total} results for "Snigel"`);
        searchRes.elements.forEach(p => {
            const price = p.calculatedPrice?.unitPrice || p.calculatedPrices?.[0]?.unitPrice || 'N/A';
            console.log(`  - ${p.translated?.name || p.name}: CHF ${price}`);
        });
    } else {
        console.log('Search response:', JSON.stringify(searchRes).substring(0, 500));
    }

    // List products
    console.log('\n=== PRODUCT LISTING ===\n');

    const listRes = await httpRequest(`${SHOPWARE_URL}/store-api/product`, {
        method: 'POST',
        body: {
            limit: 30,
            includes: {
                product: ['id', 'name', 'productNumber', 'calculatedPrice']
            }
        }
    });

    if (listRes.elements) {
        console.log(`Total products: ${listRes.total}`);
        listRes.elements.forEach(p => {
            console.log(`  - ${p.translated?.name || p.name}`);
        });
    } else {
        console.log('List response:', JSON.stringify(listRes).substring(0, 500));
    }
}

main().catch(console.error);
