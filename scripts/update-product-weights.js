/**
 * Update Product Weights by Category
 *
 * Assigns weights to products based on their Shopware category.
 * Weight classes aligned with shipping tiers:
 * - Heavy (15 kg): Firearms, Caliber Kits → CHF 22.50 tier
 * - Medium (5 kg): Optics, Gear, Clothing → CHF 13.50 tier
 * - Light (1.5 kg): Small accessories → CHF 10.50 tier
 * - Digital (0 kg): Training courses → No shipping
 *
 * Usage:
 *   node update-product-weights.js          # Dry run (preview)
 *   node update-product-weights.js --apply  # Apply changes
 */

const https = require('https');

// Use --production flag to target production, default is staging
const isProduction = process.argv.includes('--production');

const CONFIG = {
    shopware: {
        apiUrl: isProduction
            ? 'https://ravenweapon.ch/api'
            : 'https://developing.ravenweapon.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
    }
};

// Weight classes in kg
const WEIGHT_CLASSES = {
    heavy: 15,
    medium: 5,
    light: 1.5,
    digital: 0
};

// Product number prefix → weight class mapping
// Order matters! More specific patterns should come first
const PRODUCT_NUMBER_PATTERNS = [
    // DIGITAL - Training courses (no shipping)
    { pattern: /^Basic-Kurs/i, weightClass: 'digital' },
    { pattern: /^Instruktor/i, weightClass: 'digital' },

    // HEAVY - Firearms & Kits (15 kg)
    { pattern: /^RAVEN-/i, weightClass: 'heavy' },
    { pattern: /^KIT-/i, weightClass: 'heavy' },
    { pattern: /^DEX-PIS-/i, weightClass: 'heavy' },  // Pistols (RAPAX, CARACAL)

    // MEDIUM - Stocks, Bipods, Optics, Gear (5 kg)
    { pattern: /^MGP-ZBH-STK-/i, weightClass: 'medium' },  // Magpul stocks
    { pattern: /^MGP-ZBH-BPD-/i, weightClass: 'medium' },  // Magpul bipods
    { pattern: /^AIM-/i, weightClass: 'medium' },          // AIMPACT scope mounts
    { pattern: /^ZRT-/i, weightClass: 'medium' },          // ZeroTech scopes
    { pattern: /^SN-/i, weightClass: 'medium' },           // Snigel gear/clothing
    { pattern: /^FCH-AMO-/i, weightClass: 'medium' },      // Ammunition (Fiocchi)
    { pattern: /^AMMO-/i, weightClass: 'medium' },         // Ammunition

    // LIGHT - Small accessories (1.5 kg)
    { pattern: /^MGP-ZBH-GRP-/i, weightClass: 'light' },   // Magpul grips
    { pattern: /^MGP-ZBH-ACS-/i, weightClass: 'light' },   // Magpul accessories (hand stops)
    { pattern: /^MGP-MG-/i, weightClass: 'light' },        // Magpul magazines
    { pattern: /^ACH-/i, weightClass: 'light' },           // Acheron muzzle devices
    { pattern: /^30-/i, weightClass: 'light' },            // Patches (30-xxxxx-xx-xx)
];

// Default weight class for unmatched products
const DEFAULT_WEIGHT_CLASS = 'medium';

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

async function getAllProducts() {
    const token = await getToken();
    const allProducts = [];
    let page = 1;
    const limit = 100;

    while (true) {
        const res = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: {
                limit: limit,
                page: page,
                includes: {
                    product: ['id', 'productNumber', 'name', 'weight', 'active', 'parentId'],
                    category: ['id', 'name']
                },
                associations: {
                    categories: {}
                }
            }
        });

        if (res.status === 401) {
            accessToken = null;
            return getAllProducts();
        }

        if (!res.data.data || res.data.data.length === 0) break;

        allProducts.push(...res.data.data);
        process.stdout.write(`\r  Fetched ${allProducts.length} products...`);

        if (res.data.data.length < limit) break;
        page++;
    }
    console.log('');

    return allProducts;
}

function getWeightClassForProduct(product) {
    const productNumber = product.productNumber || '';

    // Check product number against patterns
    for (const { pattern, weightClass } of PRODUCT_NUMBER_PATTERNS) {
        if (pattern.test(productNumber)) {
            return {
                weightClass: weightClass,
                matchedPattern: pattern.toString()
            };
        }
    }

    // No match - use default
    return {
        weightClass: DEFAULT_WEIGHT_CLASS,
        matchedPattern: null
    };
}

