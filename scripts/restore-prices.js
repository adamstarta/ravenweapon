/**
 * Restore Product Prices from Backup
 *
 * Restores original Brutto and Netto prices from a backup JSON file.
 *
 * Usage:
 *   node restore-prices.js --file=backups/prices-backup-staging-2026-01-08.json
 */

const https = require('https');
const fs = require('fs');
const path = require('path');

const CONFIG = {
    staging: {
        apiUrl: 'https://developing.ravenweapon.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
    },
    production: {
        apiUrl: 'https://ravenweapon.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
    }
};

// Parse command line arguments
function parseArgs() {
    const args = process.argv.slice(2);
    let file = null;

    for (const arg of args) {
        if (arg.startsWith('--file=')) {
            file = arg.split('=')[1];
        }
    }

    if (!file) {
        console.error('Usage: node restore-prices.js --file=backups/prices-backup-{env}-{timestamp}.json');
        process.exit(1);
    }

    return { file };
}

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
                    resolve({ status: res.statusCode, data: data ? JSON.parse(data) : {} });
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
let currentConfig = null;

async function getToken() {
    const res = await httpRequest(`${currentConfig.apiUrl}/oauth/token`, {
        method: 'POST',
        body: {
            grant_type: 'client_credentials',
            client_id: currentConfig.clientId,
            client_secret: currentConfig.clientSecret
        }
    });

    if (!res.data.access_token) {
        throw new Error('Failed to get API token: ' + JSON.stringify(res.data));
    }

    accessToken = res.data.access_token;
    return accessToken;
}

async function getCurrencyId() {
    const res = await httpRequest(`${currentConfig.apiUrl}/search/currency`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${accessToken}` },
        body: {
            limit: 10,
            filter: [{ type: 'equals', field: 'isoCode', value: 'CHF' }],
            includes: { currency: ['id', 'isoCode'] }
        }
    });

    const currency = res.data.data?.[0];
    if (!currency) {
        throw new Error('CHF currency not found');
    }

    return currency.id;
}

async function updateProductPrice(productId, currencyId, netto, brutto) {
    const res = await httpRequest(`${currentConfig.apiUrl}/product/${productId}`, {
        method: 'PATCH',
        headers: { 'Authorization': `Bearer ${accessToken}` },
        body: {
            price: [{
                currencyId: currencyId,
                gross: brutto,
                net: netto,
                linked: false
            }]
        }
    });

    return res.status === 204 || res.status === 200;
}

async function main() {
    const { file } = parseArgs();

    // Resolve file path
    let filepath = file;
    if (!path.isAbsolute(filepath)) {
        filepath = path.join(__dirname, filepath);
    }

    if (!fs.existsSync(filepath)) {
        console.error(`Backup file not found: ${filepath}`);
        process.exit(1);
    }

    // Read backup
    console.log('\n' + '='.repeat(70));
    console.log('  RESTORE PRODUCT PRICES FROM BACKUP');
    console.log('='.repeat(70));

    console.log(`\nLoading backup: ${filepath}`);
    const backup = JSON.parse(fs.readFileSync(filepath, 'utf8'));

    console.log(`\n  Environment:    ${backup.environment.toUpperCase()}`);
    console.log(`  Backup Date:    ${backup.timestamp}`);
    console.log(`  Product Count:  ${backup.productCount}`);

    // Set config based on backup environment
    currentConfig = CONFIG[backup.environment];
    if (!currentConfig) {
        console.error(`Unknown environment in backup: ${backup.environment}`);
        process.exit(1);
    }

    console.log(`  API URL:        ${currentConfig.apiUrl}\n`);

    // Get API token
    console.log('Step 1: Authenticating...');
    await getToken();
    console.log('  API token obtained.\n');

    // Get currency ID
    console.log('Step 2: Getting CHF currency ID...');
    const currencyId = await getCurrencyId();
    console.log(`  Currency ID: ${currencyId}\n`);

    // Confirmation
    console.log('='.repeat(70));
    console.log(`  READY TO RESTORE ${backup.products.length} PRODUCTS TO ORIGINAL PRICES`);
    console.log('='.repeat(70));
    console.log('\nProceeding with restore...\n');

    // Restore prices
    console.log('Step 3: Restoring prices...');
    const results = { success: 0, failed: 0, errors: [] };

    for (let i = 0; i < backup.products.length; i++) {
        const product = backup.products[i];

        try {
            const success = await updateProductPrice(
                product.id,
                currencyId,
                product.originalNetto,
                product.originalBrutto
            );

            if (success) {
                results.success++;
            } else {
                results.failed++;
                results.errors.push({ product: product.productNumber, error: 'API returned non-success status' });
            }
        } catch (err) {
            results.failed++;
            results.errors.push({ product: product.productNumber, error: err.message });
        }

        // Progress indicator
        if ((i + 1) % 10 === 0 || i === backup.products.length - 1) {
            const pct = Math.round(((i + 1) / backup.products.length) * 100);
            process.stdout.write(`\r  Progress: ${i + 1}/${backup.products.length} (${pct}%) - Success: ${results.success}, Failed: ${results.failed}`);
        }
    }

    console.log('\n');

    // Summary
    console.log('='.repeat(70));
    console.log('  RESTORE SUMMARY');
    console.log('='.repeat(70));
    console.log(`\n  Environment:    ${backup.environment.toUpperCase()}`);
    console.log(`  Total Products: ${backup.products.length}`);
    console.log(`  Restored:       ${results.success}`);
    console.log(`  Failed:         ${results.failed}`);

    if (results.errors.length > 0) {
        console.log('\n  Errors:');
        for (const err of results.errors.slice(0, 10)) {
            console.log(`    - ${err.product}: ${err.error}`);
        }
        if (results.errors.length > 10) {
            console.log(`    ... and ${results.errors.length - 10} more errors`);
        }
    }

    console.log('\n' + '='.repeat(70));
    if (results.failed === 0) {
        console.log('  ALL PRICES RESTORED SUCCESSFULLY!');
    } else {
        console.log('  COMPLETED WITH SOME ERRORS');
    }
    console.log('='.repeat(70) + '\n');
}

main().catch(err => {
    console.error('\nFatal error:', err.message);
    process.exit(1);
});
