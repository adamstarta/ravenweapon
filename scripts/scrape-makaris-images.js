/**
 * Scrape Makaris Images & Compare with Shopware
 *
 * 1. Reads Excel to get product numbers and Makaris IDs
 * 2. Scrapes Makaris for all image URLs per product
 * 3. Compares with Shopware media count
 * 4. Reports differences
 *
 * Usage:
 *   node scrape-makaris-images.js --dry-run    # Just compare, don't sync
 *   node scrape-makaris-images.js --sync       # Compare and sync missing images
 */

const https = require('https');
const http = require('http');
const { URL } = require('url');

// ============================================================
// CONFIGURATION
// ============================================================
const CONFIG = {
    excelFile: String.raw`c:\Users\alama\Downloads\products (2).xlsx`,
    makaris: {
        loginUrl: 'https://app.makaris.ch/login',
        productsUrl: 'https://app.makaris.ch/u/products',
        credentials: {
            userId: '4842',
            email: 'nikola@ravenweapon.ch',
            password: '100%Ravenweapon...'
        }
    },
    shopware: {
        apiUrl: 'https://ravenweapon.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
    },
    requestDelay: 200
};

// ============================================================
// HELPERS
// ============================================================

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function extractMakarisId(publicUrl) {
    if (!publicUrl) return null;
    // URL format: http://raven.makaris.ch/en/products/product-name-5512
    const match = publicUrl.match(/-(\d+)$/);
    return match ? match[1] : null;
}

function httpRequest(url, options = {}) {
    return new Promise((resolve, reject) => {
        const urlObj = new URL(url);
        const protocol = urlObj.protocol === 'https:' ? https : http;
        const reqOptions = {
            hostname: urlObj.hostname,
            port: urlObj.port || (urlObj.protocol === 'https:' ? 443 : 80),
            path: urlObj.pathname + urlObj.search,
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...options.headers
            }
        };
        const req = protocol.request(reqOptions, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try {
                    resolve({ status: res.statusCode, data: JSON.parse(data), headers: res.headers });
                } catch (e) {
                    resolve({ status: res.statusCode, data: data, headers: res.headers });
                }
            });
        });
        req.on('error', reject);
        if (options.body) {
            const body = typeof options.body === 'string' ? options.body : JSON.stringify(options.body);
            req.write(body);
        }
        req.end();
    });
}

// ============================================================
// SHOPWARE API
// ============================================================

let accessToken = null;
let tokenExpiry = 0;

async function getToken(forceRefresh = false) {
    const now = Date.now();
    if (!accessToken || forceRefresh || now >= tokenExpiry) {
        const res = await httpRequest(`${CONFIG.shopware.apiUrl}/oauth/token`, {
            method: 'POST',
            body: {
                grant_type: 'client_credentials',
                client_id: CONFIG.shopware.clientId,
                client_secret: CONFIG.shopware.clientSecret
            }
        });
        if (!res.data.access_token) throw new Error('Failed to get Shopware token');
        accessToken = res.data.access_token;
        tokenExpiry = now + ((res.data.expires_in || 600) - 60) * 1000;
    }
    return accessToken;
}

async function refreshToken() {
    accessToken = null;
    tokenExpiry = 0;
    return getToken(true);
}

async function getShopwareProduct(productNumber, retry = true) {
    const token = await getToken();
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: {
            limit: 1,
            filter: [{ type: 'equals', field: 'productNumber', value: productNumber }],
            associations: { media: { associations: { media: {} } } }
        }
    });

    if (res.status === 401 && retry) {
        await refreshToken();
        return getShopwareProduct(productNumber, false);
    }

    if (res.data.data && res.data.data.length > 0) {
        return res.data.data[0];
    }
    return null;
}

// ============================================================
// MAIN
// ============================================================