async function updateProductWeight(productId, weight) {
    const token = await getToken();
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/product/${productId}`, {
        method: 'PATCH',
        headers: { 'Authorization': `Bearer ${token}` },
        body: { weight: weight }
    });

    if (res.status === 401) {
        accessToken = null;
        return updateProductWeight(productId, weight);
    }

    return res.status >= 200 && res.status < 300;
}

async function main() {
    const applyChanges = process.argv.includes('--apply');

    const envName = isProduction ? 'PRODUCTION' : 'STAGING';
    console.log('\n' + '='.repeat(70));
    console.log('  PRODUCT WEIGHT UPDATE - ' + envName + (applyChanges ? '' : ' - DRY RUN'));
    console.log('='.repeat(70));
    console.log('  Target: ' + CONFIG.shopware.apiUrl);

    if (!applyChanges) {
        console.log('  Preview mode. Run with --apply to make changes.\n');
    } else {
        console.log('');
    }

    console.log('  Authenticating...');
    await getToken();

    console.log('  Fetching products with categories...');
    const products = await getAllProducts();

    // Filter to main products only (no variants)
    const mainProducts = products.filter(p => !p.parentId);

    console.log(`\n  Total products: ${products.length}`);
    console.log(`  Main products: ${mainProducts.length}`);

    // Categorize products
    const results = {
        heavy: [],
        medium: [],
        light: [],
        digital: [],
        defaulted: [],
        skippedHasWeight: [],
        updated: 0,
        failed: 0
    };

    for (const product of mainProducts) {
        const name = product.name || 'Unknown';
        const productNumber = product.productNumber || 'N/A';
        const currentWeight = product.weight;

        // Skip products that already have weight
        if (currentWeight && currentWeight > 0) {
            results.skippedHasWeight.push({
                number: productNumber,
                name: name,
                weight: currentWeight
            });
            continue;
        }

        // Determine weight class
        const { weightClass, matchedPattern } = getWeightClassForProduct(product);
        const newWeight = WEIGHT_CLASSES[weightClass];

        const productInfo = {
            id: product.id,
            number: productNumber,
            name: name,
            weight: newWeight,
            pattern: matchedPattern
        };

        // Add to appropriate list
        if (weightClass === 'digital') {
            results.digital.push(productInfo);
            continue;
        }

        results[weightClass].push(productInfo);

        if (!matchedPattern) {
            results.defaulted.push(productInfo);
        }

        // Apply update if flag is set
        if (applyChanges) {
            const success = await updateProductWeight(product.id, newWeight);
            if (success) {
                results.updated++;
            } else {
                results.failed++;
                console.log(`  ERROR: Failed to update ${productNumber}`);
            }
        }
    }

    // Print results
    console.log('\n' + '='.repeat(70));
    console.log('  HEAVY (15 kg) - ' + results.heavy.length + ' products');
    console.log('='.repeat(70));
    for (const p of results.heavy.slice(0, 20)) {
        const cat = p.pattern ? ` [${p.pattern}]` : '';
        console.log(`  ${p.number.substring(0, 25).padEnd(25)} ${p.name.substring(0, 40)}${cat}`);
    }
    if (results.heavy.length > 20) {
        console.log(`  ... and ${results.heavy.length - 20} more`);
    }

    console.log('\n' + '='.repeat(70));
    console.log('  MEDIUM (5 kg) - ' + results.medium.length + ' products');
    console.log('='.repeat(70));
    for (const p of results.medium.slice(0, 20)) {
        const cat = p.pattern ? ` [${p.pattern}]` : '';
        console.log(`  ${p.number.substring(0, 25).padEnd(25)} ${p.name.substring(0, 40)}${cat}`);
    }
    if (results.medium.length > 20) {
        console.log(`  ... and ${results.medium.length - 20} more`);
    }

    console.log('\n' + '='.repeat(70));
    console.log('  LIGHT (1.5 kg) - ' + results.light.length + ' products');
    console.log('='.repeat(70));
    for (const p of results.light.slice(0, 20)) {
        const cat = p.pattern ? ` [${p.pattern}]` : '';
        console.log(`  ${p.number.substring(0, 25).padEnd(25)} ${p.name.substring(0, 40)}${cat}`);
    }
    if (results.light.length > 20) {
        console.log(`  ... and ${results.light.length - 20} more`);
    }

    if (results.defaulted.length > 0) {
        console.log('\n' + '='.repeat(70));
        console.log('  DEFAULT (5 kg) - ' + results.defaulted.length + ' products (REVIEW THESE)');
        console.log('='.repeat(70));
        for (const p of results.defaulted) {
            console.log(`  ${p.number.substring(0, 25).padEnd(25)} ${p.name.substring(0, 45)}`);
        }
    }

    console.log('\n' + '='.repeat(70));
    console.log('  SKIPPED');
    console.log('='.repeat(70));
    console.log(`  Already has weight:  ${results.skippedHasWeight.length} products`);
    console.log(`  Digital products:    ${results.digital.length} products`);

    // Summary
    const totalToUpdate = results.heavy.length + results.medium.length + results.light.length;

    console.log('\n' + '='.repeat(70));
    console.log('  SUMMARY');
    console.log('='.repeat(70));
    console.log(`  Heavy (15 kg):       ${results.heavy.length} products`);
    console.log(`  Medium (5 kg):       ${results.medium.length} products`);
    console.log(`  Light (1.5 kg):      ${results.light.length} products`);
    console.log(`  ─────────────────────────────────`);
    console.log(`  Total to update:     ${totalToUpdate} products`);

    if (applyChanges) {
        console.log(`\n  Updated:             ${results.updated} products`);
        console.log(`  Failed:              ${results.failed} products`);
    } else {
        console.log(`\n  Run with --apply to make changes`);
    }

    console.log('='.repeat(70) + '\n');
}

main().catch(err => {
    console.error('\nFatal error:', err.message);
    process.exit(1);
});
