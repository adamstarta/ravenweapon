const XLSX = require('xlsx');
const wb = XLSX.readFile(String.raw`c:\Users\alama\Downloads\products (2).xlsx`);
const sheet = wb.Sheets[wb.SheetNames[0]];
const products = XLSX.utils.sheet_to_json(sheet);

// Show columns
console.log('=== EXCEL COLUMNS ===');
if (products.length > 0) {
    console.log(Object.keys(products[0]).join('\n'));
}

console.log('\n=== SAMPLE PRODUCT (with price) ===');
const sample = products.find(p => p.selling_price);
console.log(JSON.stringify(sample, null, 2));

console.log('\n=== PRODUCT STATS ===');
console.log('Total products:', products.length);

// Count by prefix
const prefixes = {};
products.forEach(p => {
    if (p.number) {
        const prefix = p.number.split('-')[0];
        prefixes[prefix] = (prefixes[prefix] || 0) + 1;
    }
});
console.log('\nBy prefix:');
Object.entries(prefixes).forEach(([k, v]) => console.log(`  ${k}: ${v}`));

// Check price column
let priceStats = { total: 0, withPrice: 0, missingPrice: 0 };
products.forEach(p => {
    priceStats.total++;
    if (p.selling_price) priceStats.withPrice++;
    else priceStats.missingPrice++;
});
console.log('\n=== PRICE COVERAGE ===');
console.log(`  With price: ${priceStats.withPrice}`);
console.log(`  Missing price: ${priceStats.missingPrice}`);

// Check image columns
console.log('\n=== IMAGE COVERAGE ===');
for (let i = 1; i <= 10; i++) {
    const col = `image_${i}`;
    const count = products.filter(p => p[col]).length;
    if (count > 0) console.log(`  ${col}: ${count} products`);
}

// Show Raven Swiss products (to exclude)
console.log('\n=== RAVEN SWISS PRODUCTS (to exclude) ===');
const ravenSwiss = products.filter(p =>
    p.number && p.number.startsWith('RAV-')
);
console.log(`  Count: ${ravenSwiss.length}`);
ravenSwiss.slice(0, 3).forEach(p => console.log(`  - ${p.number}: ${p.name_en}`));

// Show AIMPACT products (to include)
console.log('\n=== AIMPACT PRODUCTS (to include) ===');
const aimpact = products.filter(p => p.number && p.number.startsWith('AIM-'));
console.log(`  Count: ${aimpact.length}`);
aimpact.slice(0, 3).forEach(p => console.log(`  - ${p.number}: ${p.name_en}`));