async function main() {
    const args = process.argv.slice(2);
    const dryRun = !args.includes('--sync');

    console.log('\n' + '='.repeat(70));
    console.log('  MAKARIS vs SHOPWARE IMAGE COMPARISON');
    console.log('='.repeat(70));
    console.log(`  Mode: ${dryRun ? 'DRY RUN (compare only)' : 'SYNC MODE'}`);

    // Load Excel
    let XLSX;
    try {
        XLSX = require('xlsx');
    } catch (e) {
        console.log('  Installing xlsx...');
        require('child_process').execSync('npm install xlsx', { stdio: 'inherit' });
        XLSX = require('xlsx');
    }

    console.log(`\n  Loading Excel: ${CONFIG.excelFile}`);
    const workbook = XLSX.readFile(CONFIG.excelFile);
    const sheet = workbook.Sheets[workbook.SheetNames[0]];
    const products = XLSX.utils.sheet_to_json(sheet);
    console.log(`  Total products in Excel: ${products.length}`);

    // Build product map with Makaris IDs and Excel image counts
    const productMap = [];
    for (const product of products) {
        const makarisId = extractMakarisId(product.public_url);

        // Count Excel images
        let excelImageCount = 0;
        const excelImages = [];
        for (let i = 1; i <= 10; i++) {
            const imgUrl = product[`image_${i}`];
            if (imgUrl && typeof imgUrl === 'string' && imgUrl.startsWith('http')) {
                excelImageCount++;
                excelImages.push(imgUrl);
            }
        }

        productMap.push({
            number: product.number,
            name: product.name_en || product.name_de || 'Unknown',
            makarisId: makarisId,
            excelImageCount: excelImageCount,
            excelImages: excelImages,
            sellingPrice: product.selling_price
        });
    }

    console.log(`  Products with Makaris ID: ${productMap.filter(p => p.makarisId).length}`);
    console.log(`  Products without Makaris ID: ${productMap.filter(p => !p.makarisId).length}`);

    // Get Shopware token
    console.log('\n  Getting Shopware API token...');
    await getToken();

    // Compare with Shopware
    console.log('\n  Comparing with Shopware...\n');

    const results = {
        total: 0,
        found: 0,
        notFound: [],
        imageMismatch: [],
        priceMismatch: []
    };

    for (const product of productMap) {
        results.total++;

        // Get Shopware product
        const swProduct = await getShopwareProduct(product.number);

        if (!swProduct) {
            results.notFound.push(product);
            console.log(`[${results.total}/${productMap.length}] ${product.number} - NOT FOUND in Shopware`);
            await delay(CONFIG.requestDelay);
            continue;
        }

        results.found++;

        // Get Shopware media count and URLs
        const swMediaCount = swProduct.media?.length || 0;
        const swMediaUrls = (swProduct.media || []).map(m => m.media?.url).filter(Boolean);

        // Get Shopware price
        const swPrice = swProduct.price?.[0]?.gross || 0;

        // Compare images (Excel vs Shopware)
        if (product.excelImageCount !== swMediaCount) {
            results.imageMismatch.push({
                ...product,
                shopwareImages: swMediaCount,
                shopwareUrls: swMediaUrls,
                diff: product.excelImageCount - swMediaCount
            });
        }

        // Compare price
        const priceDiff = Math.abs((product.sellingPrice || 0) - swPrice);
        if (priceDiff > 0.01) {
            results.priceMismatch.push({
                number: product.number,
                name: product.name,
                excelPrice: product.sellingPrice,
                shopwarePrice: swPrice
            });
        }

        // Progress
        const status = [];
        if (product.excelImageCount !== swMediaCount) status.push(`IMG:${product.excelImageCount}/${swMediaCount}`);
        if (priceDiff > 0.01) status.push('PRICE');
        const statusStr = status.length > 0 ? ` [${status.join(', ')}]` : ' OK';
        console.log(`[${results.total}/${productMap.length}] ${product.number}${statusStr}`);

        await delay(CONFIG.requestDelay);
    }

    // Print results
    console.log('\n' + '='.repeat(70));
    console.log('  RESULTS');
    console.log('='.repeat(70));

    console.log(`\n  Total: ${results.total} | Found: ${results.found} | Not Found: ${results.notFound.length}`);

    // Not Found
    if (results.notFound.length > 0) {
        console.log('\n' + '-'.repeat(70));
        console.log('  PRODUCTS NOT IN SHOPWARE (' + results.notFound.length + ')');
        console.log('-'.repeat(70));
        results.notFound.slice(0, 10).forEach(p => {
            console.log(`  ${p.number} - ${p.name}`);
        });
        if (results.notFound.length > 10) {
            console.log(`  ... and ${results.notFound.length - 10} more`);
        }
    }

    // Image Mismatches
    if (results.imageMismatch.length > 0) {
        console.log('\n' + '-'.repeat(70));
        console.log('  IMAGE MISMATCHES (' + results.imageMismatch.length + ')');
        console.log('-'.repeat(70));
        console.log('  Product Number                   Excel  Shopware  Diff   Makaris ID');
        console.log('  ' + '-'.repeat(65));

        // Sort by diff (most missing first)
        results.imageMismatch.sort((a, b) => b.diff - a.diff);

        results.imageMismatch.forEach(p => {
            const num = p.number.padEnd(30);
            const excel = String(p.excelImageCount).padStart(5);
            const sw = String(p.shopwareImages).padStart(8);
            const diff = (p.diff > 0 ? '+' : '') + String(p.diff).padStart(5);
            const mid = (p.makarisId || 'N/A').padStart(10);
            console.log(`  ${num} ${excel} ${sw} ${diff} ${mid}`);
        });

        // Summary of missing
        const totalMissing = results.imageMismatch
            .filter(p => p.diff > 0)
            .reduce((sum, p) => sum + p.diff, 0);
        const totalExtra = results.imageMismatch
            .filter(p => p.diff < 0)
            .reduce((sum, p) => sum + Math.abs(p.diff), 0);

        console.log('\n  Image Summary:');
        console.log(`    Missing in Shopware: ${totalMissing} images`);
        console.log(`    Extra in Shopware: ${totalExtra} images`);
    }

    // Price Mismatches
    if (results.priceMismatch.length > 0) {
        console.log('\n' + '-'.repeat(70));
        console.log('  PRICE MISMATCHES (' + results.priceMismatch.length + ')');
        console.log('-'.repeat(70));
        results.priceMismatch.slice(0, 10).forEach(p => {
            console.log(`  ${p.number}: Excel CHF ${p.excelPrice?.toFixed(2)} vs Shopware CHF ${p.shopwarePrice?.toFixed(2)}`);
        });
        if (results.priceMismatch.length > 10) {
            console.log(`  ... and ${results.priceMismatch.length - 10} more`);
        }
    }

    // Summary
    console.log('\n' + '='.repeat(70));
    console.log('  SUMMARY');
    console.log('='.repeat(70));
    console.log(`  Products compared:     ${results.total}`);
    console.log(`  Found in Shopware:     ${results.found}`);
    console.log(`  Not found:             ${results.notFound.length}`);
    console.log(`  Image mismatches:      ${results.imageMismatch.length}`);
    console.log(`  Price mismatches:      ${results.priceMismatch.length}`);
    console.log('='.repeat(70) + '\n');

    // If sync mode, show what would be synced
    if (!dryRun && results.imageMismatch.length > 0) {
        console.log('\n  SYNC MODE: Would sync images for products with mismatches.');
        console.log('  (Sync functionality to be implemented)');
    }
}

main().catch(err => {
    console.error('\n  Fatal error:', err.message);
    process.exit(1);
});
