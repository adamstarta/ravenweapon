/**
 * Upload images for the 2 missing Snigel products to Shopware
 */

const fs = require('fs');
const path = require('path');
const https = require('https');
const http = require('http');

// ============================================================
// CONFIGURATION
// ============================================================

const CONFIG = {
    shopware: {
        baseUrl: 'https://ortak.ch',
        apiUrl: 'https://ortak.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
    },
    tempDir: path.join(__dirname, 'snigel-import-data', 'temp-images')
};

// Create temp directory
if (!fs.existsSync(CONFIG.tempDir)) {
    fs.mkdirSync(CONFIG.tempDir, { recursive: true });
}

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

// Download image from URL
function downloadImage(url, filepath) {
    return new Promise((resolve, reject) => {
        const file = fs.createWriteStream(filepath);
        const protocol = url.startsWith('https') ? https : http;

        protocol.get(url, (response) => {
            // Handle redirects
            if (response.statusCode === 301 || response.statusCode === 302) {
                const redirectUrl = response.headers.location;
                downloadImage(redirectUrl, filepath).then(resolve).catch(reject);
                return;
            }

            if (response.statusCode !== 200) {
                reject(new Error(`Failed to download: ${response.statusCode}`));
                return;
            }

            response.pipe(file);
            file.on('finish', () => {
                file.close();
                resolve(filepath);
            });
        }).on('error', (err) => {
            fs.unlink(filepath, () => {});
            reject(err);
        });
    });
}

// Generate UUID
function generateUUID() {
    return 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'.replace(/x/g, () => {
        return Math.floor(Math.random() * 16).toString(16);
    });
}

// ============================================================
// MAIN
// ============================================================

