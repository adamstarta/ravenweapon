/**
 * Update ZeroTech products in Shopware with weights from website
 * Run with: node update-zerotech-weights.js
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

async function getProductId(productNumber) {
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${accessToken}` },
        body: {
            limit: 1,
            filter: [
                { type: 'equals', field: 'productNumber', value: productNumber }
            ],
            includes: { product: ['id'] }
        }
    });

    if (res.data.data && res.data.data.length > 0) {
        return res.data.data[0].id;
    }
    return null;
}

async function updateProductWeight(productId, weightInGrams) {
    // Shopware expects weight in kg
    const weightInKg = weightInGrams / 1000;

    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/product/${productId}`, {
        method: 'PATCH',
        headers: { 'Authorization': `Bearer ${accessToken}` },
        body: {
            weight: weightInKg
        }
    });

    return res.status === 204 || res.status === 200;
}

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function main() {
    console.log('='.repeat(90));
    console.log('  UPDATING ZEROTECH PRODUCT WEIGHTS IN SHOPWARE');
    console.log('='.repeat(90));

    // Load comparison results
    const comparison = JSON.parse(fs.readFileSync('zerotech-weight-comparison.json', 'utf8'));

    console.log(`\nProducts to update: ${comparison.matched.length}\n`);

    await getToken();
    console.log('Authenticated with Shopware API\n');

    const success = [];
    const failed = [];
    let count = 0;

    for (const product of comparison.matched) {
        count++;
        const productNumber = product.productNumber;
        const weightGrams = product.weight;

        try {
            process.stdout.write(`[${count}/${comparison.matched.length}] ${productNumber}... `);

            // Get product ID
            const productId = await getProductId(productNumber);
            if (!productId) {
                console.log('NOT FOUND');
                failed.push({ productNumber, reason: 'Product not found' });
                continue;
            }

            // Update weight
            const updated = await updateProductWeight(productId, weightGrams);
            if (updated) {
                console.log(`UPDATED (${weightGrams}g = ${(weightGrams/1000).toFixed(3)}kg)`);
                success.push({ productNumber, weight: weightGrams });
            } else {
                console.log('FAILED');
                failed.push({ productNumber, reason: 'Update failed' });
            }

            await delay(100); // Be nice to the API

        } catch (err) {
            console.log(`ERROR: ${err.message}`);
            failed.push({ productNumber, reason: err.message });
        }
    }

    // Summary
    console.log('\n' + '='.repeat(90));
    console.log('  UPDATE SUMMARY');
    console.log('='.repeat(90));
    console.log(`Successfully updated: ${success.length}`);
    console.log(`Failed: ${failed.length}`);

    if (failed.length > 0) {
        console.log('\nFailed products:');
        for (const f of failed) {
            console.log(`  - ${f.productNumber}: ${f.reason}`);
        }
    }

    // Save results
    fs.writeFileSync('zerotech-update-results.json', JSON.stringify({
        updatedAt: new Date().toISOString(),
        success,
        failed,
        summary: {
            total: comparison.matched.length,
            success: success.length,
            failed: failed.length
        }
    }, null, 2));

    console.log('\nSaved results to zerotech-update-results.json');
}

main().catch(err => {
    console.error('Fatal error:', err.message);
    process.exit(1);
});
