/**
 * Sync Product Images to Shopware - PERFECT VERSION
 *
 * Reads product image URLs from Excel and uploads them to Shopware.
 * Excludes "Raven Swiss" products.
 *
 * Features:
 *   - Syncs ALL image columns: image_1, image_2, image_3, image_4, image_5
 *   - Unique filenames: Prefixes with product number to avoid duplicates
 *   - Sanitizes filenames: Handles special characters (%, parentheses, etc.)
 *   - Comprehensive reporting: Shows exactly what succeeded/failed
 *
 * Usage:
 *   node sync-product-images.js              # Sync all products
 *   node sync-product-images.js --dry-run    # Show what would be synced
 *   node sync-product-images.js --product NUMBER  # Sync specific product
 */

const https = require('https');
const fs = require('fs');
const path = require('path');

// ============================================================
// CONFIGURATION
// ============================================================
const CONFIG = {
    // Excel file path
    excelFile: String.raw`c:\Users\alama\Downloads\products (2).xlsx`,

    // Shopware API
    shopware: {
        baseUrl: 'https://ravenweapon.ch',
        apiUrl: 'https://ravenweapon.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
    },

    // Exclude pattern
    excludePattern: 'Raven Swiss',

    // Rate limiting
    requestDelay: 500
};

// ============================================================
// HELPERS
// ============================================================

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Make HTTPS request
 */
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

/**
 * Download file from URL and return as buffer
 */
function downloadFile(url) {
    return new Promise((resolve, reject) => {
        const urlObj = new URL(url);
        const protocol = urlObj.protocol === 'https:' ? https : require('http');

        protocol.get(url, (res) => {
            if (res.statusCode === 301 || res.statusCode === 302) {
                // Follow redirect
                downloadFile(res.headers.location).then(resolve).catch(reject);
                return;
            }

            if (res.statusCode !== 200) {
                reject(new Error(`Failed to download: ${res.statusCode}`));
                return;
            }

            const chunks = [];
            res.on('data', chunk => chunks.push(chunk));
            res.on('end', () => resolve(Buffer.concat(chunks)));
            res.on('error', reject);
        }).on('error', reject);
    });
}

/**
 * Get file extension from URL
 */
function getExtension(url) {
    const match = url.match(/\.(\w+)(?:\?|$)/);
    return match ? match[1].toLowerCase() : 'jpg';
}

/**
 * Sanitize filename - remove/replace problematic characters
 */
