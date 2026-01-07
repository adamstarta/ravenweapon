/**
 * Upload Images for Missing Products
 *
 * Uploads images for products that were created but failed image upload.
 *
 * Usage: node upload-missing-images.js
 */

const https = require('https');
const http = require('http');
const { URL } = require('url');
const crypto = require('crypto');

const CONFIG = {
    excelFile: String.raw`c:\Users\alama\Downloads\products (2).xlsx`,
    shopware: {
        apiUrl: 'https://ravenweapon.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
    },
    productsToFix: [
        'ZRT-ZF-THR-000-XX-00108',
        'FCH-AMO-XXX-9mm-XX-00084',
        'FCH-AMO-XXX-300-XX-00083',
        'FCH-AMO-XXX-22LR-XX-00085',
        'FCH-AMO-XXX-223-XX-00082'
    ],
    requestDelay: 500
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
        if (!res.data.access_token) throw new Error('Failed to get token');
        accessToken = res.data.access_token;
        tokenExpiry = now + ((res.data.expires_in || 600) - 60) * 1000;
    }
    return accessToken;
}

async function shopwareApi(endpoint, options = {}, retry = true) {
    const token = await getToken();
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}${endpoint}`, {
        ...options,
        headers: {
            'Authorization': `Bearer ${token}`,
            ...options.headers
        }
    });
    if (res.status === 401 && retry) {
        accessToken = null;
        tokenExpiry = 0;
        return shopwareApi(endpoint, options, false);
    }
    return res;
}

async function getProduct(productNumber) {
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
    const fileName = `${productNumber}-img-${index}-${Date.now()}`;

    // Create media record
    const createRes = await shopwareApi('/media', {
        method: 'POST',
        body: { id: mediaId }
    });

    if (createRes.status !== 204 && createRes.status !== 200) {
        console.log(`      Failed to create media: ${createRes.status}`);
        return null;
    }

    await delay(200);

    // Upload from URL
    const uploadRes = await shopwareApi(`/_action/media/${mediaId}/upload?extension=${extension}&fileName=${encodeURIComponent(fileName)}`, {
        method: 'POST',
        body: { url: imageUrl }
    });

    if (uploadRes.status !== 204 && uploadRes.status !== 200) {
        console.log(`      Upload failed: ${uploadRes.status} - ${JSON.stringify(uploadRes.data).substring(0, 200)}`);
        return null;
    }

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
    if (res.status !== 204 && res.status !== 200) {
        console.log(`      Link failed: ${res.status}`);
        return null;
    }
    return productMediaId;
}

async function setProductCover(productId, productMediaId) {
    const res = await shopwareApi(`/product/${productId}`, {
        method: 'PATCH',
        body: { coverId: productMediaId }
    });
    return res.status === 204 || res.status === 200;
}

async function main() {
    console.log('\n' + '='.repeat(70));
    console.log('  UPLOAD IMAGES FOR MISSING PRODUCTS');
    console.log('='.repeat(70));

    let XLSX;
    try {
        XLSX = require('xlsx');
    } catch (e) {
        console.log('Installing xlsx...');
        require('child_process').execSync('npm install xlsx', { stdio: 'inherit' });
        XLSX = require('xlsx');
    }

    console.log(`\n  Loading Excel: ${CONFIG.excelFile}`);
    const workbook = XLSX.readFile(CONFIG.excelFile);
    const sheet = workbook.Sheets[workbook.SheetNames[0]];
    const products = XLSX.utils.sheet_to_json(sheet);

    const excelProducts = {};
    products.forEach(p => {
        if (CONFIG.productsToFix.includes(p.number)) {
            excelProducts[p.number] = p;
        }
    });

    console.log(`  Products to process: ${Object.keys(excelProducts).length}`);

    await getToken();
    console.log('  Token acquired\n');

    let success = 0;
    let failed = 0;

    for (const productNumber of CONFIG.productsToFix) {
        console.log(`\n  Processing: ${productNumber}`);

        const excelProduct = excelProducts[productNumber];
        if (!excelProduct) {
            console.log('    Not found in Excel');
            failed++;
            continue;
        }

        const swProduct = await getProduct(productNumber);
        if (!swProduct) {
            console.log('    Not found in Shopware');
            failed++;
            continue;
        }

        console.log(`    Shopware ID: ${swProduct.id}`);
        console.log(`    Current media count: ${swProduct.media?.length || 0}`);

        // Collect images from Excel
        const images = [];
        for (let i = 1; i <= 10; i++) {
            const imgUrl = excelProduct[`image_${i}`];
            if (imgUrl && typeof imgUrl === 'string' && imgUrl.startsWith('http')) {
                images.push(imgUrl);
            }
        }

        console.log(`    Excel images: ${images.length}`);

        if (images.length === 0) {
            console.log('    No images to upload');
            success++;
            continue;
        }

        // Print image URLs for debugging
        images.forEach((url, i) => {
            console.log(`      Image ${i + 1}: ${url.substring(0, 80)}...`);
        });

        // Upload each image
        let firstProductMediaId = null;
        let uploaded = 0;

        for (let i = 0; i < images.length; i++) {
            const imgUrl = images[i];
            console.log(`    Uploading image ${i + 1}/${images.length}...`);

            const mediaId = await uploadMediaFromUrl(imgUrl, productNumber, i + 1);
            if (mediaId) {
                await delay(200);
                const productMediaId = await addProductMedia(swProduct.id, mediaId, i);
                if (productMediaId) {
                    uploaded++;
                    if (i === 0) firstProductMediaId = productMediaId;
                }
            }

            await delay(CONFIG.requestDelay);
        }

        console.log(`    Uploaded: ${uploaded}/${images.length}`);

        // Set cover
        if (firstProductMediaId) {
            const coverSet = await setProductCover(swProduct.id, firstProductMediaId);
            console.log(`    Cover: ${coverSet ? 'SET' : 'FAILED'}`);
        }

        if (uploaded > 0) success++;
        else failed++;
    }

    console.log('\n' + '='.repeat(70));
    console.log('  SUMMARY');
    console.log('='.repeat(70));
    console.log(`  Success: ${success}`);
    console.log(`  Failed: ${failed}`);
    console.log('='.repeat(70) + '\n');
}

main().catch(err => {
    console.error('\n  Error:', err.message);
    process.exit(1);
});
