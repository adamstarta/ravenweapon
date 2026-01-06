/**
 * Sync AIMPACT Product Images
 *
 * ONLY affects AIM-* products from Excel file:
 * 1. Deletes existing product-media links (not the media files themselves)
 * 2. Uploads fresh images from Excel
 * 3. Sets cover image (coverId) on product
 *
 * Usage:
 *   node sync-aimpact-images.js --dry-run    # Preview what will happen
 *   node sync-aimpact-images.js              # Execute sync
 */

const https = require('https');

// ============================================================
// CONFIGURATION
// ============================================================
const CONFIG = {
    excelFile: String.raw`c:\Users\alama\Downloads\products (2).xlsx`,
    shopware: {
        apiUrl: 'https://ravenweapon.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
    },
    // ONLY process AIM-* products
    productPrefix: 'AIM-',
    requestDelay: 300
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

function generateUUID() {
    return 'xxxxxxxxxxxx4xxxyxxxxxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

function getExtension(url) {
    const match = url.match(/\.(\w+)(?:\?|$)/);
    return match ? match[1].toLowerCase() : 'jpg';
}

function sanitizeFilename(filename) {
    let decoded = filename;
    try { decoded = decodeURIComponent(filename); } catch (e) {}
    return decoded
        .replace(/[%]/g, 'pct')
        .replace(/[()]/g, '')
        .replace(/[<>:"/\\|?*]/g, '')
        .replace(/\s+/g, '-')
        .replace(/[^\w\-_.]/g, '')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}

function generateUniqueFilename(productNumber, imageUrl, position) {
    const urlPath = new URL(imageUrl).pathname;
    let originalName = urlPath.split('/').pop().replace(/\.[^.]+$/, '') || `image-${position}`;
    const sanitized = sanitizeFilename(originalName);
    return `${productNumber}-pos${position}-${sanitized}`;
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
        console.log('  Token obtained');
    }
    return accessToken;
}

async function refreshToken() {
    accessToken = null;
    tokenExpiry = 0;
    return getToken(true);
}

async function findProductByNumber(productNumber, retry = true) {
    const token = await getToken();
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: {
            limit: 1,
            filter: [{ type: 'equals', field: 'productNumber', value: productNumber }],
            associations: { media: {} }
        }
    });

    if (res.status === 401 && retry) {
        console.log('    Token expired, refreshing...');
        await refreshToken();
        return findProductByNumber(productNumber, false);
    }

    if (res.data.data && res.data.data.length > 0) {
        return res.data.data[0];
    }
    return null;
}

async function getProductMediaLinks(productId) {
    const token = await getToken();
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product-media`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: {
            limit: 100,
            filter: [{ type: 'equals', field: 'productId', value: productId }]
        }
    });

    if (res.data.data) {
        return res.data.data;
    }
    return [];
}

async function deleteProductMediaLink(productMediaId, retry = true) {
    const token = await getToken();
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/product-media/${productMediaId}`, {
        method: 'DELETE',
        headers: { 'Authorization': `Bearer ${token}` }
    });

    if (res.status === 401 && retry) {
        await refreshToken();
        return deleteProductMediaLink(productMediaId, false);
    }

    return res.status === 204 || res.status === 200;
}

let cachedMediaFolderId = null;

async function getProductMediaFolderId() {
    if (cachedMediaFolderId) return cachedMediaFolderId;
    const token = await getToken();
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/search/media-folder`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: {
            limit: 1,
            filter: [{ type: 'equals', field: 'name', value: 'Product Media' }]
        }
    });
    if (res.data.data && res.data.data.length > 0) {
        cachedMediaFolderId = res.data.data[0].id;
        return cachedMediaFolderId;
    }
    throw new Error('Product Media folder not found');
}

async function uploadMedia(imageUrl, productNumber, position, retry = true) {
    const token = await getToken();
    const mediaId = generateUUID();
    const extension = getExtension(imageUrl);
    const mediaFolderId = await getProductMediaFolderId();
    const fileName = generateUniqueFilename(productNumber, imageUrl, position);

    try {
        // Create media entity
        const createRes = await httpRequest(`${CONFIG.shopware.apiUrl}/media`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: { id: mediaId, mediaFolderId: mediaFolderId }
        });

        if (createRes.status === 401 && retry) {
            await refreshToken();
            return uploadMedia(imageUrl, productNumber, position, false);
        }

        if (createRes.status !== 200 && createRes.status !== 204) {
            throw new Error(`Create failed: ${createRes.status}`);
        }

        await delay(200);

        // Upload from URL
        const uploadRes = await httpRequest(
            `${CONFIG.shopware.apiUrl}/_action/media/${mediaId}/upload?extension=${extension}&fileName=${encodeURIComponent(fileName)}`,
            {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${token}` },
                body: { url: imageUrl }
            }
        );

        if (uploadRes.status !== 200 && uploadRes.status !== 204) {
            throw new Error(`Upload failed: ${uploadRes.status}`);
        }

        return mediaId;
    } catch (e) {
        console.log(`      Upload failed: ${e.message}`);
        return null;
    }
}

