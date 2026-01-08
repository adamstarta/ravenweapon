/**
 * Recalculate All Product Prices
 *
 * Formula:
 *   New Netto  = Old Brutto
 *   New Brutto = Old Brutto × 1.081 (8.1% Swiss MWST)
 *
 * Usage:
 *   node recalculate-all-prices.js --env=staging
 *   node recalculate-all-prices.js --env=production
 */

const https = require('https');
const fs = require('fs');
const path = require('path');

const MWST_RATE = 0.081;

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
    let env = null;

    for (const arg of args) {
        if (arg.startsWith('--env=')) {
            env = arg.split('=')[1];
        }
    }

    if (!env || !['staging', 'production'].includes(env)) {
        console.error('Usage: node recalculate-all-prices.js --env=staging|production');
        process.exit(1);
    }

    return { env };
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

async function getAllProducts() {
    const allProducts = [];
    let page = 1;
    const limit = 500;

    while (true) {
        const res = await httpRequest(`${currentConfig.apiUrl}/search/product`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${accessToken}` },
            body: {
                limit: limit,
                page: page,
                includes: {
                    product: ['id', 'productNumber', 'name', 'price', 'active']
                }
            }
        });

        if (!res.data.data || res.data.data.length === 0) break;
        allProducts.push(...res.data.data);

        process.stdout.write(`\r  Fetched ${allProducts.length} products...`);

        if (res.data.data.length < limit) break;
        page++;
    }

    console.log(`\r  Fetched ${allProducts.length} products total.`);
    return allProducts;
}

async function getCurrencies() {
    const res = await httpRequest(`${currentConfig.apiUrl}/search/currency`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${accessToken}` },
        body: {
            limit: 50,
            includes: { currency: ['id', 'isoCode', 'isSystemDefault'] }
        }
    });

    const currencies = res.data.data || [];
    const chf = currencies.find(c => c.isoCode === 'CHF');
    const defaultCurrency = currencies.find(c => c.isSystemDefault);

    if (!chf) {
        throw new Error('CHF currency not found');
    }
    if (!defaultCurrency) {
        throw new Error('Default currency not found');
    }

    return {
        chfId: chf.id,
        defaultId: defaultCurrency.id,
        defaultIsoCode: defaultCurrency.isoCode
    };
}

function createBackup(env, products, chfCurrencyId) {
    const backupsDir = path.join(__dirname, 'backups');
    if (!fs.existsSync(backupsDir)) {
        fs.mkdirSync(backupsDir, { recursive: true });
    }

    const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
    const filename = `prices-backup-${env}-${timestamp}.json`;
    const filepath = path.join(backupsDir, filename);

    const backup = {
        environment: env,
        timestamp: new Date().toISOString(),
        mwstRate: MWST_RATE,
        chfCurrencyId: chfCurrencyId,
        productCount: products.length,
        products: products.map(p => {
            const chfPrice = p.price.find(pr => pr.currencyId === chfCurrencyId);
            return {
                id: p.id,
                productNumber: p.productNumber || 'N/A',
                name: p.name || 'Unknown',
                originalBrutto: chfPrice?.gross || 0,
                originalNetto: chfPrice?.net || 0,
                allPrices: p.price  // Store all prices for complete restore
            };
        })
    };

    fs.writeFileSync(filepath, JSON.stringify(backup, null, 2));
    return filepath;
}

async function updateProductPrice(productId, existingPrices, currencies, newChfNetto, newChfBrutto) {
    // Build price array: keep all existing prices, update only CHF
    const priceArray = [];

    // First, ensure default currency (EUR) is present
    const defaultPrice = existingPrices.find(p => p.currencyId === currencies.defaultId);
    if (defaultPrice) {
        priceArray.push({
            currencyId: currencies.defaultId,
            gross: defaultPrice.gross,
            net: defaultPrice.net,
            linked: false
        });
    } else {
        // If no default currency price, use CHF price as fallback
        priceArray.push({
            currencyId: currencies.defaultId,
            gross: newChfBrutto,
            net: newChfNetto,
            linked: false
        });
    }

    // Add updated CHF price
    priceArray.push({
        currencyId: currencies.chfId,
        gross: newChfBrutto,
        net: newChfNetto,
        linked: false
    });

    const res = await httpRequest(`${currentConfig.apiUrl}/product/${productId}`, {
        method: 'PATCH',
        headers: { 'Authorization': `Bearer ${accessToken}` },
        body: {
            price: priceArray
        }
    });

    if (res.status !== 204 && res.status !== 200) {
        // Return error details for debugging
        return { success: false, error: res.data };
    }

    return { success: true };
}

