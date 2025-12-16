const d = require('./snigel-data/products-with-variants.json');

console.log('=== PRODUCT DATA COMPLETENESS CHECK ===\n');
console.log('Total products:', d.length);
console.log('');

// Images
const withImages = d.filter(p => p.local_images && p.local_images.length > 0);
const withGallery = d.filter(p => p.galleryImages && p.galleryImages.length > 0);
console.log('With local_images:', withImages.length);
console.log('With galleryImages:', withGallery.length);

// Description
const withDesc = d.filter(p => p.description && p.description.length > 20);
console.log('With description:', withDesc.length);

// Categories
const withCat = d.filter(p => p.category);
console.log('With category:', withCat.length);

// Color
const withColour = d.filter(p => p.colour);
const withVariants = d.filter(p => p.hasColorVariants && p.colorOptions.length > 0);
console.log('With colour (simple):', withColour.length);
console.log('With color variants:', withVariants.length);

// Missing data
const missingCat = d.filter(p => !p.category);
const missingColour = d.filter(p => !p.colour && !p.hasColorVariants);

console.log('\n=== MISSING DATA ===');
console.log('Missing category:', missingCat.length);
console.log('Missing colour (no variant, no colour):', missingColour.length);

// Sample products - one SIMPLE, one VARIANT
console.log('\n=== SAMPLE SIMPLE PRODUCT ===\n');
const simple = d.find(p => !p.hasColorVariants && p.category);
if (simple) {
    console.log('Name:', simple.name);
    console.log('Images:', (simple.local_images || []).filter(img => img.indexOf('icon') === -1).length);
    console.log('Category:', simple.category);
    console.log('Colour:', simple.colour || 'NONE');
    console.log('Description:', simple.description ? simple.description.substring(0,80) + '...' : 'NONE');
}

console.log('\n=== SAMPLE VARIANT PRODUCT ===\n');
const variant = d.find(p => p.hasColorVariants && p.colorOptions.length > 1);
if (variant) {
    console.log('Name:', variant.name);
    console.log('Images:', (variant.local_images || []).filter(img => img.indexOf('icon') === -1).length);
    console.log('Category:', variant.category);
    console.log('Color Options:', variant.colorOptions.map(c => c.name).join(', '));
    console.log('Description:', variant.description ? variant.description.substring(0,80) + '...' : 'NONE');
}

// List all unique categories
console.log('\n=== ALL CATEGORIES ===\n');
const cats = [...new Set(d.map(p => p.category).filter(Boolean))].sort();
cats.forEach(c => {
    const count = d.filter(p => p.category === c).length;
    console.log(`  ${c}: ${count} products`);
});
