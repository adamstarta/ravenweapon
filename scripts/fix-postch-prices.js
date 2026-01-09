/**
 * Update shipping prices to match Post.ch official rates
 * - Express = Standard (PostPac Priority)
 * - International = PostPac International
 */

const https = require('https');

const CONFIG = {
    apiUrl: 'https://ravenweapon.ch/api',
    clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
    clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
};

// Shipping method IDs
const SHIPPING = {
    standard: '0191c12ccec070f1bf91def7cb9f6dac',
    express: '0191c12ccec070f1bf91def7cc08e85b',
    international: '019b981d3b557a5b978cca474ab72b76'
};

// Post.ch prices from docs/shipping-prices-post-ch-2026.md
const POSTCH_PRICES = {
    // PostPac Priority (domestic) - for Standard AND Express
    domestic: {
        2: 10.50,   // 0-2kg
        10: 13.50,  // 2-10kg
        30: 22.50   // 10-30kg
    },
    // PostPac International
    international: {
        2: 36.00,   // 0-2kg
        5: 46.00,   // 2-5kg
        10: 52.00,  // 5-10kg
        15: 59.00,  // 10-15kg
        20: 65.00,  // 15-20kg
        30: 76.00   // 20-30kg
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
                try { resolve({ status: res.statusCode, data: JSON.parse(data) }); }
                catch (e) { resolve({ status: res.statusCode, data: data }); }
            });
        });
        req.on('error', reject);
        if (options.body) req.write(JSON.stringify(options.body));
        req.end();
    });
}

async function main() {
    console.log('=== Updating shipping prices to match Post.ch ===\n');

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

    // Get all shipping price rules
    const rulesRes = await httpRequest(CONFIG.apiUrl + '/shipping-method-price', {
        method: 'GET',
        headers: { 'Authorization': 'Bearer ' + token }
    });

    const updates = [];

    for (const rule of rulesRes.data.data) {
        const methodId = rule.shippingMethodId;
        const quantityEnd = rule.quantityEnd;
        let newPrice = null;
        let methodName = '';

        if (methodId === SHIPPING.standard || methodId === SHIPPING.express) {
            // Both Standard and Express use domestic PostPac Priority prices
            methodName = methodId === SHIPPING.standard ? 'Standard' : 'Express';
            newPrice = POSTCH_PRICES.domestic[quantityEnd];
        } else if (methodId === SHIPPING.international) {
            methodName = 'International';
            newPrice = POSTCH_PRICES.international[quantityEnd];
        }

        if (newPrice === null || newPrice === undefined) {
            console.log('Skipping rule ' + rule.id + ' (weight tier ' + quantityEnd + 'kg not in Post.ch prices)');
            continue;
        }

        const netPrice = Math.round((newPrice / 1.081) * 100) / 100; // Swiss VAT 8.1%

        console.log(methodName + ' (' + quantityEnd + 'kg): CHF ' + rule.currencyPrice[0].gross + ' -> CHF ' + newPrice);

        updates.push({
            id: rule.id,
            currencyPrice: [{
                currencyId: '0191c12cf40d718a8a3439b74a6f083c',
                net: netPrice,
                gross: newPrice,
                linked: false
            }]
        });
    }

    console.log('\nUpdating ' + updates.length + ' price rules...\n');

    // Batch update
    const syncRes = await httpRequest(CONFIG.apiUrl + '/_action/sync', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'single-operation': 'true'
        },
        body: {
            'update-shipping-prices': {
                entity: 'shipping_method_price',
                action: 'upsert',
                payload: updates
            }
        }
    });

    console.log('Sync response:', syncRes.status);
    if (syncRes.status === 200) {
        console.log('\n=== SUCCESS: All prices updated to Post.ch rates ===');
        console.log('\nStandard & Express (PostPac Priority):');
        console.log('  0-2kg:  CHF 10.50');
        console.log('  2-10kg: CHF 13.50');
        console.log('  10-30kg: CHF 22.50');
        console.log('\nInternational (PostPac International):');
        console.log('  0-2kg:  CHF 36.00');
        console.log('  2-5kg:  CHF 46.00');
        console.log('  5-10kg: CHF 52.00');
        console.log('  10-15kg: CHF 59.00');
        console.log('  15-20kg: CHF 65.00');
        console.log('  20-30kg: CHF 76.00');
    } else {
        console.log('Error:', JSON.stringify(syncRes.data, null, 2));
    }
}

main().catch(console.error);