async function main() {
    const { env } = parseArgs();
    currentConfig = CONFIG[env];

    console.log('\n' + '='.repeat(70));
    console.log('  RECALCULATE ALL PRODUCT PRICES');
    console.log('  Formula: New Netto = Old Brutto, New Brutto = Old Brutto × 1.081');
    console.log('='.repeat(70));
    console.log(`\n  Environment: ${env.toUpperCase()}`);
    console.log(`  API URL: ${currentConfig.apiUrl}`);
    console.log(`  MWST Rate: ${(MWST_RATE * 100).toFixed(1)}%\n`);

    // Get API token
    console.log('Step 1: Authenticating...');
    await getToken();
    console.log('  API token obtained.\n');

    // Get currencies
    console.log('Step 2: Getting currency IDs...');
    const currencies = await getCurrencies();
    console.log(`  CHF Currency ID: ${currencies.chfId}`);
    console.log(`  Default Currency: ${currencies.defaultIsoCode} (${currencies.defaultId})\n`);

    // Fetch all products
    console.log('Step 3: Fetching all products...');
    const products = await getAllProducts();

    // Filter products with CHF prices
    const productsWithChfPrices = products.filter(p => {
        if (!p.price || p.price.length === 0) return false;
        const chfPrice = p.price.find(pr => pr.currencyId === currencies.chfId);
        return chfPrice && chfPrice.gross > 0;
    });
    console.log(`  Products with CHF prices: ${productsWithChfPrices.length}\n`);

    if (productsWithChfPrices.length === 0) {
        console.log('No products with CHF prices found. Exiting.');
        return;
    }

    // Create backup (using CHF prices)
    console.log('Step 4: Creating backup...');
    const backupPath = createBackup(env, productsWithChfPrices, currencies.chfId);
    console.log(`  Backup saved to: ${backupPath}\n`);

    // Confirmation
    console.log('='.repeat(70));
    console.log(`  READY TO UPDATE ${productsWithChfPrices.length} PRODUCTS`);
    console.log('='.repeat(70));
    console.log('\nProceeding with price updates...\n');

    // Update prices
    console.log('Step 5: Updating prices...');
    const results = { success: 0, failed: 0, skipped: 0, errors: [] };

    for (let i = 0; i < productsWithChfPrices.length; i++) {
        const product = productsWithChfPrices[i];
        const chfPrice = product.price.find(pr => pr.currencyId === currencies.chfId);
        const oldBrutto = chfPrice.gross;

        // Calculate new prices
        const newNetto = oldBrutto;  // Old Brutto becomes new Netto
        const newBrutto = oldBrutto * (1 + MWST_RATE);  // Add 8.1% MWST

        // Round to 2 decimal places
        const newNettoRounded = Math.round(newNetto * 100) / 100;
        const newBruttoRounded = Math.round(newBrutto * 100) / 100;

        try {
            const result = await updateProductPrice(product.id, product.price, currencies, newNettoRounded, newBruttoRounded);

            if (result.success) {
                results.success++;
            } else {
                results.failed++;
                const errMsg = typeof result.error === 'object' ? JSON.stringify(result.error).slice(0, 100) : result.error;
                results.errors.push({ product: product.productNumber, error: errMsg });
            }
        } catch (err) {
            results.failed++;
            results.errors.push({ product: product.productNumber, error: err.message });
        }

        // Progress indicator
        if ((i + 1) % 10 === 0 || i === productsWithChfPrices.length - 1) {
            const pct = Math.round(((i + 1) / productsWithChfPrices.length) * 100);
            process.stdout.write(`\r  Progress: ${i + 1}/${productsWithChfPrices.length} (${pct}%) - Success: ${results.success}, Failed: ${results.failed}`);
        }
    }

    console.log('\n');

    // Summary
    console.log('='.repeat(70));
    console.log('  SUMMARY');
    console.log('='.repeat(70));
    console.log(`\n  Environment:    ${env.toUpperCase()}`);
    console.log(`  Total Products: ${productsWithChfPrices.length}`);
    console.log(`  Successful:     ${results.success}`);
    console.log(`  Failed:         ${results.failed}`);
    console.log(`  Backup File:    ${backupPath}`);

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
        console.log('  ALL PRICES UPDATED SUCCESSFULLY!');
    } else {
        console.log('  COMPLETED WITH SOME ERRORS - Check backup and retry failed items');
    }
    console.log('='.repeat(70) + '\n');

    if (env === 'staging') {
        console.log('Next steps:');
        console.log('  1. Log into https://developing.ravenweapon.ch/admin');
        console.log('  2. Verify prices look correct');
        console.log('  3. Run: node recalculate-all-prices.js --env=production\n');
    } else {
        console.log('Next steps:');
        console.log('  1. Log into https://ravenweapon.ch/admin');
        console.log('  2. Verify prices look correct');
        console.log('  3. Check storefront displays correct prices\n');
    }
}

main().catch(err => {
    console.error('\nFatal error:', err.message);
    process.exit(1);
});
