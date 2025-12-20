/**
 * Snigel Product Comparison - Find missing products
 */

const fs = require('fs');
const path = require('path');

// Load Snigel B2B portal products (freshly scraped)
const snigelPortal = JSON.parse(fs.readFileSync(path.join(__dirname, 'snigel-products-list.json'), 'utf8'));
console.log(`\n📋 Snigel B2B Portal: ${snigelPortal.total} products`);

// Load merged products (what's in Shopware)
const mergedProducts = JSON.parse(fs.readFileSync(path.join(__dirname, 'snigel-merged-products.json'), 'utf8'));
console.log(`📋 Shopware (merged): ${mergedProducts.length} products`);

// Create sets of slugs for comparison
const portalSlugs = new Set(snigelPortal.products.map(p => p.slug.toLowerCase()));
const shopwareSlugs = new Set(mergedProducts.map(p => p.slug.toLowerCase()));

// Find missing products (in portal but not in Shopware)
const missing = snigelPortal.products.filter(p => !shopwareSlugs.has(p.slug.toLowerCase()));

// Find extra products (in Shopware but not in portal - might be discontinued)
const extra = mergedProducts.filter(p => !portalSlugs.has(p.slug.toLowerCase()));

console.log('\n═══════════════════════════════════════════════════════════════');
console.log(`   COMPARISON RESULTS`);
console.log('═══════════════════════════════════════════════════════════════\n');

console.log(`✅ Products in both: ${snigelPortal.total - missing.length}`);
console.log(`❌ Missing from Shopware: ${missing.length}`);
console.log(`⚠️  Extra in Shopware (not on B2B): ${extra.length}`);

if (missing.length > 0) {
    console.log('\n───────────────────────────────────────────────────────────────');
    console.log('   MISSING PRODUCTS (to add to Shopware):');
    console.log('───────────────────────────────────────────────────────────────\n');
    missing.forEach((p, i) => {
        console.log(`${(i + 1).toString().padStart(3)}. ${p.name}`);
        console.log(`      URL: ${p.url}`);
    });
}

if (extra.length > 0) {
    console.log('\n───────────────────────────────────────────────────────────────');
    console.log('   EXTRA PRODUCTS (in Shopware, not on B2B portal):');
    console.log('───────────────────────────────────────────────────────────────\n');
    extra.forEach((p, i) => {
        console.log(`${(i + 1).toString().padStart(3)}. ${p.name} (${p.slug})`);
    });
}

// Save missing products to file
if (missing.length > 0) {
    const output = {
        timestamp: new Date().toISOString(),
        comparison: {
            portal_total: snigelPortal.total,
            shopware_total: mergedProducts.length,
            in_both: snigelPortal.total - missing.length,
            missing_count: missing.length,
            extra_count: extra.length
        },
        missing_products: missing
    };
    fs.writeFileSync('snigel-missing-products.json', JSON.stringify(output, null, 2));
    console.log(`\n✅ Saved missing products to snigel-missing-products.json`);
}

console.log('\n═══════════════════════════════════════════════════════════════\n');
