/**
 * Fix empty description for Tactical coverall 09F
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

async function main() {
    console.log('Fixing empty description for Tactical coverall 09F...\n');

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

    // Product ID for Tactical coverall 09F
    const productId = '9ab23b619acb921a9a9998730380de39';

    // Add a proper description
    const newDescription = '<p>Tactical coverall for professional use.</p>';

    const updateRes = await httpRequest(CONFIG.apiUrl + '/product/' + productId, {
        method: 'PATCH',
        headers: { 'Authorization': 'Bearer ' + token },
        body: { description: newDescription }
    });

    if (updateRes.status === 204 || updateRes.status === 200) {
        console.log('✓ Description updated successfully!');
        console.log('New description: ' + newDescription);
    } else {
        console.log('✗ Failed: ' + JSON.stringify(updateRes.data));
    }
}

main().catch(console.error);