function sanitizeFilename(filename) {
    // URL decode first (handles %20, %28, etc.)
    let decoded = filename;
    try {
        decoded = decodeURIComponent(filename);
    } catch (e) {
        // If decode fails, use original
    }

    // Replace problematic characters
    return decoded
        .replace(/[%]/g, 'pct')           // Replace % with 'pct'
        .replace(/[()]/g, '')              // Remove parentheses
        .replace(/[<>:"/\\|?*]/g, '')      // Remove invalid filename chars
        .replace(/\s+/g, '-')              // Replace spaces with dashes
        .replace(/[^\w\-_.]/g, '')         // Remove other special chars
        .replace(/-+/g, '-')               // Collapse multiple dashes
        .replace(/^-|-$/g, '');            // Trim leading/trailing dashes
}

/**
 * Generate unique filename with product number prefix
 */
function generateUniqueFilename(productNumber, imageUrl, position) {
    const urlPath = new URL(imageUrl).pathname;
    let originalName = urlPath.split('/').pop().replace(/\.[^.]+$/, '') || `image-${position}`;

    // Sanitize the original filename
    const sanitized = sanitizeFilename(originalName);

    // Prefix with product number to ensure uniqueness
    return `${productNumber}-pos${position}-${sanitized}`;
}

/**
 * Generate Shopware-compatible UUID (32 hex chars, no dashes)
 */
function generateUUID() {
    return 'xxxxxxxxxxxx4xxxyxxxxxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

// ============================================================
// SHOPWARE API
// ============================================================

let accessToken = null;
let tokenExpiry = 0;

/**
 * Get Shopware API token (with auto-refresh)
 */
async function getToken(forceRefresh = false) {
    const now = Date.now();

    // Refresh token if expired or forced
    if (!accessToken || forceRefresh || now >= tokenExpiry) {
        console.log('  Getting Shopware API token...');
        const res = await httpRequest(`${CONFIG.shopware.apiUrl}/oauth/token`, {
            method: 'POST',
            body: {
                grant_type: 'client_credentials',
                client_id: CONFIG.shopware.clientId,
                client_secret: CONFIG.shopware.clientSecret
            }
        });

        if (!res.data.access_token) {
            throw new Error('Failed to get API token: ' + JSON.stringify(res.data));
        }

        accessToken = res.data.access_token;
        // Token usually expires in 600 seconds, refresh 60 seconds early
        tokenExpiry = now + ((res.data.expires_in || 600) - 60) * 1000;
        console.log('  ✓ Token obtained');
    }

    return accessToken;
}

/**
 * Force token refresh (call when getting 401 errors)
 */
async function refreshToken() {
    accessToken = null;
    tokenExpiry = 0;
    return getToken(true);
}

/**
 * Search for product by product number (with 401 retry)
 */
async function findProductByNumber(productNumber, retry = true) {
    const token = await getToken();

    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: {
            limit: 1,
            filter: [
                { type: 'equals', field: 'productNumber', value: productNumber }
            ],
            associations: {
                media: {}
            }
        }
    });

    // Handle token expiration - retry once with fresh token
    if (res.status === 401 && retry) {
        console.log('    ⟳ Token expired, refreshing...');
        await refreshToken();
        return findProductByNumber(productNumber, false);
    }

    if (res.data.data && res.data.data.length > 0) {
        return res.data.data[0];
    }
    return null;
}

/**
 * Get or create product media folder ID
 */
let cachedMediaFolderId = null;

async function getProductMediaFolderId() {
    if (cachedMediaFolderId) return cachedMediaFolderId;

    const token = await getToken();

    // Search for "Product Media" folder
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/search/media-folder`, {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
        },
        body: {
            limit: 1,
            filter: [
                { type: 'equals', field: 'name', value: 'Product Media' }
            ]
        }
    });

    if (res.data.data && res.data.data.length > 0) {
        cachedMediaFolderId = res.data.data[0].id;
        console.log(`  ✓ Found media folder: ${cachedMediaFolderId.substring(0, 8)}...`);
        return cachedMediaFolderId;
    }

    // Fallback: get any media folder
    const fallbackRes = await httpRequest(`${CONFIG.shopware.apiUrl}/search/media-folder`, {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
        },
        body: { limit: 1 }
    });

    if (fallbackRes.data.data && fallbackRes.data.data.length > 0) {
        cachedMediaFolderId = fallbackRes.data.data[0].id;
        console.log(`  ✓ Using fallback media folder: ${cachedMediaFolderId.substring(0, 8)}...`);
        return cachedMediaFolderId;
    }

    throw new Error('No media folder found in Shopware');
}

/**
 * Upload media to Shopware
 * @param {string} imageUrl - URL of the image to upload
 * @param {string} productId - Shopware product ID
 * @param {string} productNumber - Product number for unique filename
 * @param {number} position - Image position (1-5)
 */
async function uploadMedia(imageUrl, productId, productNumber, position) {
    const token = await getToken();
    const mediaId = generateUUID();
    const extension = getExtension(imageUrl);
    const mediaFolderId = await getProductMediaFolderId();

    // Generate unique, sanitized filename with product number prefix
    const fileName = generateUniqueFilename(productNumber, imageUrl, position);

    try {
        // Step 1: Create media entity with folder ID
        const createRes = await httpRequest(`${CONFIG.shopware.apiUrl}/media`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            },
            body: {
                id: mediaId,
                mediaFolderId: mediaFolderId
            }
        });

        if (createRes.status !== 200 && createRes.status !== 204) {
            console.log(`      Debug create response: ${JSON.stringify(createRes.data).substring(0, 200)}`);
            throw new Error(`Failed to create media: ${createRes.status}`);
        }

        await delay(300);

        // Step 2: Upload from URL
        const uploadRes = await httpRequest(
            `${CONFIG.shopware.apiUrl}/_action/media/${mediaId}/upload?extension=${extension}&fileName=${encodeURIComponent(fileName)}`,
            {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: {
                    url: imageUrl
                }
            }
        );

        if (uploadRes.status !== 200 && uploadRes.status !== 204) {
            console.log(`      Debug upload response: ${JSON.stringify(uploadRes.data).substring(0, 200)}`);
            throw new Error(`Failed to upload: ${uploadRes.status}`);
        }

        return mediaId;
    } catch (e) {
        console.log(`      ✗ Upload failed: ${e.message}`);
        return null;
    }
}

/**
 * Link media to product
 */
async function linkMediaToProduct(productId, mediaId, position) {
    const token = await getToken();

    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/product-media`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: {
            productId: productId,
            mediaId: mediaId,
            position: position
        }
    });

    return res.status === 200 || res.status === 204;
}

/**
 * Get existing media for product
 */
