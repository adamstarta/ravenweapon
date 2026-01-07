/**
 * Scrape Makaris for ALL Product Images
 *
 * 1. Logs into Makaris admin panel
 * 2. Scrapes all images from each product's image gallery
 * 3. Compares with Shopware image count
 * 4. Reports differences
 *
 * Usage:
 *   node scrape-makaris-full.js --dry-run     # Compare only
 *   node scrape-makaris-full.js --sync        # Sync missing images
 */

const https = require('https');
const http = require('http');
const { URL } = require('url');
const crypto = require('crypto');

const CONFIG = {
    excelFile: String.raw`c:\Users\alama\Downloads\products (2).xlsx`,
    makaris: {
        baseUrl: 'https://app.makaris.ch',
        loginUrl: 'https://app.makaris.ch/login',
        credentials: {
            email: 'nikola@ravenweapon.ch',
            password: '100%Ravenweapon...'
        }
    },
    shopware: {
        apiUrl: 'https://ravenweapon.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
    },
    requestDelay: 300,
    // Exclude Raven Swiss products (RAV-*)
    excludePrefix: ['RAV-']
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

    if (createRes.status !== 204 && createRes.status !== 200) {
        return null;
    }

    await delay(200);

    const uploadRes = await shopwareApi(`/_action/media/${mediaId}/upload?extension=${extension}&fileName=${encodeURIComponent(fileName)}`, {
        method: 'POST',
        body: { url: imageUrl }
    });

    if (uploadRes.status !== 204 && uploadRes.status !== 200) {
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

// ============================================================
// PLAYWRIGHT SCRAPER
// ============================================================

async function scrapeMakarisImages(page, makarisId) {
    const imagesUrl = `${CONFIG.makaris.baseUrl}/u/products/${makarisId}/detail/images`;

    try {
        await page.goto(imagesUrl, { waitUntil: 'networkidle', timeout: 30000 });
        await delay(1000);

        // Get all image URLs from the page
        const images = await page.evaluate(() => {
            const imageUrls = [];

            // Look for images in various containers
            const imgElements = document.querySelectorAll('img[src*="makaris-prod-public"]');
            imgElements.forEach(img => {
                if (img.src && !imageUrls.includes(img.src)) {
                    imageUrls.push(img.src);
                }
            });

            // Also check for background images
            const divs = document.querySelectorAll('[style*="background-image"]');
            divs.forEach(div => {
                const style = div.getAttribute('style');
                const match = style.match(/url\(['"]?(https:\/\/makaris-prod-public[^'"]+)['"]?\)/);
                if (match && !imageUrls.includes(match[1])) {
                    imageUrls.push(match[1]);
                }
            });

            return imageUrls;
        });

        return images;
    } catch (err) {
        console.log(`    Error scraping images: ${err.message}`);
        return [];
    }
}

async function loginToMakaris(page) {
    console.log('  Logging into Makaris...');

    await page.goto(CONFIG.makaris.loginUrl, { waitUntil: 'networkidle' });
    await delay(1000);

    // Fill login form
    await page.fill('input[name="email"], input[type="email"]', CONFIG.makaris.credentials.email);
    await page.fill('input[name="password"], input[type="password"]', CONFIG.makaris.credentials.password);

    // Click login button
    await page.click('button[type="submit"]');

    // Wait for navigation
    await page.waitForLoadState('networkidle');
    await delay(2000);

    // Check if logged in by looking for user menu or dashboard
    const isLoggedIn = await page.evaluate(() => {
        return window.location.pathname.includes('/u/') ||
               document.querySelector('[data-user]') !== null ||
               document.body.innerText.includes('Dashboard');
    });

    return isLoggedIn;
}

// ============================================================
// MAIN
// ============================================================

async function main() {
    const args = process.argv.slice(2);
    const syncMode = args.includes('--sync');
    const dryRun = !syncMode;

    console.log('\\n' + '='.repeat(70));
    console.log('  MAKARIS IMAGE SCRAPER');
    console.log('='.repeat(70));
    console.log(`  Mode: ${dryRun ? 'DRY RUN (compare only)' : 'SYNC MODE'}`);

    // Load Playwright
    let playwright;
    try {
        playwright = require('playwright');
    } catch (e) {
        console.log('  Installing playwright...');
        require('child_process').execSync('npm install playwright', { stdio: 'inherit' });
        playwright = require('playwright');
    }

    // Load Excel
    let XLSX;
    try {
        XLSX = require('xlsx');
    } catch (e) {
        console.log('  Installing xlsx...');
        require('child_process').execSync('npm install xlsx', { stdio: 'inherit' });
        XLSX = require('xlsx');
    }

    console.log(`\\n  Loading Excel: ${CONFIG.excelFile}`);
    const workbook = XLSX.readFile(CONFIG.excelFile);
    const sheet = workbook.Sheets[workbook.SheetNames[0]];
    const products = XLSX.utils.sheet_to_json(sheet);
    console.log(`  Total products in Excel: ${products.length}`);

    // Build product map with Makaris IDs
    const productMap = [];
    for (const product of products) {
        // Skip excluded prefixes
        if (CONFIG.excludePrefix.some(p => product.number?.startsWith(p))) {
            continue;
        }

        const makarisId = extractMakarisId(product.public_url);
        if (makarisId) {
            productMap.push({
                number: product.number,
                name: product.name_en || product.name_de || 'Unknown',
                makarisId: makarisId
            });
        }
    }

    console.log(`  Products with Makaris ID (excluding RAV-): ${productMap.length}`);

    // Get Shopware token
    console.log('\\n  Getting Shopware API token...');
    await getToken();

    // Launch browser
    console.log('  Launching browser...');
    const browser = await playwright.chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();

    // Login to Makaris
    const loggedIn = await loginToMakaris(page);
    if (!loggedIn) {
        console.log('  ERROR: Failed to login to Makaris');
        await browser.close();
        return;
    }
    console.log('  Logged in successfully!');

    // Results tracking
    const results = {
        total: 0,
        scraped: 0,
        mismatches: [],
        synced: 0
    };

    console.log('\\n' + '-'.repeat(70));
    console.log('  SCRAPING PRODUCTS');
    console.log('-'.repeat(70));

    for (const product of productMap) {
        results.total++;
        console.log(`\\n  [${results.total}/${productMap.length}] ${product.number}`);
        console.log(`    Makaris ID: ${product.makarisId}`);

        // Scrape Makaris images
        const makarisImages = await scrapeMakarisImages(page, product.makarisId);
        console.log(`    Makaris images: ${makarisImages.length}`);

        if (makarisImages.length === 0) {
            console.log('    No images found on Makaris');
            continue;
        }

        results.scraped++;

        // Get Shopware product
        const swProduct = await getShopwareProduct(product.number);
        if (!swProduct) {
            console.log('    Not in Shopware');
            continue;
        }

        const swImageCount = swProduct.media?.length || 0;
        console.log(`    Shopware images: ${swImageCount}`);

        // Compare
        const diff = makarisImages.length - swImageCount;
        if (diff !== 0) {
            results.mismatches.push({
                number: product.number,
                name: product.name,
                makarisId: product.makarisId,
                makarisImages: makarisImages.length,
                makarisUrls: makarisImages,
                shopwareImages: swImageCount,
                diff: diff
            });

            console.log(`    MISMATCH: ${diff > 0 ? '+' : ''}${diff} images`);

            // Sync if in sync mode and Makaris has more images
            if (!dryRun && diff > 0) {
                console.log('    Syncing missing images...');

                // Upload missing images (those beyond Shopware count)
                const startIndex = swImageCount;
                let uploaded = 0;

                for (let i = startIndex; i < makarisImages.length; i++) {
                    const imgUrl = makarisImages[i];
                    const mediaId = await uploadMediaFromUrl(imgUrl, product.number, i + 1);
                    if (mediaId) {
                        const productMediaId = await addProductMedia(swProduct.id, mediaId, i);
                        if (productMediaId) {
                            uploaded++;
                        }
                    }
                    await delay(CONFIG.requestDelay);
                }

                console.log(`    Uploaded: ${uploaded} images`);
                if (uploaded > 0) results.synced++;
            }
        } else {
            console.log('    OK (images match)');
        }

        await delay(CONFIG.requestDelay);
    }

    await browser.close();

    // Print results
    console.log('\\n' + '='.repeat(70));
    console.log('  RESULTS');
    console.log('='.repeat(70));

    console.log(`\\n  Total products: ${results.total}`);
    console.log(`  Products scraped: ${results.scraped}`);
    console.log(`  Mismatches found: ${results.mismatches.length}`);
    if (!dryRun) {
        console.log(`  Products synced: ${results.synced}`);
    }

    if (results.mismatches.length > 0) {
        console.log('\\n' + '-'.repeat(70));
        console.log('  IMAGE MISMATCHES');
        console.log('-'.repeat(70));
        console.log('  Product Number                   Makaris  Shopware  Diff');
        console.log('  ' + '-'.repeat(60));

        results.mismatches.sort((a, b) => b.diff - a.diff);
        results.mismatches.forEach(p => {
            const num = p.number.padEnd(30);
            const makaris = String(p.makarisImages).padStart(7);
            const sw = String(p.shopwareImages).padStart(9);
            const diff = (p.diff > 0 ? '+' : '') + String(p.diff).padStart(5);
            console.log(`  ${num} ${makaris} ${sw} ${diff}`);
        });

        const totalMissing = results.mismatches
            .filter(p => p.diff > 0)
            .reduce((sum, p) => sum + p.diff, 0);
        console.log(`\\n  Total missing images in Shopware: ${totalMissing}`);
    }

    console.log('\\n' + '='.repeat(70) + '\\n');
}

main().catch(err => {
    console.error('\\n  Fatal error:', err.message);
    process.exit(1);
});
