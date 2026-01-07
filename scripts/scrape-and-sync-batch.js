/**
 * Batch Scrape and Sync - Process products from scraped data file
 */

const https = require('https');
const crypto = require('crypto');
const fs = require('fs');

const CONFIG = {
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
            associations: { media: {} }
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

async function syncProduct(productNumber, makarisImages) {
    const swProduct = await getShopwareProduct(productNumber);
    if (!swProduct) {
        return { status: 'not_found', synced: 0 };
    }

    const swImageCount = swProduct.media?.length || 0;
    const makarisCount = makarisImages.length;

    if (makarisCount <= swImageCount) {
        return { status: 'ok', synced: 0, makaris: makarisCount, shopware: swImageCount };
    }

    const missingCount = makarisCount - swImageCount;
    let uploaded = 0;

    for (let i = swImageCount; i < makarisCount; i++) {
        const imgUrl = makarisImages[i];
        const mediaId = await uploadMediaFromUrl(imgUrl, productNumber, i + 1);
        if (mediaId) {
            const productMediaId = await addProductMedia(swProduct.id, mediaId, i);
            if (productMediaId) uploaded++;
        }
        await delay(CONFIG.requestDelay);
    }

    return { status: 'synced', synced: uploaded, missing: missingCount, makaris: makarisCount, shopware: swImageCount };
}

async function main() {
    const dataFile = process.argv[2] || 'makaris-scraped-images.json';

    if (!fs.existsSync(dataFile)) {
        console.log(`File not found: ${dataFile}`);
        return;
    }

    const data = JSON.parse(fs.readFileSync(dataFile, 'utf8'));
    console.log(`\nLoaded ${data.length} products from ${dataFile}\n`);

    await getToken();

    let totalSynced = 0;
    let productsWithMissing = 0;

    for (let i = 0; i < data.length; i++) {
        const product = data[i];
        console.log(`[${i + 1}/${data.length}] ${product.number}`);

        if (!product.makarisImages || product.makarisImages.length === 0) {
            console.log('  No images\n');
            continue;
        }

        const result = await syncProduct(product.number, product.makarisImages);

        if (result.status === 'not_found') {
            console.log('  Not in Shopware\n');
        } else if (result.status === 'ok') {
            console.log(`  OK (Makaris: ${result.makaris}, Shopware: ${result.shopware})\n`);
        } else {
            console.log(`  Synced ${result.synced}/${result.missing} missing (Makaris: ${result.makaris}, Shopware: ${result.shopware})\n`);
            totalSynced += result.synced;
            productsWithMissing++;
        }
    }

    console.log('='.repeat(50));
    console.log(`Products with missing images: ${productsWithMissing}`);
    console.log(`Total images synced: ${totalSynced}`);
}

main().catch(err => console.error(err.message));