async function linkMediaToProduct(productId, mediaId, position, retry = true) {
    const token = await getToken();
    const productMediaId = generateUUID();
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/product-media`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: { id: productMediaId, productId, mediaId, position }
    });

    if (res.status === 401 && retry) {
        await refreshToken();
        return linkMediaToProduct(productId, mediaId, position, false);
    }

    if (res.status === 200 || res.status === 204) {
        return productMediaId;
    }
    return null;
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

    console.log('\n========================================================');
    console.log('  SYNC AIMPACT PRODUCT IMAGES');
    console.log('========================================================');
    console.log(`  Mode: ${dryRun ? 'DRY RUN (no changes)' : 'LIVE'}`);
    console.log(`  Target: ${CONFIG.productPrefix}* products only\n`);

    // Load Excel
    let XLSX;
    try {
        XLSX = require('xlsx');
    } catch (e) {
        console.log('  Installing xlsx...');
        require('child_process').execSync('npm install xlsx', { stdio: 'inherit' });
        XLSX = require('xlsx');
    }

    console.log(`  Loading: ${CONFIG.excelFile}`);
    const workbook = XLSX.readFile(CONFIG.excelFile);
    const sheet = workbook.Sheets[workbook.SheetNames[0]];
    let products = XLSX.utils.sheet_to_json(sheet);

    // Filter to AIM-* products only
    products = products.filter(p => p.number && p.number.startsWith(CONFIG.productPrefix));
    console.log(`  AIMPACT products in Excel: ${products.length}\n`);

    let stats = { processed: 0, cleaned: 0, uploaded: 0, skipped: 0, notFound: 0, coverSet: 0 };

    for (const product of products) {
        stats.processed++;
        const productNumber = product.number;
        const productName = product.name_en || product.name_de || 'Unknown';

        // Collect image URLs from Excel
        const imageUrls = [];
        for (let i = 1; i <= 5; i++) {
            const url = product[`image_${i}`];
            if (url && typeof url === 'string' && url.startsWith('http')) {
                imageUrls.push({ position: i, url });
            }
        }

        console.log(`[${stats.processed}/${products.length}] ${productName}`);
        console.log(`    Number: ${productNumber}`);
        console.log(`    Excel images: ${imageUrls.length}`);

        if (imageUrls.length === 0) {
            console.log('    -> Skipping (no images in Excel)');
            stats.skipped++;
            continue;
        }

        // Find product in Shopware
        const shopwareProduct = await findProductByNumber(productNumber);
        if (!shopwareProduct) {
            console.log('    NOT FOUND in Shopware');
            stats.notFound++;
            continue;
        }

        const productId = shopwareProduct.id;
        console.log(`    -> Found: ${productId.substring(0, 8)}...`);

        // Get existing media links
        const existingMedia = await getProductMediaLinks(productId);
        console.log(`    -> Existing media: ${existingMedia.length}`);

        if (dryRun) {
            console.log(`    [DRY RUN] Would delete ${existingMedia.length} media links`);
            console.log(`    [DRY RUN] Would upload ${imageUrls.length} images`);
            stats.cleaned += existingMedia.length;
            stats.uploaded += imageUrls.length;
            continue;
        }

        // Step 1: Delete existing product-media links
        if (existingMedia.length > 0) {
            console.log(`    Deleting ${existingMedia.length} existing media links...`);
            for (const pm of existingMedia) {
                const deleted = await deleteProductMediaLink(pm.id);
                if (deleted) {
                    stats.cleaned++;
                }
                await delay(100);
            }
            console.log(`    Cleaned`);
        }

        // Step 2: Upload fresh images
        console.log(`    Uploading ${imageUrls.length} images...`);
        let firstProductMediaId = null;

        for (const img of imageUrls) {
            const mediaId = await uploadMedia(img.url, productNumber, img.position);
            if (mediaId) {
                const productMediaId = await linkMediaToProduct(productId, mediaId, img.position);
                if (productMediaId) {
                    console.log(`      Image ${img.position} uploaded`);
                    stats.uploaded++;

                    // Save first product-media ID for cover
                    if (img.position === 1) {
                        firstProductMediaId = productMediaId;
                    }
                }
            }
            await delay(CONFIG.requestDelay);
        }

        // Step 3: Set cover image
        if (firstProductMediaId) {
            const coverSet = await updateProductCover(productId, firstProductMediaId);
            if (coverSet) {
                console.log(`    Cover image set`);
                stats.coverSet++;
            }
        }

        await delay(CONFIG.requestDelay);
    }

    // Summary
    console.log('\n========================================================');
    console.log('  COMPLETE');
    console.log('========================================================');
    console.log(`  Processed:    ${stats.processed}`);
    console.log(`  Cleaned:      ${stats.cleaned} media links removed`);
    console.log(`  Uploaded:     ${stats.uploaded} images`);
    console.log(`  Cover set:    ${stats.coverSet}`);
    console.log(`  Skipped:      ${stats.skipped} (no images)`);
    console.log(`  Not found:    ${stats.notFound}`);
    console.log('========================================================\n');
}

main().catch(err => {
    console.error('\n  Fatal error:', err.message);
    process.exit(1);
});