async function main() {
    console.log('\n╔══════════════════════════════════════════════════════════╗');
    console.log('║  UPLOAD IMAGES FOR MISSING SNIGEL PRODUCTS               ║');
    console.log('╚══════════════════════════════════════════════════════════╝\n');

    // Load scraped data
    const dataPath = path.join(__dirname, 'snigel-import-data', 'missing-products-scraped.json');
    if (!fs.existsSync(dataPath)) {
        console.error('  ✗ Scraped data not found. Run snigel-import-missing.js first.');
        return;
    }

    const products = JSON.parse(fs.readFileSync(dataPath, 'utf8'));
    console.log(`  Loaded ${products.length} products with images\n`);

    // Get Shopware API token
    console.log('  Getting Shopware API token...');
    const tokenRes = await httpRequest(`${CONFIG.shopware.apiUrl}/oauth/token`, {
        method: 'POST',
        body: {
            grant_type: 'client_credentials',
            client_id: CONFIG.shopware.clientId,
            client_secret: CONFIG.shopware.clientSecret
        }
    });

    if (!tokenRes.data.access_token) {
        console.error('  ✗ Failed to get API token');
        return;
    }
    const token = tokenRes.data.access_token;
    console.log('  ✓ Token obtained\n');

    // Get default media folder for products
    console.log('  Finding product media folder...');
    const folderSearch = await httpRequest(`${CONFIG.shopware.apiUrl}/search/media-default-folder`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: {
            limit: 10,
            filter: [{ type: 'equals', field: 'entity', value: 'product' }],
            associations: { folder: {} }
        }
    });

    let mediaFolderId = null;
    if (folderSearch.data.data && folderSearch.data.data.length > 0) {
        mediaFolderId = folderSearch.data.data[0].folder?.id;
        console.log(`  ✓ Found media folder: ${folderSearch.data.data[0].folder?.name || 'Product Media'}`);
    }

    // Process each product
    for (const product of products) {
        console.log(`\n═══════════════════════════════════════════════════════════`);
        console.log(`  ${product.name}`);
        console.log(`═══════════════════════════════════════════════════════════`);

        if (!product.images || product.images.length === 0) {
            console.log('  ⚠ No images to upload');
            continue;
        }

        // Find product in Shopware
        const productSearch = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: {
                limit: 1,
                filter: [{ type: 'contains', field: 'name', value: product.name }]
            }
        });

        if (!productSearch.data.data || productSearch.data.data.length === 0) {
            console.log('  ✗ Product not found in Shopware');
            continue;
        }

        const shopwareProduct = productSearch.data.data[0];
        const productId = shopwareProduct.id;
        console.log(`  ✓ Found product: ${productId.substring(0, 8)}...`);

        // Upload each image
        const mediaIds = [];
        let position = 0;

        for (const imageUrl of product.images) {
            position++;
            const filename = path.basename(imageUrl).split('?')[0];
            const localPath = path.join(CONFIG.tempDir, filename);

            console.log(`\n  [${position}/${product.images.length}] ${filename}`);

            try {
                // Download image
                console.log('    Downloading...');
                await downloadImage(imageUrl, localPath);

                // Check file size
                const stats = fs.statSync(localPath);
                if (stats.size < 1000) {
                    console.log('    ⚠ File too small, skipping');
                    continue;
                }

                // Create media entry in Shopware
                const mediaId = generateUUID();
                console.log('    Creating media entry...');

                const createMediaRes = await httpRequest(`${CONFIG.shopware.apiUrl}/media`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}` },
                    body: {
                        id: mediaId,
                        mediaFolderId: mediaFolderId
                    }
                });

                if (createMediaRes.status !== 204 && createMediaRes.status !== 200) {
                    console.log(`    ✗ Failed to create media: ${createMediaRes.status}`);
                    continue;
                }

                // Upload the file
                console.log('    Uploading file...');
                const fileBuffer = fs.readFileSync(localPath);
                const fileExtension = path.extname(filename).replace('.', '').toLowerCase();

                // Use _action/media endpoint for upload
                const uploadUrl = `${CONFIG.shopware.apiUrl}/_action/media/${mediaId}/upload?extension=${fileExtension}&fileName=${encodeURIComponent(filename.replace(/\.[^.]+$/, ''))}`;

                const uploadRes = await new Promise((resolve, reject) => {
                    const urlObj = new URL(uploadUrl);
                    const options = {
                        hostname: urlObj.hostname,
                        port: 443,
                        path: urlObj.pathname + urlObj.search,
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': `image/${fileExtension === 'jpg' ? 'jpeg' : fileExtension}`,
                            'Content-Length': fileBuffer.length
                        }
                    };

                    const req = https.request(options, (res) => {
                        let data = '';
                        res.on('data', chunk => data += chunk);
                        res.on('end', () => resolve({ status: res.statusCode, data }));
                    });

                    req.on('error', reject);
                    req.write(fileBuffer);
                    req.end();
                });

                if (uploadRes.status !== 204 && uploadRes.status !== 200) {
                    console.log(`    ✗ Failed to upload: ${uploadRes.status}`);
                    continue;
                }

                console.log('    ✓ Uploaded successfully');
                mediaIds.push({ mediaId, position });

                // Clean up temp file
                fs.unlinkSync(localPath);

                await delay(500);

            } catch (error) {
                console.log(`    ✗ Error: ${error.message}`);
            }
        }

        // Associate media with product
        if (mediaIds.length > 0) {
            console.log(`\n  Associating ${mediaIds.length} images with product...`);

            const productMedia = mediaIds.map((m, idx) => ({
                mediaId: m.mediaId,
                position: idx
            }));

            // Set cover image (first image)
            const updatePayload = {
                media: productMedia,
                coverId: mediaIds[0].mediaId
            };

            const updateRes = await httpRequest(`${CONFIG.shopware.apiUrl}/product/${productId}`, {
                method: 'PATCH',
                headers: { 'Authorization': `Bearer ${token}` },
                body: updatePayload
            });

            if (updateRes.status === 204 || updateRes.status === 200) {
                console.log(`  ✓ Associated ${mediaIds.length} images with product`);
            } else {
                console.log(`  ✗ Failed to associate: ${updateRes.status}`);
                if (updateRes.data.errors) {
                    updateRes.data.errors.forEach(e => console.log(`    ${e.detail || e.title}`));
                }
            }
        }
    }

    console.log('\n═══════════════════════════════════════════════════════════');
    console.log('  COMPLETE!');
    console.log('═══════════════════════════════════════════════════════════\n');
}

main().catch(console.error);