async function getProductMedia(productId) {
    const token = await getToken();

    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product-media`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: {
            limit: 100,
            filter: [
                { type: 'equals', field: 'productId', value: productId }
            ]
        }
    });

    if (res.data.data) {
        return res.data.data.length;
    }
    return 0;
}

// ============================================================
// MAIN SYNC
// ============================================================

async function syncProductImages(products, dryRun = false) {
    console.log('\n' + '═'.repeat(60));
    console.log('  SYNC PRODUCT IMAGES TO SHOPWARE');
    console.log('═'.repeat(60));
    console.log(`  Products to process: ${products.length}`);
    console.log(`  Mode: ${dryRun ? 'DRY RUN' : 'LIVE'}`);
    console.log('═'.repeat(60) + '\n');

    let processed = 0;
    let synced = 0;
    let skipped = 0;
    let notFound = 0;
    let errors = 0;

    for (const product of products) {
        processed++;
        const productNumber = product.number;
        const productName = product.name_en || product.name_de || 'Unknown';

        console.log(`[${processed}/${products.length}] ${productName}`);
        console.log(`    Number: ${productNumber}`);

        // Collect image URLs (image_1 through image_5)
        const imageUrls = [];
        for (let i = 1; i <= 5; i++) {
            const url = product[`image_${i}`];
            if (url && typeof url === 'string' && url.startsWith('http')) {
                imageUrls.push({ position: i, url: url });
            }
        }

        if (imageUrls.length === 0) {
            console.log('    → No images to sync');
            skipped++;
            continue;
        }

        console.log(`    → ${imageUrls.length} images found`);

        if (dryRun) {
            imageUrls.forEach(img => {
                console.log(`      [${img.position}] ${img.url.substring(0, 60)}...`);
            });
            synced++;
            continue;
        }

        // Find product in Shopware
        const shopwareProduct = await findProductByNumber(productNumber);

        if (!shopwareProduct) {
            console.log('    ✗ Product not found in Shopware');
            notFound++;
            continue;
        }

        const productId = shopwareProduct.id;
        console.log(`    → Found in Shopware: ${productId.substring(0, 8)}...`);

        // Check existing media count
        const existingMedia = await getProductMedia(productId);
        console.log(`    → Existing media: ${existingMedia}`);

        // Upload each image
        let uploadedCount = 0;
        for (const img of imageUrls) {
            console.log(`      Uploading image ${img.position}...`);

            const mediaId = await uploadMedia(img.url, productId, productNumber, img.position + existingMedia);

            if (mediaId) {
                const linked = await linkMediaToProduct(productId, mediaId, img.position + existingMedia);
                if (linked) {
                    console.log(`      ✓ Image ${img.position} uploaded and linked`);
                    uploadedCount++;
                } else {
                    console.log(`      ⚠ Image ${img.position} uploaded but linking failed`);
                }
            }

            await delay(CONFIG.requestDelay);
        }

        if (uploadedCount > 0) {
            console.log(`    ✓ ${uploadedCount} images synced`);
            synced++;
        } else {
            errors++;
        }

        await delay(CONFIG.requestDelay);
    }

    // Summary
    console.log('\n' + '═'.repeat(60));
    console.log('  SYNC COMPLETE');
    console.log('═'.repeat(60));
    console.log(`  Processed:  ${processed}`);
    console.log(`  Synced:     ${synced}`);
    console.log(`  Skipped:    ${skipped} (no images)`);
    console.log(`  Not found:  ${notFound}`);
    console.log(`  Errors:     ${errors}`);
    console.log('═'.repeat(60));
}

// ============================================================
// MAIN
// ============================================================

async function main() {
    const args = process.argv.slice(2);
    const dryRun = args.includes('--dry-run');
    const productIndex = args.indexOf('--product');
    const specificProduct = productIndex !== -1 ? args[productIndex + 1] : null;

    console.log('\n╔' + '═'.repeat(58) + '╗');
    console.log('║  SHOPWARE PRODUCT IMAGE SYNC                            ║');
    console.log('╚' + '═'.repeat(58) + '╝');

    // Load Excel using xlsx library
    let XLSX;
    try {
        XLSX = require('xlsx');
    } catch (e) {
        console.error('\n  ✗ xlsx library not found. Installing...');
        const { execSync } = require('child_process');
        execSync('npm install xlsx', { stdio: 'inherit' });
        XLSX = require('xlsx');
    }

    console.log(`\n  Loading: ${CONFIG.excelFile}`);
    const workbook = XLSX.readFile(CONFIG.excelFile);
    const sheetName = workbook.SheetNames[0];
    const sheet = workbook.Sheets[sheetName];
    let products = XLSX.utils.sheet_to_json(sheet);

    console.log(`  Total products: ${products.length}`);

    // Filter out Raven Swiss
    products = products.filter(p => {
        const name = p.name_en || p.name_de || '';
        return !name.toLowerCase().includes(CONFIG.excludePattern.toLowerCase());
    });
    console.log(`  After excluding '${CONFIG.excludePattern}': ${products.length}`);

    // Filter to specific product if requested
    if (specificProduct) {
        products = products.filter(p => p.number === specificProduct);
        if (products.length === 0) {
            console.error(`\n  ✗ Product not found: ${specificProduct}`);
            process.exit(1);
        }
        console.log(`  Filtering to: ${specificProduct}`);
    }

    // Run sync
    await syncProductImages(products, dryRun);
}

main().catch(err => {
    console.error('\n  ✗ Fatal error:', err.message);
    process.exit(1);
});
