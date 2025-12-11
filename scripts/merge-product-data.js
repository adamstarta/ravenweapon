/**
 * Merge Product Data
 * Combines original scraper data (with images) with B2B prices
 */

const fs = require('fs');
const path = require('path');

const config = {
    originalData: path.join(__dirname, 'snigel-data', 'products.json'),
    b2bData: path.join(__dirname, 'snigel-b2b-data', 'products-b2b-progress.json'),
    outputFile: path.join(__dirname, 'snigel-merged-products.json'),
    imagesDir: path.join(__dirname, 'snigel-data', 'images'),
};

console.log('\n========================================================');
console.log('       MERGE PRODUCT DATA');
console.log('========================================================\n');

// Load original data
console.log('Loading original product data...');
const originalProducts = JSON.parse(fs.readFileSync(config.originalData, 'utf8'));
console.log(`  Loaded ${originalProducts.length} products\n`);

// Load B2B data
console.log('Loading B2B price data...');
let b2bProducts = [];
if (fs.existsSync(config.b2bData)) {
    b2bProducts = JSON.parse(fs.readFileSync(config.b2bData, 'utf8'));
    console.log(`  Loaded ${b2bProducts.length} products with B2B prices\n`);
} else {
    console.log('  No B2B data found\n');
}

// Create B2B lookup by slug
const b2bBySlug = {};
b2bProducts.forEach(p => {
    if (p.slug) b2bBySlug[p.slug] = p;
});

// Merge data
console.log('Merging product data...');
const mergedProducts = originalProducts.map(product => {
    const b2b = b2bBySlug[product.slug];

    // Filter out favicon/icon images
    const realImages = (product.images || []).filter(img =>
        !img.includes('snigel_icon') &&
        !img.includes('cropped-') &&
        img.includes('wp-content/uploads')
    );

    const realLocalImages = (product.local_images || []).filter(img =>
        !img.includes('snigel_icon') &&
        !img.includes('cropped-')
    );

    // Check which local images actually exist
    const existingLocalImages = realLocalImages.filter(img =>
        fs.existsSync(path.join(config.imagesDir, img))
    );

    const merged = {
        url: product.url,
        slug: product.slug,
        name: product.name,
        short_description: product.short_description || '',
        colours: product.colours || [],
        images: realImages,
        local_images: existingLocalImages,
        in_stock: product.in_stock !== false,
    };

    // Add B2B data if available
    if (b2b) {
        merged.rrp_eur = b2b.rrp_eur;
        merged.b2b_price_eur = b2b.b2b_price_eur;
        merged.bulk_price_eur = b2b.bulk_price_eur;
        merged.article_no = b2b.article_no;
        merged.ean = b2b.ean;
        merged.weight_g = b2b.weight_g;
        merged.stock = b2b.stock;
        merged.category = b2b.category;
        merged.description = b2b.description || product.short_description;
        merged.has_b2b_price = true;
    } else {
        merged.has_b2b_price = false;
    }

    return merged;
});

// Save merged data
fs.writeFileSync(config.outputFile, JSON.stringify(mergedProducts, null, 2));

console.log('\n========================================================');
console.log('                    MERGE COMPLETE');
console.log('========================================================');
console.log(`  Total products: ${mergedProducts.length}`);
console.log(`  Output file: ${config.outputFile}`);
console.log('========================================================\n');

// Statistics
const withB2BPrices = mergedProducts.filter(p => p.has_b2b_price).length;
const withImages = mergedProducts.filter(p => p.local_images && p.local_images.length > 0).length;
const inStock = mergedProducts.filter(p => p.in_stock).length;

console.log('Statistics:');
console.log(`  - With B2B prices: ${withB2BPrices}`);
console.log(`  - With local images: ${withImages}`);
console.log(`  - In stock: ${inStock}`);
console.log('');

// Show sample products with B2B prices
if (withB2BPrices > 0) {
    console.log('Sample products with B2B prices:');
    mergedProducts.filter(p => p.has_b2b_price).slice(0, 5).forEach(p => {
        console.log(`  - ${p.name}: €${p.b2b_price_eur} (RRP: €${p.rrp_eur})`);
    });
    console.log('');
}
