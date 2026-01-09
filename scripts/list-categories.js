/**
 * List all Shopware categories
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

    // Get categories
    const catRes = await httpRequest(CONFIG.apiUrl + '/search/category', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + token },
        body: {
            limit: 500,
            includes: {
                category: ['id', 'name', 'parentId', 'level', 'active', 'productCount']
            }
        }
    });

    const categories = catRes.data.data || [];

    // Sort by level and name
    categories.sort((a, b) => {
        if (a.level !== b.level) return a.level - b.level;
        return (a.name || '').localeCompare(b.name || '');
    });

    console.log('SHOPWARE CATEGORIES (' + categories.length + ' total)\n');
    console.log('Level | Products | Active | Name');
    console.log('-'.repeat(70));

    for (const cat of categories) {
        const indent = '  '.repeat(cat.level || 0);
        const count = String(cat.productCount || 0).padStart(8);
        const active = cat.active ? 'Yes' : 'No ';
        console.log(`${cat.level || 0}     | ${count} | ${active}    | ${indent}${cat.name || 'Unknown'}`);
    }
}

main().catch(console.error);
