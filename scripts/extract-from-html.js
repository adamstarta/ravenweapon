/**
 * Extract product data from saved HTML file
 * Since browser DOM extraction isn't working, parse the saved HTML directly
 */

const fs = require('fs');
const path = require('path');

const htmlPath = path.join(__dirname, 'snigel-b2b-data', 'all-products.html');
const outputDir = path.join(__dirname, 'snigel-b2b-data');

console.log('\n========================================================');
console.log('       EXTRACT PRODUCTS FROM SAVED HTML');
console.log('========================================================\n');

// Load HTML
const html = fs.readFileSync(htmlPath, 'utf8');
console.log(`Loaded HTML file: ${(html.length / 1024).toFixed(0)} KB\n`);

// Extract products using regex
const products = [];
const seen = new Set();

// Pattern to match product containers
// Each product is in a tmb div with product URL, title, and price
const productPattern = /<div class="tmb tmb-woocommerce[^"]*"[^>]*>[\s\S]*?<a[^>]*href="(https:\/\/products\.snigel\.se\/product\/([^\/]+)\/)[\s\S]*?<h3 class="t-entry-title[^"]*"><a[^>]*>([^<]+)<\/a><\/h3>[\s\S]*?<bdi>([0-9,.]+)&nbsp;/g;

let match;
while ((match = productPattern.exec(html)) !== null) {
    const url = match[1];
    const slug = match[2];
    const name = match[3].trim();
    const priceStr = match[4];

    if (seen.has(slug)) continue;
    seen.add(slug);

    // Convert price from "30,22" to 30.22
    const price = parseFloat(priceStr.replace(',', '.'));

    products.push({
        url,
        slug,
        name,
        b2b_price_eur: price,
        in_stock: true
    });
}

console.log(`Extracted ${products.length} products\n`);

// Show sample
console.log('Sample products:');
products.slice(0, 15).forEach(p => {
    console.log(`  - ${p.name.substring(0, 40).padEnd(40)}: €${p.b2b_price_eur.toFixed(2)}`);
});

// Merge with original data for local images
console.log('\nMerging with original data...');
const originalPath = path.join(__dirname, 'snigel-data', 'products.json');
let originalData = [];

if (fs.existsSync(originalPath)) {
    originalData = JSON.parse(fs.readFileSync(originalPath, 'utf8'));
    console.log(`  Found ${originalData.length} products in original data`);
}

const originalBySlug = {};
originalData.forEach(p => {
    if (p.slug) originalBySlug[p.slug] = p;
});

const mergedProducts = products.map(p => {
    const original = originalBySlug[p.slug];
    if (original) {
        return {
            ...p,
            local_images: (original.local_images || []).filter(img =>
                !img.includes('snigel_icon') && !img.includes('cropped-')
            ),
            colours: original.colours || [],
            short_description: original.short_description || ''
        };
    }
    return p;
});

// Update main merged products file
console.log('\nUpdating main merged products file...');
const mainMergedPath = path.join(__dirname, 'snigel-merged-products.json');

if (fs.existsSync(mainMergedPath)) {
    const mainMerged = JSON.parse(fs.readFileSync(mainMergedPath, 'utf8'));

    let updated = 0;
    mergedProducts.forEach(p => {
        if (p.b2b_price_eur) {
            const existing = mainMerged.find(m => m.slug === p.slug);
            if (existing) {
                existing.b2b_price_eur = p.b2b_price_eur;
                // Calculate RRP as ~50% markup
                if (!existing.rrp_eur) {
                    existing.rrp_eur = Math.round(p.b2b_price_eur * 1.5 * 100) / 100;
                }
                existing.has_b2b_price = true;
                updated++;
            }
        }
    });

    fs.writeFileSync(mainMergedPath, JSON.stringify(mainMerged, null, 2));
    console.log(`  Updated ${updated} products with B2B prices`);
}

// Save output
const outputFile = path.join(outputDir, 'products-from-html.json');
fs.writeFileSync(outputFile, JSON.stringify(mergedProducts, null, 2));

// Stats
const withPrices = mergedProducts.filter(p => p.b2b_price_eur).length;
const withImages = mergedProducts.filter(p => p.local_images && p.local_images.length > 0).length;

console.log('\n========================================================');
console.log('                    COMPLETE');
console.log('========================================================');
console.log(`  Total products: ${mergedProducts.length}`);
console.log(`  With B2B prices: ${withPrices}`);
console.log(`  With local images: ${withImages}`);
console.log(`  Output: ${outputFile}`);
console.log('========================================================\n');
