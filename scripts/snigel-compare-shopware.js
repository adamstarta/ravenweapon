/**
 * Compare Snigel Products with Shopware Store
 * Checks which products are missing from ortak.ch
 *
 * Usage: node snigel-compare-shopware.js
 */

const fs = require('fs');
const https = require('https');

// Shopware API config
const SHOPWARE_URL = 'https://ortak.ch';
const API_URL = `${SHOPWARE_URL}/store-api`;

async function fetchShopwareProducts() {
    console.log('🛒 Fetching products from Shopware (ortak.ch)...\n');

    return new Promise((resolve, reject) => {
        const postData = JSON.stringify({
            limit: 500,
            includes: {
                product: ['id', 'productNumber', 'name', 'translated']
            },
            filter: [
                {
                    type: 'contains',
                    field: 'productNumber',
                    value: 'SN-'
                }
            ]
        });

        const options = {
            hostname: 'ortak.ch',
            port: 443,
            path: '/store-api/product',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'sw-access-key': 'SWSCVWNQN0FHSXLUM0NNZGNQBW', // Public storefront key
                'Content-Length': Buffer.byteLength(postData)
            }
        };

        const req = https.request(options, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try {
                    const json = JSON.parse(data);
                    const products = json.elements || [];
                    resolve(products.map(p => ({
                        name: p.translated?.name || p.name,
                        sku: p.productNumber
                    })));
                } catch (e) {
                    resolve([]);
                }
            });
        });

        req.on('error', () => resolve([]));
        req.write(postData);
        req.end();
    });
}

async function main() {
    console.log('═══════════════════════════════════════════════════════════');
    console.log('   SNIGEL vs SHOPWARE COMPARISON');
    console.log('═══════════════════════════════════════════════════════════\n');

    // Load Snigel products from audit
    let snigelProducts = [];
    try {
        const auditData = JSON.parse(fs.readFileSync('snigel-products-audit.json', 'utf8'));
        snigelProducts = auditData.products || [];
        console.log(`📦 Snigel Products (from audit): ${snigelProducts.length}`);
    } catch (e) {
        console.log('⚠️  No audit file found. Run "node snigel-product-audit.js" first.');
        return;
    }

    // Fetch Shopware products
    const shopwareProducts = await fetchShopwareProducts();
    console.log(`🛒 Shopware Products (SN- prefix): ${shopwareProducts.length}\n`);

    // Normalize names for comparison
    const normalize = (name) => name.toLowerCase().trim()
        .replace(/[®™©]/g, '')
        .replace(/\s+/g, ' ')
        .replace(/[^a-z0-9\s]/g, '');

    const shopwareNames = new Set(shopwareProducts.map(p => normalize(p.name)));

    // Find missing products
    const missing = snigelProducts.filter(name => !shopwareNames.has(normalize(name)));
    const found = snigelProducts.filter(name => shopwareNames.has(normalize(name)));

    // Results
    console.log('═══════════════════════════════════════════════════════════');
    console.log('   RESULTS');
    console.log('═══════════════════════════════════════════════════════════\n');

    console.log(`✅ Products FOUND in Shopware: ${found.length}`);
    console.log(`❌ Products MISSING from Shopware: ${missing.length}\n`);

    if (missing.length > 0) {
        console.log('MISSING PRODUCTS:');
        console.log('─────────────────────────────────────────────────────────');
        missing.forEach((name, i) => {
            console.log(`${(i + 1).toString().padStart(3)}. ${name}`);
        });
    }

    // Save comparison results
    const comparison = {
        timestamp: new Date().toISOString(),
        snigelTotal: snigelProducts.length,
        shopwareTotal: shopwareProducts.length,
        found: found.length,
        missing: missing.length,
        missingProducts: missing
    };

    fs.writeFileSync('snigel-comparison-result.json', JSON.stringify(comparison, null, 2));
    console.log('\n✅ Saved comparison to snigel-comparison-result.json');

    // Summary
    console.log('\n═══════════════════════════════════════════════════════════');
    console.log('   SUMMARY');
    console.log('═══════════════════════════════════════════════════════════');
    console.log(`\n   Snigel Portal:  ${snigelProducts.length} products`);
    console.log(`   Shopware Store: ${shopwareProducts.length} products`);
    console.log(`   ─────────────────────────────`);
    console.log(`   ✅ Matched:     ${found.length} (${((found.length / snigelProducts.length) * 100).toFixed(1)}%)`);
    console.log(`   ❌ Missing:     ${missing.length}\n`);
}

main().catch(console.error);
