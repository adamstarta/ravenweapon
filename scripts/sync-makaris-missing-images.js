/**
 * Sync Missing Images from Makaris to Shopware
 *
 * Only uploads images that exist in Makaris but NOT in Shopware
 * Compares by count - if Makaris has 10 and Shopware has 5, uploads images 6-10
 */

const https = require('https');
const crypto = require('crypto');

const CONFIG = {
    excelFile: String.raw`c:\Users\alama\Downloads\products (2).xlsx`,
    shopware: {
        apiUrl: 'https://ravenweapon.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
    },
    requestDelay: 400
};

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function generateUuid() {
    return crypto.randomBytes(16).toString('hex');
}

function httpRequest(url, options = {}) {
    return new Promise((resolve, reject) => {
        const urlObj = new URL(url);
        const req = https.request({
            hostname: urlObj.hostname,
            port: 443,
            path: urlObj.pathname + urlObj.search,
            method: options.method || 'GET',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', ...options.headers }
        }, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try { resolve({ status: res.statusCode, data: JSON.parse(data) }); }
                catch (e) { resolve({ status: res.statusCode, data: data }); }
            });
        });
        req.on('error', reject);
        if (options.body) req.write(JSON.stringify(options.body));
        req.end();
    });
}

let accessToken = null;
let tokenExpiry = 0;

async function getToken() {
    const now = Date.now();
    if (!accessToken || now >= tokenExpiry) {
        const res = await httpRequest(`${CONFIG.shopware.apiUrl}/oauth/token`, {
            method: 'POST',
            body: {
                grant_type: 'client_credentials',
                client_id: CONFIG.shopware.clientId,
                client_secret: CONFIG.shopware.clientSecret
            }
        });
        accessToken = res.data.access_token;
        tokenExpiry = now + ((res.data.expires_in || 600) - 60) * 1000;
    }
    return accessToken;
}

async function shopwareApi(endpoint, options = {}) {
    const token = await getToken();
    return httpRequest(`${CONFIG.shopware.apiUrl}${endpoint}`, {
        ...options,
        headers: { 'Authorization': `Bearer ${token}`, ...options.headers }
    });
}

async function getShopwareProduct(productNumber) {
    const res = await shopwareApi('/search/product', {
        method: 'POST',
        body: {
            limit: 1,
            filter: [{ type: 'equals', field: 'productNumber', value: productNumber }],
            associations: { media: { associations: { media: {} } } }
        }
    });
    return res.data?.data?.[0];
}

async function uploadMediaFromUrl(imageUrl, productNumber, index) {
    const mediaId = generateUuid();
    const extension = imageUrl.split('.').pop().split('?')[0].toLowerCase() || 'jpg';
    const fileName = `${productNumber}-makaris-${index}-${Date.now()}`;

    const createRes = await shopwareApi('/media', {
        method: 'POST',
        body: { id: mediaId }
    });

    if (createRes.status !== 204 && createRes.status !== 200) return null;

    await delay(200);

    const uploadRes = await shopwareApi(`/_action/media/${mediaId}/upload?extension=${extension}&fileName=${encodeURIComponent(fileName)}`, {
        method: 'POST',
        body: { url: imageUrl }
    });

    if (uploadRes.status !== 204 && uploadRes.status !== 200) return null;

    return mediaId;
}

async function addProductMedia(productId, mediaId, position) {
    const productMediaId = generateUuid();
    const res = await shopwareApi('/product-media', {
        method: 'POST',
        body: {
            id: productMediaId,
            productId: productId,
            mediaId: mediaId,
            position: position
        }
    });
    return (res.status === 204 || res.status === 200) ? productMediaId : null;
}

// This function will be called with scraped Makaris data
async function syncMissingImages(productNumber, makarisImageUrls) {
    console.log(`\n  Processing: ${productNumber}`);
    console.log(`    Makaris images: ${makarisImageUrls.length}`);

    const swProduct = await getShopwareProduct(productNumber);
    if (!swProduct) {
        console.log('    NOT FOUND in Shopware - skipping');
        return { synced: 0, skipped: true };
    }

    const swImageCount = swProduct.media?.length || 0;
    console.log(`    Shopware images: ${swImageCount}`);

    if (makarisImageUrls.length <= swImageCount) {
        console.log('    No missing images');
        return { synced: 0, skipped: false };
    }

    const missingCount = makarisImageUrls.length - swImageCount;
    console.log(`    Missing: ${missingCount} images`);

    // Upload only the missing images (those beyond current Shopware count)
    let uploaded = 0;
    for (let i = swImageCount; i < makarisImageUrls.length; i++) {
        const imgUrl = makarisImageUrls[i];
        console.log(`    Uploading image ${i + 1}/${makarisImageUrls.length}...`);

        const mediaId = await uploadMediaFromUrl(imgUrl, productNumber, i + 1);
        if (mediaId) {
            const productMediaId = await addProductMedia(swProduct.id, mediaId, i);
            if (productMediaId) {
                uploaded++;
                console.log(`      OK`);
            }
        }
        await delay(CONFIG.requestDelay);
    }

    console.log(`    Synced: ${uploaded}/${missingCount}`);
    return { synced: uploaded, skipped: false };
}

// Main function - accepts product data from command line or file
async function main() {
    const args = process.argv.slice(2);

    // Check if we have a JSON file with scraped data
    if (args[0] === '--data') {
        const fs = require('fs');
        const dataFile = args[1] || 'makaris-scraped-data.json';

        if (!fs.existsSync(dataFile)) {
            console.log(`Data file not found: ${dataFile}`);
            console.log('Run the scraper first to generate this file.');
            return;
        }

        const data = JSON.parse(fs.readFileSync(dataFile, 'utf8'));
        console.log(`\nLoaded ${data.length} products from ${dataFile}`);

        await getToken();

        let totalSynced = 0;
        let productsWithMissing = 0;

        for (const product of data) {
            if (product.makarisImages && product.makarisImages.length > 0) {
                const result = await syncMissingImages(product.number, product.makarisImages);
                if (result.synced > 0) {
                    totalSynced += result.synced;
                    productsWithMissing++;
                }
            }
        }

        console.log('\n' + '='.repeat(50));
        console.log('  SYNC COMPLETE');
        console.log('='.repeat(50));
        console.log(`  Products with missing images: ${productsWithMissing}`);
        console.log(`  Total images synced: ${totalSynced}`);

    } else if (args[0] === '--product') {
        // Sync a single product with provided image URLs
        const productNumber = args[1];
        const imageUrls = args.slice(2);

        if (!productNumber || imageUrls.length === 0) {
            console.log('Usage: node sync-makaris-missing-images.js --product <productNumber> <imageUrl1> <imageUrl2> ...');
            return;
        }

        await getToken();
        await syncMissingImages(productNumber, imageUrls);

    } else {
        console.log('Usage:');
        console.log('  node sync-makaris-missing-images.js --data <scraped-data.json>');
        console.log('  node sync-makaris-missing-images.js --product <productNumber> <imageUrls...>');
    }
}

module.exports = { syncMissingImages, getToken };

main().catch(err => console.error(err.message));
