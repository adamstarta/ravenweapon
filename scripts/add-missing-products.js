/**
 * Add Missing Products to Shopware
 *
 * Adds:
 * - THRIVE 4-16X50 MILDOT (ZRT-ZF-THR-000-XX-00108)
 * - 4 Ammo products (FCH-AMO-*)
 *
 * Usage: node add-missing-products.js
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
    // Products to add
    productsToAdd: [
        'ZRT-ZF-THR-000-XX-00108',  // THRIVE 4-16X50 MILDOT
        'FCH-AMO-XXX-9mm-XX-00084',  // 9mm ammo
        'FCH-AMO-XXX-300-XX-00083',  // .300 ammo
        'FCH-AMO-XXX-22LR-XX-00085', // .22LR ammo
        'FCH-AMO-XXX-223-XX-00082'   // .223 ammo
    ],
    requestDelay: 300
};

// ============================================================
// HELPERS
// ============================================================

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

async function getSalesChannelId() {
    const res = await shopwareApi('/search/sales-channel', {
        method: 'POST',
        body: { limit: 1 }
    });
    return res.data?.data?.[0]?.id;
}

async function getTaxId() {
    const res = await shopwareApi('/search/tax', {
        method: 'POST',
        body: {
            limit: 1,
            filter: [{ type: 'equals', field: 'taxRate', value: 8.1 }]
        }
    });
    if (res.data?.data?.[0]?.id) return res.data.data[0].id;

    // Fallback to any tax
    const res2 = await shopwareApi('/search/tax', {
        method: 'POST',
        body: { limit: 1 }
    });
    return res2.data?.data?.[0]?.id;
}

async function getCurrencyId() {
    const res = await shopwareApi('/search/currency', {
        method: 'POST',
        body: {
            limit: 1,
            filter: [{ type: 'equals', field: 'isoCode', value: 'CHF' }]
        }
    });
    return res.data?.data?.[0]?.id;
}

async function getDefaultCategoryId() {
    // Get root category or first available
    const res = await shopwareApi('/search/category', {
        method: 'POST',
        body: {
            limit: 1,
            filter: [{ type: 'equals', field: 'level', value: 1 }]
        }
    });
    return res.data?.data?.[0]?.id;
}

async function uploadMediaFromUrl(imageUrl, productNumber, index) {
    // Create media entry
    const mediaId = generateUuid();
    const extension = imageUrl.split('.').pop().split('?')[0].toLowerCase() || 'jpg';
    const fileName = `${productNumber}-image-${index}-${Date.now()}`;

    // Create media record
    const createRes = await shopwareApi('/media', {
        method: 'POST',
        body: {
            id: mediaId
        }
    });

    if (createRes.status !== 204 && createRes.status !== 200) {
        console.log(`    Failed to create media entry: ${createRes.status}`);
        return null;
    }

    // Upload from URL using _action endpoint
    const uploadRes = await shopwareApi(`/_action/media/${mediaId}/upload?extension=${extension}&fileName=${encodeURIComponent(fileName)}`, {
        method: 'POST',
        body: {
            url: imageUrl
        }
    });

    if (uploadRes.status !== 204 && uploadRes.status !== 200) {
        console.log(`    Failed to upload media: ${uploadRes.status} - ${JSON.stringify(uploadRes.data)}`);
        return null;
    }

    return mediaId;
}

async function createProduct(productData, salesChannelId, taxId, currencyId, categoryId) {
    const productId = generateUuid();

    // Build product payload
    const payload = {
        id: productId,
        productNumber: productData.number,
        name: productData.name_en || productData.name_de || 'Unknown Product',
        stock: 100,
        active: true,
        taxId: taxId,
        price: [{
            currencyId: currencyId,
            gross: productData.selling_price || 0,
            net: (productData.selling_price || 0) / 1.081,
            linked: true
        }],
        visibilities: [{
            salesChannelId: salesChannelId,
            visibility: 30
        }],
        categories: [{ id: categoryId }]
    };

    // Add description if available
    if (productData.description_en || productData.description_de) {
        payload.description = productData.description_en || productData.description_de;
    }

    const res = await shopwareApi('/product', {
        method: 'POST',
        body: payload
    });

    if (res.status !== 204 && res.status !== 200) {
        console.log(`    Failed to create product: ${res.status}`);
        console.log(`    Response: ${JSON.stringify(res.data)}`);
        return null;
    }

    return productId;
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
        console.log(`    Failed to link media: ${res.status}`);
        return null;
    }

    return productMediaId;
}

async function setProductCover(productId, productMediaId) {
    const res = await shopwareApi(`/product/${productId}`, {
        method: 'PATCH',
        body: {
            coverId: productMediaId
        }
    });

    return res.status === 204 || res.status === 200;
}

// ============================================================
// MAIN
// ============================================================

async function main() {
    console.log('\n' + '='.repeat(70));
    console.log('  ADD MISSING PRODUCTS TO SHOPWARE');
    console.log('='.repeat(70));

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

    // Get products to add
    const productsToCreate = products.filter(p =>
        CONFIG.productsToAdd.includes(p.number)
    );

    console.log(`\n  Products to add: ${productsToCreate.length}`);
    productsToCreate.forEach(p => {
        console.log(`    - ${p.number}: ${p.name_en || p.name_de}`);
    });

    if (productsToCreate.length === 0) {
        console.log('\n  No products to add. Exiting.');
        return;
    }

    // Get Shopware prerequisites
    console.log('\n  Getting Shopware prerequisites...');
    await getToken();

    const salesChannelId = await getSalesChannelId();
    const taxId = await getTaxId();
    const currencyId = await getCurrencyId();
    const categoryId = await getDefaultCategoryId();

    console.log(`    Sales Channel ID: ${salesChannelId}`);
    console.log(`    Tax ID: ${taxId}`);
    console.log(`    Currency ID: ${currencyId}`);
    console.log(`    Category ID: ${categoryId}`);

    if (!salesChannelId || !taxId || !currencyId) {
        console.log('\n  ERROR: Missing required Shopware configuration');
        return;
    }

    // Process each product
    console.log('\n' + '-'.repeat(70));
    console.log('  CREATING PRODUCTS');
    console.log('-'.repeat(70));

    let created = 0;
    let failed = 0;

    for (const product of productsToCreate) {
        console.log(`\n  [${created + failed + 1}/${productsToCreate.length}] ${product.number}`);
        console.log(`    Name: ${product.name_en || product.name_de}`);
        console.log(`    Price: CHF ${product.selling_price?.toFixed(2) || '0.00'}`);

        // Create product
        const productId = await createProduct(product, salesChannelId, taxId, currencyId, categoryId);

        if (!productId) {
            failed++;
            continue;
        }

        console.log(`    Product created: ${productId}`);

        // Collect images
        const images = [];
        for (let i = 1; i <= 10; i++) {
            const imgUrl = product[`image_${i}`];
            if (imgUrl && typeof imgUrl === 'string' && imgUrl.startsWith('http')) {
                images.push(imgUrl);
            }
        }

        console.log(`    Images to upload: ${images.length}`);

        // Upload images
        let firstProductMediaId = null;
        for (let i = 0; i < images.length; i++) {
            const imgUrl = images[i];
            console.log(`      Uploading image ${i + 1}/${images.length}...`);

            const mediaId = await uploadMediaFromUrl(imgUrl, product.number, i + 1);
            if (mediaId) {
                const productMediaId = await addProductMedia(productId, mediaId, i);
                if (productMediaId && i === 0) {
                    firstProductMediaId = productMediaId;
                }
            }

            await delay(CONFIG.requestDelay);
        }

        // Set cover image
        if (firstProductMediaId) {
            const coverSet = await setProductCover(productId, firstProductMediaId);
            console.log(`    Cover image: ${coverSet ? 'SET' : 'FAILED'}`);
        }

        created++;
        await delay(CONFIG.requestDelay);
    }

    // Summary
    console.log('\n' + '='.repeat(70));
    console.log('  SUMMARY');
    console.log('='.repeat(70));
    console.log(`  Products created: ${created}`);
    console.log(`  Failed: ${failed}`);
    console.log('='.repeat(70) + '\n');
}

main().catch(err => {
    console.error('\n  Fatal error:', err.message);
    process.exit(1);
});
