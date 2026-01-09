/**
 * Remove "Not a stock item..." text from product descriptions
 */

const https = require('https');

const CONFIG = {
    apiUrl: 'https://ravenweapon.ch/api',
    clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
    clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
};

// Products to update
const PRODUCTS = [
    { id: '9ab23b619acb921a9a9998730380de39', name: 'Tactical coverall 09F' },
    { id: 'd2bb3d52ca910f5244e8b8405ddc0b4b', name: 'Tactical coverall, Digi -09F' }
];

// For product 1: Remove entire description (it only has the stock item text)
// For product 2: Remove the first 3 paragraphs (stock item text) but keep the rest

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
    console.log('Removing "Not a stock item..." text from product descriptions...\n');

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

    for (const product of PRODUCTS) {
        console.log(`Processing: ${product.name}`);

        // Get current description
        const getRes = await httpRequest(CONFIG.apiUrl + '/product/' + product.id, {
            method: 'GET',
            headers: { 'Authorization': 'Bearer ' + token }
        });

        let description = getRes.data.data?.description || '';
        const originalDesc = description;

        console.log('  Current description:');
        console.log('  ' + description);
        console.log('');

        // Product 1: Tactical coverall 09F - entire description is stock item text, clear it
        if (product.id === '9ab23b619acb921a9a9998730380de39') {
            description = '';
        }

        // Product 2: Tactical coverall, Digi -09F - remove first 3 paragraphs, keep the rest
        if (product.id === 'd2bb3d52ca910f5244e8b8405ddc0b4b') {
            // Remove: "Only for Finnprotec, not a stock item." paragraph
            // Remove: "It is made to order..." paragraph
            // Remove: "If you order 50 or more..." paragraph
            // Keep: "Made in Cotton/Polyester..." and everything after
            description = `<p>Made in Cotton/Polyester and is NOT flameretardant.</p>
<p>Complete coverall includes:</p>
<p>Coverall</p>
<p>Vest</p>`;
        }

        if (description === originalDesc) {
            console.log('  No changes needed\n');
            continue;
        }

        // Update the product
        const updateRes = await httpRequest(CONFIG.apiUrl + '/product/' + product.id, {
            method: 'PATCH',
            headers: { 'Authorization': 'Bearer ' + token },
            body: { description: description }
        });

        if (updateRes.status === 204 || updateRes.status === 200) {
            console.log('  ✓ Updated successfully!');
            console.log('  New description preview: ' + description.replace(/<[^>]*>/g, ' ').substring(0, 100) + '...\n');
        } else {
            console.log('  ✗ Failed to update: ' + JSON.stringify(updateRes.data) + '\n');
        }
    }

    console.log('Done!');
}

main().catch(console.error);
