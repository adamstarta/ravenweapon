/**
 * Remove export control text from product description
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
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', ...options.headers }
        };
        const req = https.request(reqOptions, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                let parsed = null;
                try {
                    parsed = data ? JSON.parse(data) : null;
                } catch (e) {
                    parsed = data;
                }
                resolve({ status: res.statusCode, data: parsed });
            });
        });
        req.on('error', reject);
        if (options.body) req.write(JSON.stringify(options.body));
        req.end();
    });
}

async function main() {
    const tokenRes = await httpRequest(CONFIG.apiUrl + '/oauth/token', {
        method: 'POST',
        body: { grant_type: 'client_credentials', client_id: CONFIG.clientId, client_secret: CONFIG.clientSecret }
    });
    const token = tokenRes.data.access_token;

    // Get the product
    const res = await httpRequest(CONFIG.apiUrl + '/search/product', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + token },
        body: {
            limit: 1,
            filter: [{ type: 'equals', field: 'productNumber', value: '85-01153' }],
            includes: { product: ['id', 'productNumber', 'name', 'description'] }
        }
    });

    const product = res.data.data[0];
    if (!product) {
        console.log('Product not found');
        return;
    }

    console.log('Product ID:', product.id);
    console.log('SKU:', product.productNumber);
    console.log('Name:', product.name);
    console.log('');
    console.log('CURRENT DESCRIPTION:');
    console.log('-'.repeat(50));
    console.log(product.description);
    console.log('-'.repeat(50));

    // Remove the export control text (including HTML wrapper)
    let newDescription = product.description || '';

    // Remove the entire paragraph containing export control text
    // The text is: <p><span id="sa_content" style="color: #00ccff;">This product is subject to export control if exported from&nbsp;the European Union</span>.</p>
    newDescription = newDescription.replace(/<p><span[^>]*>This product is subject to export control[^<]*<\/span>\.<\/p>/gi, '');

    // Clean up any leftover empty paragraphs or double spaces
    newDescription = newDescription.replace(/<p>\s*<\/p>/gi, '');
    newDescription = newDescription.replace(/\s{2,}/g, ' ');
    newDescription = newDescription.trim();

    console.log('');
    console.log('NEW DESCRIPTION (after removal):');
    console.log('-'.repeat(50));
    console.log(newDescription);
    console.log('-'.repeat(50));

    // Update the product
    const updateRes = await httpRequest(CONFIG.apiUrl + '/product/' + product.id, {
        method: 'PATCH',
        headers: { 'Authorization': 'Bearer ' + token },
        body: {
            description: newDescription
        }
    });

    if (updateRes.status === 204 || updateRes.status === 200) {
        console.log('');
        console.log('SUCCESS: Export control text removed from product description!');
    } else {
        console.log('');
        console.log('ERROR:', updateRes.status, JSON.stringify(updateRes.data));
    }
}

main().catch(console.error);
