/**
 * Fix ZRT Product Cover Images
 *
 * Sets the coverId on all ZRT-* products to their first product-media entry.
 * This fixes the "placeholder.png" issue where images exist but no cover is set.
 *
 * Usage:
 *   node fix-zrt-cover-images.js --dry-run    # Preview what will happen
 *   node fix-zrt-cover-images.js              # Execute fix
 */

const https = require('https');

// ============================================================
// CONFIGURATION
// ============================================================
const CONFIG = {
    shopware: {
        apiUrl: 'https://ravenweapon.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
    },
    productPrefix: 'ZRT-',
    requestDelay: 200
};

// ============================================================
// HELPERS
// ============================================================

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
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

// ============================================================
// SHOPWARE API
// ============================================================

let accessToken = null;
let tokenExpiry = 0;

async function getToken(forceRefresh = false) {
    const now = Date.now();
    if (!accessToken || forceRefresh || now >= tokenExpiry) {
        console.log('  Getting API token...');
        const res = await httpRequest(`${CONFIG.shopware.apiUrl}/oauth/token`, {
            method: 'POST',
            body: {
                grant_type: 'client_credentials',
                client_id: CONFIG.shopware.clientId,
                client_secret: CONFIG.shopware.clientSecret
            }
        });
        if (!res.data.access_token) {
            throw new Error('Failed to get token: ' + JSON.stringify(res.data));
        }
        accessToken = res.data.access_token;
        tokenExpiry = now + ((res.data.expires_in || 600) - 60) * 1000;
        console.log('  ✓ Token obtained');
    }
    return accessToken;
}

async function refreshToken() {
    accessToken = null;
    tokenExpiry = 0;
    return getToken(true);
}

async function getAllZRTProducts(retry = true) {
    const token = await getToken();
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: {
            limit: 500,
            filter: [{ type: 'prefix', field: 'productNumber', value: CONFIG.productPrefix }],
            associations: { media: {} }
        }
    });

    if (res.status === 401 && retry) {
        console.log('    ⟳ Token expired, refreshing...');
        await refreshToken();
        return getAllZRTProducts(false);
    }

    if (res.data.data) {
        return res.data.data;
    }
    return [];
}

async function getProductMediaLinks(productId, retry = true) {
    const token = await getToken();
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product-media`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: {
            limit: 100,
            filter: [{ type: 'equals', field: 'productId', value: productId }],
            sort: [{ field: 'position', order: 'ASC' }]
        }
    });

    if (res.status === 401 && retry) {
        await refreshToken();
        return getProductMediaLinks(productId, false);
    }

    if (res.data.data) {
        return res.data.data;
    }
    return [];
}

async function updateProductCover(productId, coverId, retry = true) {
    const token = await getToken();
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/product/${productId}`, {
        method: 'PATCH',
        headers: { 'Authorization': `Bearer ${token}` },
        body: { coverId: coverId }
    });

    if (res.status === 401 && retry) {
        await refreshToken();
        return updateProductCover(productId, coverId, false);
    }

    return res.status === 200 || res.status === 204;
}

// ============================================================
// MAIN
// ============================================================

async function main() {
    const args = process.argv.slice(2);
    const dryRun = args.includes('--dry-run');

    console.log('\n╔════════════════════════════════════════════════════════╗');
    console.log('║  FIX ZRT PRODUCT COVER IMAGES                          ║');
    console.log('╚════════════════════════════════════════════════════════╝');
    console.log(`  Mode: ${dryRun ? 'DRY RUN (no changes)' : 'LIVE'}`);
    console.log(`  Target: ${CONFIG.productPrefix}* products\n`);

    // Get all ZRT products
    console.log('  Fetching ZRT products from Shopware...');
    const products = await getAllZRTProducts();
    console.log(`  Found: ${products.length} products\n`);

    let stats = { checked: 0, fixed: 0, alreadySet: 0, noMedia: 0, failed: 0 };

    for (const product of products) {
        stats.checked++;
        const productNumber = product.productNumber;
        const productName = product.translated?.name || product.name || 'Unknown';

        console.log(`[${stats.checked}/${products.length}] ${productName}`);
        console.log(`    Number: ${productNumber}`);

        // Check if cover is already set
        if (product.coverId) {
            console.log(`    ✓ Cover already set: ${product.coverId.substring(0, 8)}...`);
            stats.alreadySet++;
            continue;
        }

        // Get product media links
        const mediaLinks = await getProductMediaLinks(product.id);

        if (mediaLinks.length === 0) {
            console.log('    ✗ No media linked to product');
            stats.noMedia++;
            continue;
        }

        // Find the first media link (lowest position)
        const firstMedia = mediaLinks[0];
        console.log(`    → Found ${mediaLinks.length} media, first: ${firstMedia.id.substring(0, 8)}...`);

        if (dryRun) {
            console.log(`    [DRY RUN] Would set coverId to: ${firstMedia.id}`);
            stats.fixed++;
            continue;
        }

        // Update product with coverId
        const success = await updateProductCover(product.id, firstMedia.id);
        if (success) {
            console.log(`    ✓ Cover image set`);
            stats.fixed++;
        } else {
            console.log(`    ✗ Failed to set cover`);
            stats.failed++;
        }

        await delay(CONFIG.requestDelay);
    }

    // Summary
    console.log('\n════════════════════════════════════════════════════════');
    console.log('  COMPLETE');
    console.log('════════════════════════════════════════════════════════');
    console.log(`  Checked:      ${stats.checked}`);
    console.log(`  Fixed:        ${stats.fixed}`);
    console.log(`  Already set:  ${stats.alreadySet}`);
    console.log(`  No media:     ${stats.noMedia}`);
    console.log(`  Failed:       ${stats.failed}`);
    console.log('════════════════════════════════════════════════════════\n');
}

main().catch(err => {
    console.error('\n  ✗ Fatal error:', err.message);
    process.exit(1);
});
